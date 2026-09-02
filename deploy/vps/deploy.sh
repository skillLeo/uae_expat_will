#!/usr/bin/env bash
#
# Deploys the application on the VPS. Run as root.
#
#   bash deploy/vps/deploy.sh
#
# Assets are NOT built here. They are built on a workstation and shipped by
# ship.sh, because building on the server is what took the old site down twice:
# rolldown saturated the host and the whole account became unreachable,
# including SSH, so there was no way in to stop it. That was a shared-hosting
# process limit rather than a law of nature, but the split works well and there
# is no reason to hand a production box a job a laptop does better.
set -euo pipefail

APP_DIR=/var/www/uaeexpatwills
APP_USER=uew
REPO=${REPO:-https://github.com/skillLeo/uae_expat_will.git}
BRANCH=${BRANCH:-main}

# provision.sh recorded which PHP it settled on. Reading it here keeps this
# script from carrying a second, silently divergent copy of the version.
[ -f /etc/uew.env ] && . /etc/uew.env
PHP=/usr/bin/php${PHP_VERSION:-8.4}

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
as_app() { sudo -u "$APP_USER" "$@"; }

if [ "$(id -u)" -ne 0 ]; then echo "run as root" >&2; exit 1; fi

# ---------------------------------------------------------------------- code
if [ ! -d "$APP_DIR/.git" ]; then
    log "First deploy — cloning"
    rm -rf "${APP_DIR:?}"/* "${APP_DIR:?}"/.[!.]* 2>/dev/null || true
    as_app git clone --branch "$BRANCH" "$REPO" "$APP_DIR"
else
    log "Updating to origin/$BRANCH"
    as_app git -C "$APP_DIR" fetch origin "$BRANCH" -q
    as_app git -C "$APP_DIR" reset --hard "origin/$BRANCH" -q
fi

cd "$APP_DIR"

# ------------------------------------------------------------------- secrets
# Replace a key in place, or append it only if it is genuinely new.
#
# Blindly appending produced a .env with every key defined twice, because
# .env.example already carries most of them. Laravel reads the last
# occurrence so it behaved correctly, while leaving a file in which editing
# the obvious line has no effect -- a trap for whoever touches it next.
set_env() {
    local key=$1 value=$2
    if grep -q "^${key}=" .env; then
        # Rewrite the first occurrence and drop any later ones.
        awk -v k="$key" -v v="$value" '
            index($0, k "=") == 1 { if (!seen++) print k "=" v; next }
            { print }
        ' .env > .env.tmp && mv .env.tmp .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

if [ ! -f .env ]; then
    log "Creating .env"
    cp .env.example .env
fi

log "Environment"
# shellcheck disable=SC1091
. /root/.uew-db-credentials
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_DATABASE"
set_env DB_USERNAME "$DB_USERNAME"
set_env DB_PASSWORD "$DB_PASSWORD"
set_env APP_URL https://uaeexpatwills.com
set_env APP_ENV production
set_env APP_DEBUG false
set_env SESSION_DRIVER database
set_env SESSION_SECURE_COOKIE true
set_env QUEUE_CONNECTION database
set_env CACHE_STORE database
set_env FILESYSTEM_DISK local
set_env INERTIA_SSR_ENABLED true
set_env INERTIA_SSR_URL http://127.0.0.1:13714
set_env APP_TIMEZONE Asia/Dubai
chown "$APP_USER":"$APP_USER" .env
chmod 640 .env

# -------------------------------------------------------------- dependencies
log "Composer"
as_app composer install --no-dev --optimize-autoloader --no-interaction --quiet

# artisan needs the autoloader, so the key can only be generated once Composer
# has run -- not while the .env is being written.
#
# Only when there is no key at all. import-from-shared.sh brings the real one
# across from the old server, and a fresh key here would make every encrypted
# setting and every administrator's 2FA secret permanently unreadable.
if ! grep -q '^APP_KEY=base64:' .env; then
    log "Generating an application key"
    as_app "$PHP" artisan key:generate --force
fi

# ------------------------------------------------------------------ database
log "Migrations"
as_app "$PHP" artisan migrate --force

# --------------------------------------------------------------- filesystem
# exec() is available here, unlike the shared host where this had to be a
# hand-made symlink.
[ -L public/storage ] || as_app "$PHP" artisan storage:link

install -d -o "$APP_USER" -g "$APP_USER" storage/app/public storage/framework/{cache,sessions,views} storage/logs
chown -R "$APP_USER":"$APP_USER" storage bootstrap/cache

# nginx must be able to traverse to public/. A git checkout or a restored
# backup can put this back to 0750, so it is asserted on every deploy.
chmod 0755 "$APP_DIR"
chmod 0755 "$APP_DIR/public"

# Laravel ships a public/robots.txt. A static file there is served by nginx
# before the request reaches Laravel and silently overrides the robots route,
# which is how the sitemap went undeclared and /admin stayed crawlable for
# weeks. It is gitignored now; this is the belt to that braces.
rm -f public/robots.txt public/sitemap.xml

# ---------------------------------------------------------------- optimise
log "Caches"
as_app "$PHP" artisan config:cache
as_app "$PHP" artisan route:cache
as_app "$PHP" artisan view:cache
as_app "$PHP" artisan event:cache

# ---------------------------------------------------------------- services
log "Restarting services"
# The worker holds the code it booted with, so it has to come round to see the
# deploy. queue:restart asks it to stop cleanly after the job in hand.
as_app "$PHP" artisan queue:restart || true
systemctl restart uew-ssr
systemctl restart "php${PHP_VERSION:-8.4}-fpm"
systemctl reload nginx

# ----------------------------------------------------------------- verify
log "Verifying"
for _ in $(seq 1 15); do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 3 http://127.0.0.1:13714/health || true)
    [ "$code" = "200" ] && break
    sleep 1
done
printf '  renderer health: %s\n' "${code:-no answer}"
printf '  ssr processes:   %s\n' "$(pgrep -c -f 'node /var/www/uaeexpatwills/bootstrap/ssr/ssr.js' || echo 0)"
printf '  queue worker:    %s\n' "$(systemctl is-active uew-queue)"
printf '  scheduler:       %s\n' "$([ -f /etc/cron.d/uew-scheduler ] && echo installed || echo MISSING)"

# The only reliable signal that server-side rendering is actually working.
# Grepping for a phrase always passes, because every string on the page also
# appears in the JSON payload Inertia embeds.
rendered=$(curl -s --max-time 10 -H 'Host: uaeexpatwills.com' http://127.0.0.1/ | grep -c 'data-server-rendered="true"' || true)
printf '  server-rendered: %s\n' "$rendered"

log "Deployed"
