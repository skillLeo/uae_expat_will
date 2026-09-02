#!/usr/bin/env bash
#
# Builds the front end on this workstation and ships it to the VPS, then runs
# the deploy there.
#
#   VPS_SSH_PASS='...' ./deploy/vps/ship.sh
#
# Run from the repository root. An SSH key is used in preference to a password
# whenever one is loaded; the password is only a fallback.
#
# The build never happens on the server. See deploy.sh for why.
set -euo pipefail

VPS_HOST=${VPS_HOST:-200.234.43.188}
VPS_USER=${VPS_USER:-root}
APP_DIR=/var/www/uaeexpatwills
APP_USER=uew

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

[ -f artisan ] || { echo "Run from the repository root." >&2; exit 1; }

SSH=(ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20 "$VPS_USER@$VPS_HOST")
RSH="ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20"

# Prefer a key. sshpass only if there is no key and a password was supplied.
if ! ssh -o BatchMode=yes -o ConnectTimeout=8 "$VPS_USER@$VPS_HOST" true 2>/dev/null; then
    if [ -n "${VPS_SSH_PASS:-}" ]; then
        command -v sshpass >/dev/null || { echo "sshpass not installed" >&2; exit 1; }
        export SSHPASS="$VPS_SSH_PASS"
        SSH=(sshpass -e "${SSH[@]}")
        RSH="sshpass -e $RSH"
    else
        echo "No key access and VPS_SSH_PASS is not set." >&2
        exit 1
    fi
fi

log "Building"
npm ci --silent
npm run build

[ -f bootstrap/ssr/ssr.js ] || { echo "No SSR bundle was produced — check vite.config." >&2; exit 1; }

log "Shipping assets"
# --delete so a file removed from a build does not linger and get served.
# --stats rather than --info=stats1: macOS still ships rsync 2.6.9, which does
# not have --info at all, and this script runs from a workstation.
for path in public/build bootstrap/ssr; do
    rsync -az --delete --stats -e "$RSH" \
        "$path/" "$VPS_USER@$VPS_HOST:$APP_DIR/$path/"
done

log "Deploying on the server"
"${SSH[@]}" "
    chown -R $APP_USER:$APP_USER $APP_DIR/public/build $APP_DIR/bootstrap/ssr
    bash $APP_DIR/deploy/vps/deploy.sh
"

log "Checking the live site"
for p in "" pricing faqs assessment blog sitemap.xml robots.txt; do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "https://uaeexpatwills.com/$p" || echo "---")
    ssr=$(curl -s --max-time 20 "https://uaeexpatwills.com/$p" | grep -c 'data-server-rendered="true"' || true)
    printf '  /%-14s %s  ssr=%s\n' "$p" "$code" "$ssr"
done
