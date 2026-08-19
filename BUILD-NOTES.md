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
| OBS-01 | `Handoff.dc.html` says "27 internal statuses", but its own status table enumerates 25. The 25 enumerated ones are implemented. Worth confirming which two are missing. | Raised for the client |

## 5. Conflicts recorded
- **Review-before-payment scope.** The questionnaire document routes blended families,
  business owners, trusts, existing Wills and multi-country matters to review before payment
  (rules R-05..R-12). Website content states only DIFC, capacity concerns and active disputes
  are held. The questionnaire's version is seeded, since it is the safer and more specific of
  the two. Switching model is a data edit in the admin rule builder, not a code change.
  Flagged for the client.

## 6. Build status — what is complete, and what is not

Honest inventory. "Complete" means built, wired and exercised; "backend only" means the
domain logic, schema, permissions and tests exist but the admin screen does not.

### Complete
- Design import — all 21 files, mirrored to `design-src/`
- Token layer — `resources/css/app.css`, transcribed from Foundations/Handoff
- Schema — 30+ tables, all migrations run clean on both SQLite and MySQL
- Routing engine — data-driven, cross-question rules, severity precedence, section skips
- Assessment — 16 questions, 10 conditionals, 41 rules, 7 declarations, 5 result screens
- Public site — 13 pages, **server-rendered via Inertia SSR**, 57 FAQs, cookie consent,
  sitemap, robots, canonicals, Organization/WebPage/Service/BreadcrumbList schema
- Settings — 66 settings across 10 groups, encrypted secrets, history, audit
- RBAC — 42 permissions, 7 roles, separate `web`/`admin` guards
- Admin auth — two-step sign-in, mandatory 2FA with QR and recovery codes, lockout with a
  real countdown, session-length choice, disabled-account screen, enumeration resistance
- Admin — dashboard, case list with filters and search, case detail with itemised trigger
  reasons and per-viewer redaction
- Restricted cases — enforced at the query layer, covered by tests
- Audit log — append-only, guarded by a database trigger AND a model guard
- Notifications — dispatcher, 22 templates (11 email + 11 WhatsApp), WhatsApp→email
  fallback, runtime mailer rebuilt from settings
- Payments — gateway interface, Telr driver, refund calculator with all four bands,
  webhook signature verification
- Magic links — single-use, expiring, revocable, hash-stored
- Retention command and full schedule
- Mobile shell — bottom tab bar, bottom-anchored action bar, tables→cards, fixed stack order
- 122 tests, 268 assertions, all passing
- **Deployed and verified live at https://will.skillleo.com**

### Backend only — no admin screen yet
These have schema, domain logic, permissions and (mostly) tests, but no UI:
- Content editor for the 13 pages and 57 FAQs
- Questionnaire and routing rule builder (draft/preview/publish/rollback)
- Settings screens, including the test-send and test-connection buttons
- User and role managers
- Audit log viewer and export, consent export
- Payment screens — link generation, manual recording, refunds
- Operational analytics
- Documents and drafts/amendments

### Not started
- Client area (10 screens). Deliberately gated behind `client_portal_enabled`, which is
  FALSE. Nothing is reachable until Summit approves that phase in writing.
- The detailed post-payment questionnaire
- Payment webhook route (the driver and its signature verification are built and tested;
  the HTTP endpoint is not wired)
- Bespoke layouts for How It Works, Will Options, Pricing and About Us — these currently
  render through the generic `Page.vue`, so the content is live and correct but the
  distinctive per-page compositions from the design are not yet reproduced

### Deployment caveat
Cron is **not yet configured** — `crontab` is unavailable over SSH on this host and the
three entries must be added through hPanel. See `DEPLOYMENT.md`. Until they are, retention,
backups, the queue and the SSR watchdog do not run. The SSR server is currently running,
but nothing will restart it if it dies.
