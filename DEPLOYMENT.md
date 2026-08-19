# Deployment — will.skillleo.com

Live: **https://will.skillleo.com** · Host: Hostinger shared (CloudLinux) ·
Repo: `github.com/skillLeo/uae_expat_will`

The application is deployed **inside** `public_html` (the Hostinger pattern), with a root
`.htaccess` that rewrites into `public/` and blocks everything else.

---

## Host gotchas — read these before touching the deploy

Four things about this host will waste your afternoon if you do not know them.

### 1. The web PHP is 8.3; the app needs 8.4

Symfony 8 and several Spatie packages require `php >= 8.4.1`. The domain's default web PHP
is 8.3.31, so Composer's platform check aborts **before Laravel boots** and every page 500s
with an empty Laravel log — because the failure happens before the log handler exists.

The fix is in `.htaccess`:

```apache
AddType application/x-httpd-alt-php84 .php
```

`AddHandler application/x-httpd-alt-php84___lsphp .php` is **accepted and silently
ignored** on this host. `AddType` is the form that works. Verify with:

```bash
curl -sI https://will.skillleo.com/ | grep -i x-powered-by   # expect PHP/8.4.21
```

CLI is separate again: the shell alias points at php84, but a bare `php` is 8.2. Always use
the absolute path in scripts and cron: `/opt/alt/php84/usr/bin/php`.

### 2. The asset build needs its thread pool constrained

Vite 8 uses rolldown, which tries to spawn a Rust thread pool and gets `EAGAIN` from the
host. The build dies with `ThreadPoolBuildError`. Constrain it:

```bash
export RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1
npm run build
```

Node is not on `PATH` by default — it is installed via nvm (v20.20.2):

```bash
export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"
```

### 3. The host overwrites the CSP header

The vhost sets `Content-Security-Policy: upgrade-insecure-requests` **after** PHP runs, so
the policy from `SecurityHeaders` middleware never ships. The `.htaccess` reasserts it with
`Header always unset` then `Header always set`. If you change the CSP, change it in
`.htaccess` — changing only the middleware will appear to do nothing.

### 4. `exec()` is disabled, and there is no `crontab` over SSH

- `php artisan storage:link` fails (it shells out). Make the symlink by hand:
  `ln -s ../storage/app/public public/storage`
- **Cron must be added through hPanel.** See below.

---

## Cron entries to add in hPanel

These are **not yet configured** — `crontab` is unavailable over SSH on this host, so they
must be added through the hPanel cron interface. Nothing scheduled runs until they are.

| Schedule | Command |
|---|---|
| Every minute | `cd ~/domains/will.skillleo.com/public_html && /opt/alt/php84/usr/bin/php artisan schedule:run >> storage/logs/schedule.log 2>&1` |
| Every minute | `~/domains/will.skillleo.com/public_html/ssr-watchdog.sh >/dev/null 2>&1` |
| Every minute | `~/domains/will.skillleo.com/public_html/queue-tick.sh >/dev/null 2>&1` |

The first drives retention and backups. The second restarts the SSR server if it dies —
**without it, a crash silently drops the site to client-side rendering**, which loses the
server-rendered HTML the contract requires. The third drains the notification queue.

Both scripts are already on the server and executable.

---

## Deploying a change

```bash
ssh -p 65002 u290685119@46.202.183.38
cd ~/domains/will.skillleo.com/public_html

PHP=/opt/alt/php84/usr/bin/php
export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"
export RAYON_NUM_THREADS=1

git pull origin main
$PHP /usr/local/bin/composer2.phar install --no-dev --optimize-autoloader --no-interaction
$PHP artisan migrate --force
npm ci --no-audit --no-fund && npm run build

$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache

# Restart SSR so it picks up the new bundle.
pkill -f "bootstrap/ssr/ssr.js"; ./ssr-watchdog.sh
```

---

## Post-deploy checks specific to this build

```bash
php artisan content:verify-legal     # reports any legal page short of the spec
php artisan retention:apply --dry-run
```

`content:verify-legal` exits non-zero while the Privacy Policy, Payment and Refund Policy
and Legal Disclaimer remain short. That is expected until Summit supplies the full wording,
and is not a deploy failure.

## Verifying a deploy

```bash
curl -sI https://will.skillleo.com/ | grep -i x-powered-by          # PHP/8.4.21
curl -s  https://will.skillleo.com/ | grep -c "Without a Will"      # >0 = SSR is up
curl -sI https://will.skillleo.com/.env | head -1                   # 403
curl -sI https://will.skillleo.com/composer.json | head -1          # 404
```

If the homepage returns HTML but the `grep` finds nothing, SSR is down and the page is
client-rendering. Check `storage/logs/ssr.log` and run `./ssr-watchdog.sh`.

---

## Environment

`.env` is `chmod 600`, is not in the repository, and is blocked by `.htaccess`
(verified 403). Database `u290685119_will` on `127.0.0.1`.

Admin passwords are printed once by `AdminUserSeeder` and are not recoverable. To issue
new ones: `php artisan tinker` then set a password on the user directly. Two-factor
enrolment is mandatory at first sign-in and cannot be skipped.
