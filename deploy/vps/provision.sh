#!/usr/bin/env bash
#
# One-time provisioning for the UAE Expat Wills VPS.
#
# Run as root on a fresh Ubuntu/Debian box. It is idempotent: running it twice
# changes nothing the second time, so it is safe to re-run after a failure.
#
#   bash provision.sh
#
# It does NOT deploy the application or issue certificates. Those are
# deploy.sh and ssl.sh, in that order, because a certificate cannot be issued
# until DNS actually points here.
#
# Why a dedicated VPS at all: the shared host ran ten sites on one account. A
# Next.js app belonging to a different project exhausted the process budget,
# the SSR renderer was killed and could not restart, and this site served empty
# HTML to search engines for days. Nothing in the application was wrong. The
# whole point of this box is that nothing else runs on it.
set -euo pipefail

APP_USER=uew
APP_DIR=/var/www/uaeexpatwills
DOMAIN=uaeexpatwills.com
NODE_MAJOR=20

# PHP_VERSION is discovered, not assumed. Ubuntu 26.04 ships 8.5 and has no
# ondrej/php build, so a hardcoded 8.4 fails at the first apt-get. Whatever is
# chosen here is written to /etc/uew.env and every other script reads it from
# there, so the nginx socket path and the systemd units cannot drift from it.
PHP_VERSION=${PHP_VERSION:-}

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

if [ "$(id -u)" -ne 0 ]; then
    echo "provision.sh must run as root" >&2
    exit 1
fi

# --------------------------------------------------------------- base packages
log "Base packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq \
    ca-certificates curl gnupg lsb-release software-properties-common \
    git unzip zip rsync ufw fail2ban cron \
    nginx mariadb-server \
    ghostscript

# Prefer a version already installed, then the distribution's own package,
# and only then reach for ondrej/php. The PPA has no build for every release
# and adding it on one it does not support breaks apt for everything after.
log "PHP"
if [ -z "$PHP_VERSION" ]; then
    for v in 8.4 8.5 8.3; do
        if command -v php"$v" >/dev/null 2>&1; then PHP_VERSION=$v; break; fi
    done
fi

if [ -z "$PHP_VERSION" ]; then
    for v in 8.4 8.5 8.3; do
        if apt-cache show php"$v"-fpm >/dev/null 2>&1; then PHP_VERSION=$v; break; fi
    done
fi

if [ -z "$PHP_VERSION" ]; then
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 && apt-get update -qq || true
    for v in 8.4 8.5 8.3; do
        if apt-cache show php"$v"-fpm >/dev/null 2>&1; then PHP_VERSION=$v; break; fi
    done
fi

if [ -z "$PHP_VERSION" ]; then
    echo "No supported PHP (8.3-8.5) is installable on this release." >&2
    exit 1
fi
echo "  using PHP $PHP_VERSION"

# Everything else -- nginx's socket path, the queue unit, deploy.sh -- reads
# this rather than carrying its own copy of the number.
printf 'PHP_VERSION=%s\n' "$PHP_VERSION" > /etc/uew.env

# gd/imagick and exif are medialibrary's; intl and bcmath are the framework's;
# zip is composer's. mysqldump (mariadb-client) is spatie/laravel-backup's, and
# without it the nightly backup fails silently at the point you need it most.
apt-get install -y -qq \
    php"$PHP_VERSION"-fpm php"$PHP_VERSION"-cli \
    php"$PHP_VERSION"-mysql php"$PHP_VERSION"-mbstring php"$PHP_VERSION"-xml \
    php"$PHP_VERSION"-curl php"$PHP_VERSION"-zip php"$PHP_VERSION"-gd \
    php"$PHP_VERSION"-intl php"$PHP_VERSION"-bcmath php"$PHP_VERSION"-imagick \
    php"$PHP_VERSION"-redis \
    mariadb-client

# ------------------------------------------------------------------- composer
if ! command -v composer >/dev/null 2>&1; then
    log "Composer"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php"$PHP_VERSION" /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f /tmp/composer-setup.php
fi

# ----------------------------------------------------------------------- node
# The SSR renderer runs this. Node is a runtime dependency here, not a build
# tool: assets are built on a workstation and shipped, never built on the
# server. Building on the host is what took the old site down twice.
if ! command -v node >/dev/null 2>&1 || [ "$(node -v | cut -d. -f1)" != "v$NODE_MAJOR" ]; then
    log "Node $NODE_MAJOR"
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash - >/dev/null
    apt-get install -y -qq nodejs
fi

# ------------------------------------------------------------------- app user
if ! id -u "$APP_USER" >/dev/null 2>&1; then
    log "Application user: $APP_USER"
    adduser --system --group --home "$APP_DIR" --shell /bin/bash "$APP_USER"
fi
mkdir -p "$APP_DIR"
chown -R "$APP_USER":"$APP_USER" "$APP_DIR"

# adduser creates a home at 0750, and this home is the document root's parent.
# nginx runs as www-data and needs to traverse it to reach public/, so without
# this every request is a 404 with "Permission denied" buried in the nginx
# error log rather than anything the application ever sees.
#
# 0755 exposes nothing on its own: .env stays 0640 and owned by the app user,
# so www-data still cannot read it, and PHP-FPM runs as the app user.
chmod 0755 "$APP_DIR"

# ------------------------------------------------------------------- php-fpm
log "PHP-FPM pool"
POOL=/etc/php/"$PHP_VERSION"/fpm/pool.d/uew.conf
cat > "$POOL" <<EOF
; The application's own pool, so it never shares a process budget with
; anything else that might later land on this box.
[uew]
user = $APP_USER
group = $APP_USER
listen = /run/php/php$PHP_VERSION-fpm-uew.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
; Recycle workers periodically; a long-lived worker that has leaked is the
; classic cause of a slow site nobody can explain.
pm.max_requests = 500

php_admin_value[memory_limit] = 512M
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 25M
php_admin_value[max_execution_time] = 120
php_admin_flag[expose_php] = off
EOF

# Remove the default pool so www-data cannot serve anything unexpected.
rm -f /etc/php/"$PHP_VERSION"/fpm/pool.d/www.conf

# Production PHP settings. display_errors off matters: a stack trace on a legal
# services site can leak paths, queries and occasionally client data.
INI=/etc/php/"$PHP_VERSION"/fpm/conf.d/99-uew.ini
cat > "$INI" <<'EOF'
display_errors = Off
display_startup_errors = Off
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
date.timezone = Asia/Dubai
EOF
cp "$INI" /etc/php/"$PHP_VERSION"/cli/conf.d/99-uew.ini

systemctl enable --now php"$PHP_VERSION"-fpm >/dev/null 2>&1 || true
systemctl restart php"$PHP_VERSION"-fpm

# -------------------------------------------------------------------- mariadb
log "Database"
systemctl enable --now mariadb >/dev/null 2>&1 || true

# The password is generated here and written only to a root-readable file. It
# is never echoed, so it cannot end up in a terminal scrollback or a chat
# window. deploy.sh reads it from the same file.
CRED=/root/.uew-db-credentials
if [ ! -f "$CRED" ]; then
    DB_PASS=$(head -c 32 /dev/urandom | base64 | tr -d '/+=' | head -c 28)
    umask 077
    cat > "$CRED" <<EOF
DB_DATABASE=uaeexpatwills
DB_USERNAME=uaeexpatwills
DB_PASSWORD=$DB_PASS
EOF
fi
# shellcheck disable=SC1090
. "$CRED"

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USERNAME'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'localhost';
FLUSH PRIVILEGES;
SQL

# --------------------------------------------------------------------- nginx
log "nginx"
HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# nginx will not start a server block that listens with `ssl` and has no
# certificate, so the real config cannot go in until certbot has run. Use the
# plain-HTTP bootstrap vhost until then; ssl.sh swaps in the full one.
if [ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
    SRC="$HERE/nginx-uaeexpatwills.conf"
else
    SRC="$HERE/nginx-bootstrap.conf"
fi
sed "s|__PHP_VERSION__|$PHP_VERSION|g" "$SRC" > /etc/nginx/sites-available/uaeexpatwills
chmod 0644 /etc/nginx/sites-available/uaeexpatwills
ln -sfn /etc/nginx/sites-available/uaeexpatwills /etc/nginx/sites-enabled/uaeexpatwills
rm -f /etc/nginx/sites-enabled/default

# TLS settings, written here rather than pulled from certbot or the internet.
mkdir -p /etc/nginx/snippets
cat > /etc/nginx/snippets/uew-tls.conf <<'TLSEOF'
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 1d;
ssl_session_tickets off;

# No ssl_dhparam: every cipher above is ECDHE, so a finite-field DH parameter
# is never negotiated. Generating one costs minutes at provision time and
# buys nothing.
TLSEOF

mkdir -p /var/www/letsencrypt
chown -R www-data:www-data /var/www/letsencrypt
nginx -t && systemctl reload nginx

# ------------------------------------------------------------------- services
log "systemd units for the renderer and the queue"
install -m 0644 "$HERE/uew-ssr.service" /etc/systemd/system/uew-ssr.service
sed "s|__PHP_VERSION__|$PHP_VERSION|g" "$HERE/uew-queue.service" \
    > /etc/systemd/system/uew-queue.service
chmod 0644 /etc/systemd/system/uew-queue.service
systemctl daemon-reload
systemctl enable uew-ssr uew-queue >/dev/null 2>&1 || true

# --------------------------------------------------------------------- cron
# The shared host had no usable cron at all, so nothing scheduled ever ran: no
# backups, no retention, no overdue-case escalation. This is that gap closed.
log "Scheduler"
cat > /etc/cron.d/uew-scheduler <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * $APP_USER cd $APP_DIR && /usr/bin/php$PHP_VERSION artisan schedule:run >> /dev/null 2>&1
EOF
chmod 0644 /etc/cron.d/uew-scheduler

# ------------------------------------------------------------------ firewall
log "Firewall"
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null
systemctl enable --now fail2ban >/dev/null 2>&1 || true

# ------------------------------------------------------- unattended security
apt-get install -y -qq unattended-upgrades >/dev/null
dpkg-reconfigure -f noninteractive unattended-upgrades >/dev/null 2>&1 || true

log "Provisioned"
cat <<EOF

  Application user   $APP_USER
  Application dir    $APP_DIR
  Database           $DB_DATABASE  (credentials in $CRED, root-readable only)
  PHP                $(php$PHP_VERSION -v | head -1)
  Node               $(node -v)

  Next:
    1. Point $DOMAIN and www.$DOMAIN at this server's IP.
    2. bash deploy/vps/deploy.sh        first deploy
    3. bash deploy/vps/ssl.sh           once DNS resolves here
EOF
