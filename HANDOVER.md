# UAE Expat Wills — everything you need to pick this up cold

Written 31 August 2026 so a new conversation, or a new developer, can carry on
without the history. If this file and a chat message disagree, **this file
wins** — chat history is where scope has repeatedly been lost.

Read [BACKLOG.md](BACKLOG.md) alongside this. That is the task list; this is
how the thing works and how to deploy it.

---

## 1. What this is

A legal-tech platform for **Summit Legal Consultancy UAE**, built by SkillLeo.
Live at **https://will.skillleo.com**.

An expatriate answers a free screening assessment, a routing engine decides
whether Summit can serve them online or must look at the matter first, and
whoever can be served pays and completes a detailed questionnaire. Summit's
legal team drafts, the client approves, Summit assists with registration at the
authority.

The client contact is **Ahmed Mohammadi**, Managing Director. He is the
decision maker on wording, pricing and policy. **Dr. Mohamed Raouf** is the
Principal Legal Consultant.

### Always end with a message for the client

SkillLeo forwards these straight to Ahmed on WhatsApp. **Every time work is
finished, end the reply with a ready-to-send message in a fenced block**, so it
can be copied without editing.

Style it for him, not for a developer:

- lowercase, casual, plain words
- **no dashes, no bullet symbols, no em dashes, no jargon**; numbered points
  like "1." are fine
- say what it means for his business, not what was built
- structure: what is done, what he needs to do or decide, what is next
- when something is not done, say so plainly rather than softening it

Keep the engineering detail in the reply above the block, where SkillLeo reads
it. The block is Ahmed's.

### Two rules that override everything else

1. **Never write or alter Summit's legal wording.** Not the Terms, not the
   Privacy Policy, not the Refund Policy, not the Disclaimer. If a clause is
   missing or wrong, report it and stop. Prices inside those clauses come from
   a token, so a price change is not a wording change.
2. **Every commit is authored and committed by `skillLeo
   <hassam.dev.571@gmail.com>` only.** No other name, no `Co-Authored-By`, no
   AI attribution. The repo's local git config is already correct — do not pass
   `-c user.name=` or `-c user.email=` to `git commit`. This was got wrong once
   and needed a `filter-branch` rewrite of every commit plus a force-push.

---

## 2. Access

### Git

```
https://github.com/skillLeo/uae_expat_will.git      branch: main
```

### SSH

```
ssh -p 65002 u290685119@46.202.183.38
password: SkillLeo@571
```

Application path on the server:

```
~/domains/will.skillleo.com/public_html
```

### Database (on the server)

```
name      u290685119_will
user      u290685119_will
password  u290685119_Will
```

> **These credentials were shared in plain text over chat during the build and
> should be treated as exposed.** Rotating them is on the backlog and has not
> been done. Do it before launch.

### PHP on the server

The domain default is 8.3; this application needs 8.4. Always use the absolute
path:

```
/opt/alt/php84/usr/bin/php artisan ...
```

---

## 3. How to deploy

Two steps, and **never** combine them.

### Step one — code, migrations, seeders

```bash
ssh -p 65002 u290685119@46.202.183.38
cd ~/domains/will.skillleo.com/public_html
git fetch origin main -q && git reset --hard origin/main -q
/opt/alt/php84/usr/bin/php artisan migrate --force
/opt/alt/php84/usr/bin/php artisan cache:clear
/opt/alt/php84/usr/bin/php artisan config:cache
/opt/alt/php84/usr/bin/php artisan route:cache
/opt/alt/php84/usr/bin/php artisan view:cache
```

### Step two — assets, built locally and shipped

```bash
export SSHPASS='SkillLeo@571'
./scripts/deploy-assets.sh
```

### Never run `npm run build` on the server

It has taken the whole site down **twice**, including once when started
detached with `setsid nohup`. Rolldown saturates the account's CloudLinux
entry-process limit; every page then returns 503 **and SSH is refused**, so you
cannot get in to kill the process causing it. Recovery took about 45 minutes
each time, unattended.

`scripts/deploy-assets.sh` builds locally and rsyncs `public/build` and
`bootstrap/ssr` with `--delete`, then restarts the renderer. The host only
serves files.

### Other host facts that will bite you

- `exec()` is disabled, so `artisan storage:link` fails. The symlink exists,
  made by hand: `ln -s ../storage/app/public public/storage`.
- **There is no cron.** `crontab` is unavailable over SSH; entries must be
  added through hPanel and **have never been added**. Nothing scheduled runs:
  no backups, no retention, no overdue-case escalation. The admin dashboard's
  health panel reports this honestly, and `php artisan system:health` exits
  non-zero when anything is critical.
- The root `.htaccess` is not in the web root by accident — the Laravel app
  lives inside `public_html` and everything except `public/` is denied. A
  tracked copy is at `deploy/public_html.htaccess`, with the reasoning. It
  once denied `/storage/` wholesale, which silently broke **every uploaded
  file on the site**.
- Hostinger's CDN negative-caches 404s. A file that 404s once may keep 404ing
  after you upload it; add `?v=<timestamp>` to check.

---

## 4. How to verify a deploy

SSR is the one that lies. **`data-server-rendered="true"` is the only reliable
signal** — grepping for a phrase always "passes" because every string also
appears in the JSON payload.

```bash
curl -s https://will.skillleo.com/ | grep -c 'data-server-rendered="true"'
```

A stale renderer answers health checks perfectly while serving the previous
build, so `./scripts/ssr-watchdog.sh` compares the bundle's mtime against the
process start time.

---

## 5. Architecture, and the decisions behind it

Laravel 13, PHP 8.4, Inertia 3, Vue 3, Tailwind 4, Vite 8. Domain code under
`app/Domain/{Assessment,Cases,Payments,Notifications,Content,Settings,Audit,System}`.

**The routing engine** (`app/Domain/Assessment/RoutingEngine.php`) is the
centrepiece. Every question, option and rule is *data*, editable from the admin
rule builder without a deploy — a contractual requirement. It evaluates the
whole answer set, collects every match, then takes the most severe. Terminal
rules exit immediately.

**Questionnaires are versioned.** The seeder rebuilds a version in place only
if nothing has answered against it; otherwise it publishes a **new** version
beside the old one. An assessment must keep the exact wording and rules it was
answered under.

**Settings are runtime.** Mail, gateway and WhatsApp are rebuilt from the
database, never `.env`. `SettingsSeeder::define()` writes a default **only when
the row does not exist** — so *a price or setting change is always a migration,
never a seeder edit*. Change both, or tests pass against a value the site does
not use.

**The fee is single-sourced.** `CommercialTokens` is the only thing that knows
what `{fee}`, `{mirror_fee}`, `{total_2dp}` resolve to. Page copy, FAQs, two
legal clauses, notification templates, meta descriptions and structured data
all carry tokens. **Never type a price into content** — it went wrong twice.

**The audit log is append-only**, enforced by database triggers.

**Restricted cases** are enforced at the query layer: a global scope omits the
column from the SELECT for users without `cases.view_restricted`.

**Stored HTML is sanitised on save** (`HtmlSanitiser`) — article bodies and
page sections reach the browser through `v-html`, and `content.edit` is a
permission that could be given to a coordinator or agency.

---

## 6. Where things stand

**Live and working:** the full public site (13 pages, server-rendered), the
screening assessment, contact capture, both payment result screens, the
specialist request form for existing-Will and estate enquiries, 24 admin
screens, the payment webhook with idempotency, the refund engine, the system
health panel, and the blog.

**349 tests, 1408 assertions, all passing. Pint clean.**

### The two things blocking launch, neither of them code

1. **No SMTP.** The platform cannot send a single email. No receipts, no
   questionnaire links, no team alerts, no partner invitations. Everything
   email-shaped is built and untestable.
2. **Gateway in test mode.** No real payment can be taken.

Both are credentials Ahmed owes. Chase these before building anything else.

### Current prices

AED 10,000 single, AED 15,000 mirror, set 31 August 2026. With 5% VAT that is
10,500.00 and 15,750.00 at checkout. Authority charges are separate and shown
separately.

---

## 7. What to do next, in order

1. **Mirror wills.** Spec is complete in BACKLOG.md. One question is still
   unanswered — what happens if the partner's nationality is UAE.
2. **Wire the seven unconnected notification templates.** Written, editable,
   nothing fires them. Untestable until SMTP exists.
3. **Admin image upload with cropping.** Ahmed asked for it. There is no file
   upload anywhere in the admin today.
4. **Document upload** on the specialist request form.

---

## 8. Hard-won lessons — read these

Each of these cost real time or broke production.

- **A component that reimplements a question is a second source of truth.**
  The homepage hero has its own copy of question one. It swallowed the "someone
  has died" option for three rounds of bug reports while every test against
  `/assessment` passed. Test the path the customer actually takes.
- **A stop must block progress, not correction.** Refusing all input after a
  terminal outcome locked people out permanently on a mis-click.
- **Finishing must not end the site for that browser.** A completed assessment
  redirected every later visit back to the old result, so nothing the customer
  picked afterwards had any effect.
- **`define()` never overwrites.** A changed default reaches a fresh install
  and nowhere else.
- **The DIFC "Specialist Legal Review Request Flow" document is another
  client's project.** It carries UAE Expat Wills branding and was pasted into
  this project's chat, so it reads exactly like a spec for this site. It was
  built here, removed, and nearly rebuilt. Do not implement any part of it.
  DIFC on this site runs the whole questionnaire and ends on the review screen
  with payment blocked. That is correct and final.
- **Ask before deleting.** Told to remove that document, I removed more than
  it contained, including an instruction Ahmed had given separately. He noticed
  within the hour.
- **A price typed by hand into `pages.json` doesn't move when the price does.**
  How It Works and the UAE Will Options table both had "AED 2,199" typed as a
  literal instead of the `{fee}` token, so they silently sat through three
  price changes while everything else on the site updated correctly. Fixed
  31 Aug — `PriceIsSingleSourcedTest.php` now scans `pages.json` too, and
  asserts a rendered FAQ page too, because that gap is exactly what let it
  through: the test scanned `content.json` but not `pages.json`.
- **FAQ answers never went through the token pass.** Every other content type
  — page sections, notification templates, blog posts — runs through
  `CommercialTokens::apply()` before it reaches the browser. FAQs came straight
  off the model in `PageController::extra()`, so the FAQ page showed the
  literal text `{fee}` instead of a number. Fixed 31 Aug in `extra()`.
- **`pgrep -f "$BUNDLE" | head -1` only ever looks at one PID.** If a previous
  restart left two `node bootstrap/ssr/ssr.js` processes stacked, the watchdog
  could kill the wrong one and leave the real stale renderer bound to the
  port — a deploy's health check passes while the site keeps serving the old
  build, with nothing anywhere saying so. Hit this during the 31 Aug deploy:
  SSR went down for a few minutes after a routine asset ship and needed a
  manual `pkill` and restart to recover. `scripts/ssr-watchdog.sh` now kills
  every matching PID, polls for the port to clear and the replacement to
  answer healthy instead of guessing with a fixed `sleep`, and retries once.
