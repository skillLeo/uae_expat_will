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

Both scripts are already on the server and executable. They are also version-controlled at
`scripts/ssr-watchdog.sh` and `scripts/queue-tick.sh`, so a redeploy cannot lose them.

### Confirming the cron entries are actually running

Do not take the hPanel screen's word for it. Open **Admin → Dashboard** as a Super
Administrator or Administrator and read the **System health** panel, or run:

```bash
/opt/alt/php84/usr/bin/php artisan system:health
```

The command exits non-zero when anything is critical, so it works as a deploy gate. The
scheduler check reads a heartbeat the scheduler itself writes every minute — if that row
stops advancing, the cron entry is gone, whatever the panel says. When a check first
crosses into critical the platform emails the configured contact address once. It never
sends twice for the same state, and the alert contains no case data.

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

## The Cookie Policy is deliberately not published

`/cookie-policy` returns a 404 on purpose. The page states cookie counts per category, and
the specification forbids publishing it until the production cookie scan has verified them.
The content is written and stored — only the `is_published` flag is off.

To publish it once the scan is done: **Admin → Content → Cookie Policy → Publish**. Nothing
else needs changing. The footer link is built from whatever is published, so it returns on
its own. `php artisan content:verify-legal` reports the page as `withheld` and will flag it
if it ever goes live before someone means it to.

---

## One open commercial question: VAT on authority charges

Payments carry a type — `professional_fee` or `disbursement`. Both currently take
the same VAT rate from commercial settings, so an AED 750 court or registry charge is
billed to the customer at AED 787.50.

Whether VAT applies to a charge Summit merely collects and passes on is a tax question
for Summit's accountant, so the behaviour is deliberately unchanged. When the answer
arrives, exactly one method changes:

```php
// app/Domain/Payments/Enums/PaymentType.php
public function vatRate(): float
{
    return match ($this) {
        self::ProfessionalFee => (float) setting('commercial.vat_rate', 5),
        self::Disbursement => 0.0,   // <- the whole change
    };
}
```

Every call site already asks the type for its rate. Nothing else needs touching, and
`AuthorityFeePathTest` has a test asserting the current behaviour that will need its
expectation updated at the same time — deliberately, so the change cannot pass silently.

---

## Deploying: never interrupt the asset build

`npm run build` on this host is the one step that can take the whole account down. Rolldown
is memory-hungry, and if the SSH session carrying it is killed part-way the orphaned process
keeps running — which exhausts the CloudLinux entry-process limit, returns **503 on every
page**, and refuses new SSH connections so you cannot get in to kill it.

If that happens: it usually clears on its own once the limit window resets. If it does not,
hPanel is the only way back in — the browser Terminal under **Advanced**, or Hostinger
support, to kill the stray `node` / `rolldown` processes.

To avoid it entirely, run the build detached so an SSH drop cannot take it with it:

```bash
cd ~/domains/will.skillleo.com/public_html
export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"
export RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1
setsid nohup npm run build > storage/logs/build.log 2>&1 &
# then poll: tail -f storage/logs/build.log
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
curl -s  https://will.skillleo.com/ | grep -c 'data-server-rendered' # 1 = SSR is up
curl -sI https://will.skillleo.com/.env | head -1                   # 403
curl -sI https://will.skillleo.com/composer.json | head -1          # 404
```

### How to check SSR, and how NOT to

`data-server-rendered="true"` on the `#app` div is the only reliable signal. Grepping the
page for a phrase is **not** — every string also appears inside the Inertia JSON payload,
so a grep passes whether or not anything was rendered. That false positive cost real time
here: SSR looked fine for hours while nothing was being checked.

To inspect the rendered markup, take the document from `data-server-rendered` onwards and
look at that. Stripping `<script>` tags with a regex is unreliable on a page this size.

```bash
# Genuinely rendered markup, not the JSON payload
curl -s https://will.skillleo.com/ | sed -n '/data-server-rendered/,$p' | grep -c '<h1'
```

If `data-server-rendered` is absent, SSR is down and the page is client-rendering. Run
`./ssr-watchdog.sh` and check `storage/logs/ssr.log`.

### The stale-SSR trap

A stale SSR process is more dangerous than a dead one: it keeps answering, so every health
check passes, while silently serving the **previous** build. `ssr-watchdog.sh` compares the
bundle's mtime against the process start time and recycles it when the bundle is newer.
Always run the watchdog after a build rather than assuming a running process is current.

---

## Environment

`.env` is `chmod 600`, is not in the repository, and is blocked by `.htaccess`
(verified 403). Database `u290685119_will` on `127.0.0.1`.

Admin passwords are printed once by `AdminUserSeeder` and are not recoverable. To issue
new ones: `php artisan tinker` then set a password on the user directly. Two-factor
enrolment is mandatory at first sign-in and cannot be skipped.
