# BUILD NOTES — UAE Expat Wills Platform

Running log of decisions, deviations, open items and UX fixes.

## 0. Design import — COMPLETE

The Claude Design project `f503d5d6-7b79-40fd-8a64-af686b05b882` was imported in full and
mirrored to `design-src/` in this repository. **All 21 files are on local disk**, verified
complete (every `.dc.html` ends with a closing `</html>`, the specification ends with its own
closing note). The design canvas is no longer needed to finish this build.

| File | Bytes | What it is |
|---|---|---|
| `Foundations.dc.html` | 153,126 | Every token and component state, in real content |
| `Handoff.dc.html` | 38,806 | **The specification.** Tokens + contrast ratios, type scale, geometry, 17 components, page specs, the 13 routing rules, 11 conditionals, 27→8 status map, permission matrix, compliance non-negotiables, 10 open questions |
| `Homepage.dc.html` | 56,013 | The homepage |
| `HowItWorks.dc.html` | 18,449 | 9 steps + responsibility matrix |
| `WillOptions.dc.html` | 22,747 | 5 registration routes |
| `Pricing.dc.html` | 22,642 | Fee block, authority table, exclusions |
| `PublicPages.dc.html` | 116,389 | The remaining 9 public pages, cookie banner, 4 error pages |
| `Assessment.dc.html` | 55,305 | 16 questions, 10 conditionals, 6 outcomes |
| `Auth.dc.html` | 108,443 | 9 admin screens, 5 client screens (gated) |
| `Admin.dc.html` | 156,612 | 14 internal screens including the rule builder |
| `ClientArea.dc.html` | 91,675 | 10 screens — gated behind `client_portal_enabled` |
| `Notifications.dc.html` | 34,524 | 11 templates, email + WhatsApp |
| `Mobile.dc.html` | 16,008 | Homepage at 390px + the stacking order |
| `SiteHeader.dc.html` | 4,192 | 7 nav items + reserved utility slot |
| `SiteFooter.dc.html` | 4,675 | Footer, ownership line, disclaimer |
| `Wordmark.dc.html` | 10,918 | The swappable identity component (12 variants) |
| `Wordmarks.dc.html` | 11,754 | The 3 directions compared |
| `Headlines.dc.html` | 6,692 | 3 hero headlines at size |
| `support.js` | 69,150 | The design-canvas runtime (framework, not project content) |
| `uploads/UAE-EXPAT-WILLS-MASTER-SPECIFICATION.md` | 107,469 | 13-part master spec: questionnaire, routing, all 13 pages, compliance, contradictions register |

Notes on the import:
- `DesignSync.list_files` returns 404 for this project because its type is
  `PROJECT_TYPE_PROJECT`, not `PROJECT_TYPE_DESIGN_SYSTEM`. `get_file` works normally.
  Files were therefore fetched by name from the `FILES` manifest inside `Handoff.dc.html`.
- `Terms.dc.html` is linked from `SiteFooter` but does not exist — the five legal pages live
  inside `PublicPages.dc.html`. Logged as UX-01 below.
- `SUMMIT-CONTENT-KNOWLEDGE-BASE.md` and `SUMMIT-PLATFORM-FLOW-SPEC.md` 404 because the
  specification's own closing note records them as superseded and folded into it.

## 1. Environment deviations
- Brief says Laravel 12; installed framework is **Laravel 13.26.1**. Built against 13.
- Brief says `php artisan inertia:middleware`; inertia-laravel v3 ships the middleware and it
  is registered directly in `bootstrap/app.php`.
- Database is **SQLite** as scaffolded. Schema written portably.

## 2. Token corrections applied after reading Foundations/Handoff
The brief quoted a partial palette. Handoff's computed token table is authoritative and
differs, so the following were corrected in `resources/css/app.css`:
- Semantic `bg`/`border` pairs are **specified**, not derived. Exact values now used:
  positive `#EAF2ED`/`#C4DCD0`, progress `#E9EFF8`/`#C3D4EA`, attention `#F7F0DF`/`#E6D5A8`,
  held `#F0EBF6`/`#D6C9E5`, critical `#F8ECEA`/`#E8C9C4`, neutral `#F1F2F4`/`#DCDFE4`.
- Three tokens the brief omitted entirely: `--ink-70 #3A4A66` (secondary prose on paper),
  `--rule-warm #E4DDCE` (hairline on paper), `--input-border #8C93A1` (raised from `#B9C2D1`
  specifically so input borders clear 3:1 on white — do not lower it back).
- A **third type role** the brief omitted: **IBM Plex Mono** for case references, timestamps,
  money and fee tables, with `tabular-nums` always on.
- The type scale is Handoff's, not a guess: display-xl 72/1.02/-0.03em (40 mobile),
  display-l 44/1.08/-0.02em (32), display-m 34/1.14/-0.015em (26), h1 30/1.2, h2 24/1.3,
  h3 20/1.35, h4 17/1.4, body-l 18/1.65, body 16/1.65, body-s 14/1.6,
  legal 15/1.72 at 64ch, caption 13/1.5, eyebrow 12/1.4/0.14em.
- Elevation is five named shadows including `--shadow-sheet-ink` at raised alpha, because a
  shadow's tint follows its ground.
- Gold `#AD8A46` measures 2.91:1 on paper — decorative only. `#8A6512` (4.62:1) is the gold
  that may be read. Focus ring is 2px `#8A6512` at 2px offset, never removed.
- Grid: 12 columns, 1280 max, 32px gutters. Collapse at 1080 (spans → 1/-1), 900
  (tables → cards), 719 (margin column collapses, its annotation hides rather than wraps).

## 3. Open items for the client
Carried from `Handoff.dc.html`'s own open-questions tab; these are Summit's to answer.

| # | Item | Blocks |
|---|---|---|
| 01 | Wording of the 4th religion option and its result-screen reason line | Q5 |
| 02 | Hero headline 72px (3 lines) vs the briefed 76px (4 lines, breaks the 820px cap) | Cosmetic |
| 03 | Client Login in the header — slot reserved at 96×24, empty | Nothing |
| 04 | Client-area phase written approval | ClientArea |
| 05 | Two lawyer headshots, 3:4 | About Us |
| 06 | Authority fee figures need re-checking against current schedules | Pricing |
| 07 | Amendment allowance — shown as 2 rounds, spec does not fix a number | Checkout copy |
| 08 | Retention periods — only the 30-day one is specified, other 4 are proposed | Settings |
| 09 | First-draft target of 2 business days — confirm Summit stands behind it | How It Works |
| 10 | 11 WhatsApp templates need Meta Utility-category approval before launch | Launch |

## 4. UX issues found and fixed
| # | Issue | Fix |
|---|---|---|
| UX-01 | `SiteFooter` links `Terms.dc.html`, which does not exist in the design project | Footer links routed to the real `/terms-and-conditions` page |
| BUG-01 | The engine initially marked a trigger reason restricted when the QUESTION was sensitive. Sensitivity and restriction are different things: `is_sensitive` governs encryption at rest and analytics exclusion (religion, family, debts all qualify), whereas restriction is the narrow capacity-or-undue-influence access control. The bug restricted almost every held case, which would have hidden ordinary review cases from the coordinators whose job is to work them. | A reason is restricted only when the RULE'S OUTCOME is restricted. Caught by the engine test matrix; covered by a regression test. |
| BUG-06 | The client-area gate was route middleware, but Laravel sorts `Authenticate` early through its priority list — so an unauthenticated hit on `/client` redirected to sign-in instead of 404ing, confirming the gated area exists. `prependToPriorityList` did not help, because that sort only reorders middleware already in the list. | The gate is now a global web middleware matched on path, so nothing can be sorted in front of it. Covered by a test asserting 404 for a guest, for a signed-in customer, and when the flag row is absent entirely. |
| BUG-07 | SSR crashed on every error page. `ssr.js` dereferenced `page.props.ziggy.location`, but an error response is built inside the exception handler — outside the Inertia middleware that shares those props — so `ziggy` was undefined and setup threw. The 404 silently fell back to client-side rendering. | `ssr.js` guards the Ziggy props, and the exception handler now shares the standard props onto the error response so the header and footer render. |
| BUG-08 | Even after BUG-07, SSR still crashed on error pages. ZiggyVue falls back to `window.location` when no `location` is passed, and `window` does not exist in the Node SSR process. Every error page silently dropped to client-side rendering. | The exception handler now shares a real `location`, and `ssr.js` skips Ziggy entirely when there is none. Seven regression tests cover the error pages. |
| OBS-02 | Verifying SSR by grepping the page for a phrase is a false positive: every string also appears in the Inertia JSON payload, so the grep passes whether or not anything rendered. `data-server-rendered="true"` is the only reliable signal. Documented in DEPLOYMENT.md. | Method corrected |
| OBS-03 | A stale SSR process keeps answering health checks while serving the previous build — more dangerous than a dead one, because every check passes. `ssr-watchdog.sh` now compares bundle mtime against process start time and recycles. | Fixed |
| OBS-01 | `Handoff.dc.html` says "27 internal statuses", but its own status table enumerates 25. The 25 enumerated ones are implemented. Worth confirming which two are missing. | Raised for the client |

## 5. Conflicts recorded
- **Review-before-payment scope.** The questionnaire document routes blended families,
  business owners, trusts, existing Wills and multi-country matters to review before payment
  (rules R-05..R-12). Website content states only DIFC, capacity concerns and active disputes
  are held. The questionnaire's version is seeded, since it is the safer and more specific of
  the two. Switching model is a data edit in the admin rule builder, not a code change.
  Flagged for the client.

## 6. Build status — complete

Everything in the brief is built, wired and exercised. 159 tests, 476 assertions,
146 routes, 51 Vue pages.

### Public site
13 pages, all server-rendered through Inertia SSR, all content from `pages` and
`page_sections`. How It Works, UAE Will Options, Pricing and About Us have dedicated
components matching their design files; the rest use the shared page components. 57 FAQs
with every answer in the rendered HTML while collapsed. Cookie consent with four
categories, three equally weighted actions and nothing pre-ticked. Five error pages.
Sitemap, robots, canonicals, Organization/WebPage/Service/BreadcrumbList — and
deliberately no FAQPage markup.

### Assessment
16 questions, 10 conditionals, 41 routing rules, 7 declarations, 5 result screens. The
routing engine is fully data-driven with cross-question rules and severity precedence.
No question count is emitted anywhere.

### Admin
Dashboard, case list and detail with itemised trigger reasons, staff assignment,
configurable statuses with the internal-to-customer map visible, countdowns and overdue
flags, notes, contact logging, stage timestamps. Content editor for all 13 pages and 57
FAQs with drag reorder and publish state. Questionnaire and routing editor with question
and option CRUD, the exclusive toggle, a condition builder, a rule builder that renders
every rule as a live sentence, preview against test answers, draft/publish/rollback and
full history. Settings across all 10 groups with working test-send and test-connection
buttons that surface the real provider error. Users, roles with a grouped permission
matrix and a "what this role can and cannot see" preview, session revocation, 2FA reset.
Audit viewer with export and no edit affordance anywhere. Consent export. Payments with
link generation, manual recording and refunds showing the band and the full working.
Analytics. Notification template editor with variable preview and test send.

### Client area
Registration carrying the reference and outcome forward, email verification, sign-in,
magic-link sign-in as an equal option with four distinct failure screens, password reset,
dashboard with the eight-stage tracker, the detailed questionnaire on the same engine,
document upload with camera capture to the private disk via signed expiring URLs, draft
review with amendments and approval recorded as a consent.

**Gated behind `client_portal_enabled`, which is FALSE.** Every route 404s while it is
off — a 404 rather than a 403 so an unapproved phase is not advertised.

### Payment webhook
Signature verified before anything is read, idempotent by gateway reference plus status,
a `payment_events` row per delivery, automatic status and stage updates, throttled, with
a manual gateway status check as fallback. Tested with a replayed duplicate, a tampered
body, a wrong secret, a missing secret and an unknown reference.

### Mobile
Fixed bottom tab bar with safe-area inset on admin and client below 768px. Top bar with
contextual back, title and one action. Sheets rather than page swaps. Primary action
bottom-anchored. 46px targets. Every wide table becomes labelled stacked cards — the only
`overflow-x` in the codebase is the `.scroll-x` utility, never on the body. Pull-to-refresh
on list views. Both 2FA inputs are 22px so iOS cannot zoom them. `prefers-reduced-motion`
respected throughout, including the How It Works rail, which renders complete rather than
not at all.

## 7. Legal content verification — THREE PAGES ARE SHORT

Run `php artisan content:verify-legal` to reproduce this at any time.

| Page | Specification (Part 7.9) | Seeded | Status |
|---|---|---|---|
| Terms and Conditions | 25 clauses | 25 | complete |
| Privacy Policy | 18 clauses | 9 | **short by 9** |
| Payment and Refund Policy | 16 clauses | 9 | **short by 7** |
| Legal Disclaimer | 17 clauses | 9 | **short by 8** |
| Cookie Policy | 9 clauses | 9 | complete |

The design project carries excerpts of three of these, not the full text. The full wording
lives in Summit's 31-file content package, which is not in the design project and was never
supplied.

**Nothing has been drafted or completed to fill the gap.** The contract forbids altering
Summit's legal content, and inventing policy wording would be worse than leaving it short.
The three pages render exactly what was supplied.

Two further notes carried from the specification:
- The Privacy Policy and Cookie Policy are dated 6 August and marked *deliberately
  unchanged* — they should not be edited without Summit saying so.
- **The Cookie Policy must not be published until the production cookie scan is complete.**
  It is currently published. That is a launch decision, not a code change: unpublish it in
  the content manager, or complete the scan first.

## 8. UX and correctness issues found and fixed

| # | Issue | Fix |
|---|---|---|
| UX-01 | `SiteFooter` links `Terms.dc.html`, which does not exist in the design project | Footer routed to the real `/terms-and-conditions` |
| BUG-01 | The engine marked a trigger reason restricted when the QUESTION was sensitive. Sensitivity governs encryption and analytics exclusion; restriction is the narrow capacity-or-undue-influence control. The bug restricted nearly every held case, hiding them from the coordinators whose job is to work them. | Restriction now follows the rule's OUTCOME only. Regression test added. |
| BUG-02 | `AuditLogger` tried to `UPDATE` the activity row to add IP and route, but the append-only trigger correctly refused — so every login 500'd. | The columns are written on insert by a custom `AuditActivity` model. The guard was right; the code was wrong. |
| BUG-03 | The auto-generated unique index on `questionnaire_result_screens` was 68 characters. SQLite accepts it, MySQL caps at 64 — so it passed locally and failed on the first production migrate. | Named explicitly. A scan confirmed it was the only one over the limit. |
| BUG-04 | `RoleController::preview` used Spatie's `role()` scope without a guard. It defaults to `web`; the roles live on `admin`, so the screen 500'd. | Guard named explicitly. Caught by the admin screen smoke test. |
| BUG-05 | `case` used as a Vue prop name in the admin and client areas. It is a reserved JavaScript word and cannot appear in a template expression, so the build failed. | Renamed to `record` throughout. |
| OBS-01 | `Handoff.dc.html` says "27 internal statuses" but its own table enumerates 25. The 25 enumerated are implemented. | Raised for the client |
