# Moving UAE Expat Wills to its own server

From Hostinger shared hosting (`will.skillleo.com`) to a dedicated VPS
(`uaeexpatwills.com`).

## Why

The shared account ran **ten sites**. A Next.js application belonging to a
different project held 172 MB for four hours, the account ran out of process
slots, `pthread_create` began failing, and the SSR renderer was killed. Nothing
restarted it, because that host has no usable cron. Pages still worked in a
browser — the JavaScript runs client-side — but **search engines received empty
HTML** and nobody was told.

It happened again on 2 September, mid-migration-planning, with the renderer
found dead and no error in any log. That is not a bug that can be fixed in the
application. It is the hosting.

On this VPS the renderer is a systemd unit with `Restart=always`, so the same
failure self-heals in five seconds.

## What the move turns on

Things that are built, tested, and have never once run:

| | Why it never ran |
|---|---|
| Nightly database backups (22:30) | no cron on the shared host |
| Health checks every 5 minutes | no cron |
| Data-retention policy (23:00) | no cron — a privacy-policy commitment |
| Overdue-case escalation (2-hourly) | no cron |
| Queue worker | no process manager |
| Automatic renderer recovery | no service manager |

## Order of operations

Nothing here is reversible in a hurry, so the order matters. The old site keeps
serving until step 6.

### 1. Provision

```bash
ssh root@200.234.43.188
git clone https://github.com/skillLeo/uae_expat_will.git /tmp/uew
bash /tmp/uew/deploy/vps/provision.sh
```

Installs nginx, PHP-FPM (the newest of 8.4/8.5/8.3 the release offers), MariaDB, Node 20, Composer, certbot, ufw and
fail2ban. Creates the `uew` user, its own PHP-FPM pool, the database, the two
systemd units and the scheduler cron entry. Idempotent — safe to re-run.

The database password is generated on the server and written only to
`/root/.uew-db-credentials`. It is never printed, so it cannot end up in a
terminal log or a chat window.

### 2. Deploy the code

```bash
bash /var/www/uaeexpatwills/deploy/vps/deploy.sh
```

### 3. Bring the data across

```bash
OLD_SSH_PASS='...' bash /var/www/uaeexpatwills/deploy/vps/import-from-shared.sh
```

Database, `storage/app`, and **`APP_KEY`**.

> `APP_KEY` is not optional and not regenerable. Laravel encrypts at rest with
> it, and this application encrypts the SMTP password, the payment gateway auth
> key and webhook secret, the WhatsApp token, and **every administrator's
> two-factor secret and recovery codes**. A fresh key makes all of it
> permanently unreadable and locks every admin out of their own second factor.
> The import script refuses to continue if it cannot read the old one.

### 4. Ship the built front end

From a workstation, in the repository:

```bash
VPS_SSH_PASS='...' ./deploy/vps/ship.sh
```

Assets are **never** built on the server. Doing that took the old site down
twice — rolldown saturated the host and SSH itself stopped answering, so there
was no way in to stop it.

### 5. Check it before the domain moves

`ship.sh` checks over HTTPS, which will not work yet. Check locally on the box:

```bash
curl -s -H 'Host: uaeexpatwills.com' http://127.0.0.1/ | grep -c 'data-server-rendered="true"'   # must be 1
sudo -u uew php /var/www/uaeexpatwills/artisan system:health
systemctl status uew-ssr uew-queue
```

`data-server-rendered="true"` is the **only** reliable signal. Grepping for a
phrase always passes, because every string on the page also appears in the JSON
payload Inertia embeds.

### 6. Point the domain, then issue the certificate

`uaeexpatwills.com` currently resolves to Hostinger parking
(`dns-parking.com` nameservers, `hstgr.net` CDN). In hPanel, under
**Domains → DNS Zone**, delete the existing `@` and `www` records including the
CDN entry, and add:

```
A   @     200.234.43.188
A   www   200.234.43.188
```

Then, once it resolves:

```bash
bash /var/www/uaeexpatwills/deploy/vps/ssl.sh
```

It refuses to run if DNS has not moved, rather than burning one of Let's
Encrypt's five-per-week attempts.

### 7. Redirect the old address

So the move does not throw away the indexing the old address has, and so no
customer with a bookmark hits a dead page. On the **shared host**, replace the
contents of `~/domains/will.skillleo.com/public_html/.htaccess` with
`deploy/vps/old-host-redirect.htaccess`.

A 301 passes ranking to the new address. Leave it in place for at least six
months.

### 8. Tell Google

1. Add `https://uaeexpatwills.com` as a property in Search Console.
2. Verify it — paste the token into **Settings → Analytics** in the admin, in
   either *Search Console verification* (meta tag) or *Search Console
   verification file* (the `google….html` filename). Both are served
   automatically; neither needs a file uploading, and neither is lost on a
   deploy.
3. Submit `https://uaeexpatwills.com/sitemap.xml`.
4. Use **Change of Address** on the old property to point at the new one.
5. Put the GA4 measurement ID into **Settings → Analytics**. It only loads
   after a visitor accepts analytics cookies, which is deliberate.

## After the move

- [ ] Confirm a backup actually ran: `ls -la /var/www/uaeexpatwills/storage/app/UAE*`
- [ ] `systemctl status uew-queue` is active
- [ ] `php artisan system:health` — scheduler, backups and retention should
      all have gone from critical to healthy
- [ ] Set Ahmed's password: `php artisan admin:password ahmed@summitlegaluae.com`
- [ ] **Rotate the old shared-host credentials.** They were shared in plain
      text over chat and must be treated as exposed.
- [ ] Add an SSH key and turn off password authentication on the VPS.

## What still blocks launch, and is not code

Unchanged by the move. Both are credentials Ahmed owes:

1. **SMTP.** The platform cannot send a single email — no receipts, no
   questionnaire links, no team alerts.
2. **Live Telr keys.** The gateway is in test mode, so no real payment can be
   taken.
