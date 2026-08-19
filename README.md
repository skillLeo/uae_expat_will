# UAE Expat Wills

A digital legal-service platform for UAE Will assessment, preparation, human legal review
and registration assistance.

Owned and operated by **Summit Legal Consultancy UAE** · Trade Licence No. 4429232.01.
Built by SkillLeo SMC Pvt Ltd.

> UAE Expat Wills and Summit Legal Consultancy UAE are not a court, registry, notary or
> government authority. The platform does not itself register, notarise or issue Wills.

---

## Stack

| | |
|---|---|
| Backend | Laravel 13.26 · PHP 8.3+ |
| Frontend | Inertia 3 · Vue 3 (`<script setup>`) · Tailwind 4 |
| Rendering | **SSR enabled** — the public pages are genuinely server-rendered |
| Database | SQLite in development, MySQL in production |
| Queue / cache / session | `database` driver |
| Timezone | `Asia/Dubai` |

Key packages: `spatie/laravel-permission`, `spatie/laravel-activitylog`,
`spatie/laravel-medialibrary`, `spatie/laravel-backup`, `spatie/laravel-sitemap`,
`pragmarx/google2fa`, `tightenco/ziggy`, `pestphp/pest`.

---

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # add SEED_DEMO_DATA=true for 40 demo cases
npm run build                       # builds the client AND the SSR bundle
```

Run it:

```bash
php artisan inertia:start-ssr &     # required — the public site is server-rendered
php artisan serve
php artisan queue:work              # every notification is queued
```

`AdminUserSeeder` prints two administrator passwords **once**. They are random on every
seed and are never committed. Two-factor enrolment is mandatory at first sign-in.

---

## Architecture

```
app/Domain/                 business logic, by domain
  Assessment/               RoutingEngine, Actions, DTOs, Enums
  Cases/                    case lifecycle, references, magic links
  Payments/                 gateway interface, Telr driver, refund calculator
  Notifications/            dispatcher, runtime mailer, WhatsApp client
  Content/ Settings/ Audit/
app/Http/Controllers/       thin — they validate, delegate, and render
app/Models/                 Eloquent, soft-deletable, activity-logged
resources/js/               Vue 3 SFCs, one Page component per screen
```

Every mutation goes through a **FormRequest** and an **Action** class. Controllers hold no
business logic.

### The routing engine

`app/Domain/Assessment/RoutingEngine.php` is 100% data-driven. Questions, options,
visibility conditions and routing rules are all database rows, so changing the assessment
is an edit in the admin rule builder — never a code change. The signed contract (Part B
clause 5) requires exactly that.

Two properties make it more than a lookup table:

1. **Cross-question rules.** A rule may read an answer given many screens earlier. R-12 is
   *"Q13A wish needs the wider route AND Q5 = Muslim → review"*. The engine evaluates
   against the whole answer set, not the question just answered.
2. **Precedence, not first-match.** Every matching rule is collected and the most severe
   outcome governs, so a single review anywhere sends the whole case to review.

Outcome precedence: `stop_ineligible` → `stop_refer` → `urgent_review` → `review` →
`continue` (+ flags, reminders, route marks).

Everything is re-derived server-side on every step. A tampered client cannot reveal a
hidden branch or talk its way past a stop.

---

## Things that are deliberate

These are compliance requirements from the client's own documents, not preferences.
Changing one needs Summit's written agreement.

- **No question count, ever.** Progress is named stages. The number of questions depends
  on the answers, so `"3 of 16"` would be a promise the engine cannot keep.
- **No payment control on a held matter.** The control does not exist in the DOM, rather
  than being rendered disabled.
- **Restricted cases.** A capacity or undue-influence flag is visible only with
  `cases.view_restricted`. The restricted column is not even SELECTed for anyone else —
  enforced at the query layer by `RestrictedCaseScope`, because hiding a field in the
  serialiser is how it ends up in a dev-tools screenshot. The row stays present and
  countable; only its body is redacted.
- **Restricted reasons never appear** in a notification, an email subject, an export or a
  search result. Internal alerts carry the reference and the outcome bucket and nothing else.
- **No FAQPage structured data.** The client has ruled it out. Organization, WebPage,
  Service and BreadcrumbList only.
- **No contact form.** Email and WhatsApp as live text links.
- **No horizontal scroll anywhere.** Wide tables become labelled stacked cards below 900px.
- **The audit log is append-only** — enforced by a database trigger, an application-level
  model guard, and the absence of any update or delete route.
- **`client_portal_enabled` defaults to FALSE.** The client area is fully built but
  commercially gated: nothing is reachable until Summit approves that phase in writing.
  An absent or unreadable flag reads as *off*, never *on*.

---

## Settings

Everything runtime-configurable lives in the `settings` table, never in `.env`:
branding, contact, commercial, mail, WhatsApp, payment, analytics, security, retention and
feature flags. Credentials are encrypted at rest and never shipped to the browser
(`is_public` gates that). Every change writes to `settings_history` and the audit log, with
secrets recorded redacted.

The mailer, the payment gateway and the WhatsApp client all rebuild their configuration
from these rows at runtime, so an administrator changes a credential without a deploy.

---

## Tests

```bash
php artisan test          # 122 tests, 268 assertions
vendor/bin/pint           # code style
```

Covering: routing-engine correctness for every outcome including the religion-conditional
rules and the exclusive multi-select; restricted-case invisibility in queries, search,
serialisation and exports; permission enforcement on admin routes; the 2FA gates; account
enumeration resistance; magic-link single-use, expiry and revocation; refund-band
calculation from stage timestamps; webhook signature verification; audit-log immutability;
and settings-driven mail and gateway configuration.

---

## Deployment

CI/CD deploys to `will.skillleo.com`. On the server:

```bash
composer install --no-dev --optimise-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
npm ci && npm run build
php artisan inertia:start-ssr     # keep alive via supervisor/systemd
php artisan queue:work            # keep alive
```

Add the scheduler to cron:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

### Restoring a backup

```bash
php artisan backup:list
# download the archive from the configured disk, then:
unzip backup.zip -d ./restore
mysql -u USER -p DATABASE < ./restore/db-dumps/mysql-DATABASE.sql
# restore storage/app/private from ./restore/storage
php artisan config:clear && php artisan cache:clear
```

---

## Documentation

- **`BUILD-NOTES.md`** — decisions, deviations, open items for the client, and the UX
  issues found and fixed during the build. **Read this before changing anything.**
- **`design-src/`** — the imported Claude Design project (21 files), including
  `Handoff.dc.html` (the specification) and the master specification markdown.
