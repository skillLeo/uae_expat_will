#!/usr/bin/env bash
#
# Moves the live data off the shared host and into this VPS.
#
# Run as root ON THE VPS, after provision.sh and after the code is in place.
#
#   OLD_SSH_PASS='...' bash deploy/vps/import-from-shared.sh
#
# Three things travel, and the third is the one that quietly ruins a migration
# if you forget it:
#
#   1. The database.
#   2. storage/app  — uploaded documents and the founder photographs.
#   3. APP_KEY.
#
# APP_KEY is not a formality. Laravel encrypts at rest with it, and this
# application encrypts the SMTP password, the payment gateway auth key and
# webhook secret, the WhatsApp access token, and every administrator's
# two-factor secret and recovery codes. Generate a fresh key on the new server
# and all of that becomes permanently unreadable: the gateway stops
# authenticating, and every admin is locked out of their own second factor with
# no way back except clearing it by hand. The key comes across with the data or
# the migration is not a migration.
set -euo pipefail

OLD_HOST=${OLD_HOST:-46.202.183.38}
OLD_PORT=${OLD_PORT:-65002}
OLD_USER=${OLD_USER:-u290685119}
OLD_PATH=${OLD_PATH:-domains/will.skillleo.com/public_html}

APP_DIR=/var/www/uaeexpatwills
APP_USER=uew

# provision.sh recorded which PHP it settled on.
[ -f /etc/uew.env ] && . /etc/uew.env
PHP=php${PHP_VERSION:-8.4}
STAMP=$(date +%Y%m%d-%H%M%S)
WORK=/root/uew-migration-$STAMP

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

if [ "$(id -u)" -ne 0 ]; then echo "run as root" >&2; exit 1; fi
if [ -z "${OLD_SSH_PASS:-}" ]; then
    echo "Set OLD_SSH_PASS to the shared host's SSH password." >&2
    exit 1
fi

command -v sshpass >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y -qq sshpass; }

mkdir -p "$WORK"
chmod 700 "$WORK"
export SSHPASS="$OLD_SSH_PASS"
SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=20 -p "$OLD_PORT")
REMOTE="$OLD_USER@$OLD_HOST"

# ------------------------------------------------------------------- database
log "Dumping the database on the shared host"
# --single-transaction so the dump is consistent without locking the live site
# out. --no-tablespaces because the shared account has no PROCESS privilege and
# mysqldump 8 asks for it by default, which is a confusing hard failure.
sshpass -e ssh "${SSH_OPTS[@]}" "$REMOTE" "
    cd ~/$OLD_PATH
    DB=\$(grep '^DB_DATABASE=' .env | cut -d= -f2-)
    DBU=\$(grep '^DB_USERNAME=' .env | cut -d= -f2-)
    DBP=\$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)
    mysqldump --single-transaction --quick --no-tablespaces \
        --default-character-set=utf8mb4 \
        -u\"\$DBU\" -p\"\$DBP\" \"\$DB\" | gzip -9 > /tmp/uew-$STAMP.sql.gz
    ls -lh /tmp/uew-$STAMP.sql.gz
"
sshpass -e scp "${SSH_OPTS[@]/-p/-P}" "$REMOTE:/tmp/uew-$STAMP.sql.gz" "$WORK/"
sshpass -e ssh "${SSH_OPTS[@]}" "$REMOTE" "rm -f /tmp/uew-$STAMP.sql.gz"

# --------------------------------------------------------------- uploaded files
log "Copying uploaded files"
mkdir -p "$WORK/storage-app"
sshpass -e rsync -az --stats \
    -e "ssh ${SSH_OPTS[*]}" \
    "$REMOTE:$OLD_PATH/storage/app/" "$WORK/storage-app/"

# ------------------------------------------------------------------- app key
log "Carrying the encryption key across"
OLD_APP_KEY=$(sshpass -e ssh "${SSH_OPTS[@]}" "$REMOTE" "grep '^APP_KEY=' ~/$OLD_PATH/.env | cut -d= -f2-")
if [ -z "$OLD_APP_KEY" ]; then
    echo "Could not read APP_KEY from the old .env. Stopping: importing without it" >&2
    echo "would silently destroy every encrypted setting and 2FA secret." >&2
    exit 1
fi

# ------------------------------------------------------------------- restore
log "Restoring into the new database"
# shellcheck disable=SC1091
. /root/.uew-db-credentials

# Keep a copy of whatever is already there. On a first migration this is empty,
# but on a re-run it is the only way back.
mysqldump --single-transaction --no-tablespaces "$DB_DATABASE" 2>/dev/null \
    | gzip -9 > "$WORK/pre-import-$DB_DATABASE.sql.gz" || true

gunzip -c "$WORK/uew-$STAMP.sql.gz" | mysql "$DB_DATABASE"

log "Restoring uploaded files"
mkdir -p "$APP_DIR/storage/app"
rsync -a "$WORK/storage-app/" "$APP_DIR/storage/app/"
chown -R "$APP_USER":"$APP_USER" "$APP_DIR/storage"

# ------------------------------------------------------------- env alignment
log "Pointing the application at its new home"
ENV="$APP_DIR/.env"
set_env() {
    local key=$1 value=$2
    if grep -q "^$key=" "$ENV"; then
        # A literal replacement, so a value containing / or & cannot corrupt it.
        python3 - "$ENV" "$key" "$value" <<'PY'
import sys, io
path, key, value = sys.argv[1], sys.argv[2], sys.argv[3]
lines = io.open(path, encoding='utf-8').read().splitlines(True)
out = []
for line in lines:
    if line.startswith(key + '='):
        out.append(f'{key}={value}\n')
    else:
        out.append(line)
io.open(path, 'w', encoding='utf-8').writelines(out)
PY
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV"
    fi
}

set_env APP_KEY "$OLD_APP_KEY"
set_env APP_URL "https://uaeexpatwills.com"
set_env APP_ENV production
set_env APP_DEBUG false
set_env DB_DATABASE "$DB_DATABASE"
set_env DB_USERNAME "$DB_USERNAME"
set_env DB_PASSWORD "$DB_PASSWORD"
set_env SESSION_SECURE_COOKIE true
chown "$APP_USER":"$APP_USER" "$ENV"
chmod 640 "$ENV"

# ---------------------------------------------------- settings that hold a URL
log "Updating stored absolute URLs"
sudo -u "$APP_USER" "$PHP" "$APP_DIR/artisan" migrate --force
sudo -u "$APP_USER" "$PHP" "$APP_DIR/artisan" cache:clear

log "Imported"
cat <<EOF

  Working copy kept at $WORK
    - uew-$STAMP.sql.gz          the dump that was restored
    - pre-import-*.sql.gz        whatever this database held beforehand
    - storage-app/               the uploaded files

  Delete it once the site has been checked end to end, not before.
EOF
