#!/usr/bin/env bash
#
# Issues the certificate and turns on HTTPS. Run as root, ON THE VPS, and only
# once DNS for the domain actually resolves to this machine.
#
#   bash deploy/vps/ssl.sh
#
# Let's Encrypt proves control by fetching a file over plain HTTP from the name
# on the certificate. If the domain still points at the old host, that fetch
# reaches the old host and the request fails — so this checks first and says so
# plainly, rather than burning one of the five-per-week rate-limited attempts.
set -euo pipefail

DOMAIN=uaeexpatwills.com
EMAIL=${LETSENCRYPT_EMAIL:-info@uaeexpatwills.com}

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

if [ "$(id -u)" -ne 0 ]; then echo "run as root" >&2; exit 1; fi

command -v certbot >/dev/null 2>&1 || {
    log "Installing certbot"
    apt-get update -qq && apt-get install -y -qq certbot python3-certbot-nginx
}

MINE=$(curl -s --max-time 10 https://api.ipify.org || hostname -I | awk '{print $1}')
log "Checking DNS"

# Each name is checked on its own, and only the ones that actually resolve
# here go on the certificate.
#
# Requesting a name that does not point at this server fails the whole
# request, not just that name -- so one lagging record would otherwise hold
# the apex hostage and leave the site with no HTTPS at all. The apex is
# required; www is included when it is ready and added later by re-running
# this script, which certbot handles as an expansion of the same certificate.
NAMES=""
ok=0
for name in "$DOMAIN" "www.$DOMAIN"; do
    # Authoritative answer, so a stale resolver cache neither adds a name that
    # is not ready nor drops one that is.
    got=$(dig +short A "$name" @"$(dig +short NS "$DOMAIN" | head -1)" 2>/dev/null | grep -E '^[0-9.]+$' | tail -1)
    [ -n "$got" ] || got=$(dig +short A "$name" | grep -E '^[0-9.]+$' | tail -1)

    if [ "$got" = "$MINE" ]; then
        printf '  %-26s -> %s  included\n' "$name" "$got"
        NAMES="$NAMES -d $name"
        [ "$name" = "$DOMAIN" ] && ok=1
    else
        printf '  %-26s -> %s  SKIPPED (expected %s)\n' "$name" "${got:-nothing}" "$MINE"
    fi
done

if [ "$ok" -ne 1 ]; then
    cat <<EOF

  The domain itself does not point here yet, so verification would fail.

  At the registrar for $DOMAIN, set:

      A     @      $MINE
      A     www    $MINE

  The nameservers are currently Hostinger's (dns-parking.com), so the records
  are changed in hPanel under Domains > DNS Zone. Remove any existing A or
  CNAME record for @ and www first, including the CDN entry.

  Wait for the change to take, then run this script again.

EOF
    exit 1
fi

log "Requesting the certificate"
# --expand so re-running once www is ready adds it to the same certificate
# rather than being refused as a duplicate.
# shellcheck disable=SC2086
certbot certonly --webroot -w /var/www/letsencrypt $NAMES --expand \
    --email "$EMAIL" --agree-tos --no-eff-email --non-interactive

log "Enabling HTTPS"
HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
[ -f /etc/uew.env ] && . /etc/uew.env
sed "s|__PHP_VERSION__|${PHP_VERSION:-8.4}|g" "$HERE/nginx-uaeexpatwills.conf" \
    > /etc/nginx/sites-available/uaeexpatwills
chmod 0644 /etc/nginx/sites-available/uaeexpatwills

[ -f /etc/letsencrypt/options-ssl-nginx.conf ] || \
    curl -fsSL https://raw.githubusercontent.com/certbot/certbot/main/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf \
        -o /etc/letsencrypt/options-ssl-nginx.conf
[ -f /etc/letsencrypt/ssl-dhparams.pem ] || \
    openssl dhparam -out /etc/letsencrypt/ssl-dhparams.pem 2048

nginx -t && systemctl reload nginx

# certbot installs its own renewal timer; this proves it works now rather than
# discovering in ninety days that it does not.
log "Testing renewal"
certbot renew --dry-run --quiet && echo "  renewal works"

log "HTTPS live"
curl -s -o /dev/null -w '  https://%{host} -> %{http_code}\n' "https://$DOMAIN"
