# Backlog

One list, in the repo, so it survives a lost chat and neither of us has to
reconstruct scope from WhatsApp history. Correct it freely — if something below
is wrong or missing, edit this file and it becomes the truth.

For how the platform works, how to deploy it and the access details, see
[HANDOVER.md](HANDOVER.md).

Last reviewed: 28 August 2026, against the full client conversation of 25 to
27 August. Every request in that thread is either done below or listed as open;
nothing from it is unaccounted for.

---

## Done and live

Verified on `will.skillleo.com`, not just merged.

| | Shipped |
|---|---|
| Price AED 1,999 single, 2,999 mirror, from one source | 25 Aug |
| Wordmark: UAE at the same size as the name | 25 Aug |
| Two assessment questions removed (debts, registered beneficiary) | 25 Aug |
| Beneficiary-protection question accepts several answers | 25 Aug |
| Review outcomes no longer block payment — 6 things stop a customer, not 27 | 25 Aug |
| Contact details captured after the age question | 25 Aug |
| Both approved result screens, single and mirror | 25 Aug |
| Under 18 cannot post past the stop | 25 Aug |
| UAE removed from the nationality list | 25 Aug |
| A mis-click can be corrected, plus "start again" | 26 Aug |
| DIFC eligibility notice on the religion question | 26 Aug |
| Founder photographs, homepage and About | 26 Aug |
| Existing-Will and estate go to a contact form, not a rejection | 27 Aug |
| Every multi-select says more than one answer is allowed | 28 Aug |
| Legal pages closed at 9 clauses, accepted by Ahmed | 26 Aug |
| Blog: posts, author and review dates, Article markup, admin editing | 28 Aug |
| Stored HTML sanitised on save; drafts previewable by an admin | 29 Aug |
| Fees set to AED 10,000 single and 15,000 mirror | 31 Aug |
| AED shown on the ADJD and Dubai Courts authority charges | 31 Aug |
| Dubai Courts Wills description said "Dubai's statutory framework" — now "UAE's" | 31 Aug |
| How It Works and UAE Will Options showed a hand-typed AED 2,199 that never picked up any price change — now token-driven | 31 Aug |
| FAQ fee answer showed the literal text "{fee}" instead of a number — FAQs now resolve tokens like every other content type | 31 Aug |
| UAE Will Options CTA aside ("This is the only call to action...") removed | 31 Aug |
| Mirror Wills price (AED 15,000) added as its own card on Pricing, next to the standard fee | 31 Aug |
| SSR watchdog could never find its own renderer, so every run started another one | 31 Aug |
| `admin:password` — the only way back in for a locked-out administrator | 31 Aug |
| robots.txt was a stale static file: sitemap undeclared, /admin crawlable | 2 Sep |
| Structured data reached crawlers as an empty script tag on every page | 2 Sep |
| Search Console file verification, served from a setting so a deploy cannot lose it | 2 Sep |
| Watchdog lock was held forever by the renderer, so it silently stopped watching | 2 Sep |

---

## Waiting on Ahmed

Nothing here can be built without him. The first two block launch outright.

- [ ] **SMTP credentials.** The platform cannot send a single email until these
      exist. No receipts, no questionnaire links, no team alerts. This is the
      biggest launch blocker and it is not a code problem.
- [ ] **Live Telr keys.** Gateway is in test mode, so no real payment can be
      taken.
- [ ] **Meta WhatsApp approval** for the 11 templates, plus the phone number ID,
      business account ID, permanent token and the two admin numbers.
- [ ] **Wording for the two request forms** — existing Will, and estate after
      death. Asked for on 25 August: "we will show different message to those 3
      options". He said he would send the text, then sent the DIFC document
      instead, which is void. The current wording is ours.
- [ ] **Mirror wills: what happens if the partner turns out ineligible** after
      the first person has already paid. He said "let the team decide", which
      covers DIFC but not a hard stop.
- [ ] **VAT on authority charges.** Currently 5% is applied to a pass-through
      government fee. His accountant's call. One line changes when answered.

---

## Waiting on SkillLeo

- [ ] **Three hPanel cron entries** — scheduler, queue worker, SSR watchdog.
      Nothing scheduled runs without them: no backups, no retention, no overdue
      alerts. The health panel on the admin dashboard reports this honestly.
      **Safe to add now, and it was not before 31 August**: the watchdog could
      not match its own renderer, so on a one-minute schedule it would have
      started a node process a minute until the account ran out of them. That
      is fixed. Adding these clears three of the four criticals on the health
      panel (scheduler, backups, retention).
- [ ] **Set Ahmed a password.** He has never logged in — confirmed 31 August,
      `last_login_at` is null and no 2FA secret exists. This is the login
      complaint. There is no admin forgot-password route and no mail server, so
      it cannot be self-served:
      `/opt/alt/php84/usr/bin/php artisan admin:password ahmed@summitlegaluae.com`
      It prompts, so the password never reaches a chat window or shell history.
      He is walked through 2FA setup on first login.
- [ ] **Rotate the SSH and database credentials.** They were shared in plain
      text over chat during the build and should be treated as exposed.
- [ ] **Deploy key** so the password stops being passed around:
      `ssh-keygen -t ed25519 -f ~/.ssh/uew_deploy` then `ssh-copy-id -p 65002`.

---

## Built but not yet connected

Real work, no external dependency.

- [ ] **Seven notification templates have no trigger.** Written and editable,
      but nothing fires them: assessment result (continue and held), payment
      receipt, further information required, draft approved, registration
      appointment, matter completed. Four of the eleven are wired. Roughly a day
      to connect the rest, and they cannot be tested until SMTP exists.
- [ ] **Authority-fee findings AF-01 to AF-04 and AF-06 are closed. AF-05 is
      open by decision** — see the VAT item above.

---

## Migration to a dedicated VPS

Scripts and the full runbook are in [deploy/vps/README.md](deploy/vps/README.md).
Target `200.234.43.188`, domain `uaeexpatwills.com`.

- [x] Provisioning, deploy, asset-shipping, SSL and data-import scripts written
- [x] systemd units for the renderer and the queue worker, so a dead renderer
      restarts itself in five seconds instead of staying dead unnoticed
- [x] Scheduler cron entry, which turns on backups, retention, health checks
      and overdue-case escalation for the first time
- [x] 301 redirect for the old address, so the move keeps its search ranking
- [x] **Provisioned and deployed, 2 September.** Ubuntu 26.04, PHP 8.5, nginx,
      MariaDB, Node 20. The VPS was bare, so nothing was removed — the
      placeholder on the domain is Hostinger's and goes when DNS goes.
- [x] Database, uploads and **APP_KEY** imported. The key's fingerprint is
      identical to the old server's, so every encrypted setting and every
      administrator's 2FA secret still decrypts.
- [x] Verified on the box: 14 of 14 pages server-rendered, sitemap on the new
      domain, all five services active, scheduler running, a backup taken.
- [x] **DNS moved and HTTPS live, 2 September.** Let's Encrypt certificate
      covering the apex and `www`, valid to 1 December, auto-renewal verified
      by `certbot renew --dry-run`.
- [x] Old address 301s to the new one with the path preserved, so the search
      ranking transfers and no bookmark breaks.
- [x] Full customer journey completed on the live site: reference
      SLC-2026-00022, payment allowed at AED 10,500.00.
- [ ] **Search Console.** Add `https://uaeexpatwills.com`, verify it through
      Settings → Analytics, submit the sitemap, and use Change of Address on
      the old property. Needs Ahmed's Google account.
- [ ] **GA4 measurement ID** into Settings → Analytics. Needs Ahmed.

## Not started

- [ ] **Mirror wills.** Partner link, one case with one reference and two forms,
      first person pays for both. Needs a look at the case model, because drafts
      are currently one per case and two people must approve separately.
      3 to 5 days. Spec as agreed with Ahmed on 28 August:
      - Partner block on the contact details form: name, nationality, phone,
        email. All required — cannot continue without them.
      - Partner email entered twice and shown back, because a typo in your own
        address is self-correcting and a typo in someone else's is not.
      - The "not available to UAE citizens" line under partner nationality.
      - The link goes to the partner the moment the details are given; person
        one carries straight on to payment without waiting.
      - Both arrive as one case, one reference, two forms.
      - **Open:** what happens if the partner nationality is UAE. Stop there and
        refer to the team, or continue and flag it. Not yet answered.
- [ ] **Admin image upload with cropping.** Ahmed asked for it. There is no file
      upload anywhere in the admin today — the `File` setting type exists but
      nothing implements it. Upload, storage, validation and a crop UI. About a
      day.
- [ ] **Document upload on the request form.** PDF, DOCX, JPG, PNG at lead
      stage.
- [ ] **Separate wording for the two request forms**, once Ahmed sends the text.

---

## Decisions on the record

Written down so nobody relitigates them.

- **The "Specialist Legal Review Request Flow" document is another client's and
  is ignored entirely.** Settled 28 August, after it was implemented here on
  26 August and removed on 27 August. It carries UAE Expat Wills branding and
  was pasted into this project's chat, which is what caused the confusion, but
  it is not this project's document. Do not implement any part of it, however
  convincing the header looks.

  Nothing of it remains in the code — verified: no DIFC request type, no DIFC
  status, no ticket, no DIFC result screen.

  **DIFC on this site behaves as it always has**: through the whole
  questionnaire, ending on the review screen, payment blocked. Rule R-05,
  outcome `review`. That is correct and final.

  Two things that look like they came from that document but did not, and stay:
  routing options four and five to a contact form, which was Ahmed's own
  instruction on 25 August; and the DIFC eligibility notice on the religion
  question, which he sent by message on the same evening.
- **Legal pages accepted at 9 clauses each** by Ahmed on 26 August, against a
  specification asking for 18, 16 and 17. `content:verify-legal` guards what he
  accepted and records what the specification wanted.
- **The age question stays a question, not a tick box.** Ahmed suggested a
  "I confirm I am over 18" tick box on 25 August. A tick box can only ever be
  ticked, so it loses the record that someone answered no — on a question
  adjacent to capacity, that record is the protection. He did not press it
  after the mis-click problem was fixed. Reopen if he asks.
- **No email verification code before payment.** Agreed with Ahmed on
  28 August. A code proves somebody can open an inbox; it does not prove they
  are serious, and payment already does that. Put before payment it is a hard
  stop in a journey that promises "free, no account needed" on every screen,
  and it costs real customers to filter out leads that cost nothing while they
  sit in a list. Revisit after launch, placed *after* payment where the
  customer must have a working inbox anyway — or rely on the questionnaire link
  itself, which proves the same thing for free. He expects to revisit in a few
  months.

  The one genuine cost of unverified leads is that the four-hour first-contact
  countdown fires on fake ones and the team chases ghosts. That is fixed by
  handling bounces, not by a code.
- **The "not available to UAE citizens" notice shows on the nationality
  question for every will type** — personal, mirror and DIFC. Already live.
  Confirmed as mandatory by Ahmed on 28 August, so it must never be made
  conditional. The same line goes under the partner nationality field when the
  mirror partner block is built.
- **The Cookie Policy stays unpublished** until the production cookie scan
  confirms the counts it states. One click to publish from Content.
- **The client portal stays off** behind `client_portal_enabled` until Ahmed
  approves that phase in writing.
- **Never build assets on the production host.** It took the site down twice.
  Use `scripts/deploy-assets.sh`.
- **A price change is always a migration, never a seeder edit.** `define()`
  writes a default only when the row does not exist.
