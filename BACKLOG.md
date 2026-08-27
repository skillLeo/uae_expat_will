# Backlog

One list, in the repo, so it survives a lost chat and neither of us has to
reconstruct scope from WhatsApp history. Correct it freely — if something below
is wrong or missing, edit this file and it becomes the truth.

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

---

## Waiting on Ahmed

Nothing here can be built without him.

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
- [ ] **What should DIFC actually do on this site?** Unresolved, and the one
      real gap. On 27 August Ahmed wrote "difc doesnt go questions, it gives
      contact form". That is not what it does: it goes through the whole
      questionnaire and ends on the review screen with no payment, which is how
      it has always behaved here. The DIFC document was another project's, but
      "I specifically want a DIFC Will" is still an option at question one on
      this site, so it has to do *something*. Three ways to settle it: leave it
      as it is, send it to the contact form like options four and five, or take
      the option off question one entirely.
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

## Not started

- [ ] **Mirror wills.** Partner link, one case with one reference and two forms,
      first person pays for both. Spec is complete from Ahmed's answers. Needs
      a look at the case model, because drafts are currently one per case and
      two people must approve separately. 3 to 5 days.
- [ ] **Blog.** Posts with author and review dates, `/blog` and `/blog/{slug}`,
      Article structured data, sitemap entries, admin editing. FAQ overlap
      handled by depth, not duplication: short answer on `/faqs`, long version
      as the post, FAQ links to it. 2 to 3 days.
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

- **The DIFC "Specialist Legal Review Request Flow" document belongs to another
  client's project.** It was implemented here on 26 August and removed on
  27 August. Nothing of it remains: no DIFC request type, no DIFC status, no
  ticket, no DIFC result screen. A DIFC selection behaves as it always has —
  through the questionnaire, ending on the review screen with no payment.
  Routing options four and five to a contact form was *not* from that document;
  it was Ahmed's own instruction on 25 August and is live.
- **Legal pages accepted at 9 clauses each** by Ahmed on 26 August, against a
  specification asking for 18, 16 and 17. `content:verify-legal` guards what he
  accepted and records what the specification wanted.
- **The age question stays a question, not a tick box.** Ahmed suggested a
  "I confirm I am over 18" tick box on 25 August. A tick box can only ever be
  ticked, so it loses the record that someone answered no — on a question
  adjacent to capacity, that record is the protection. He did not press it
  after the mis-click problem was fixed. Reopen if he asks.
- **The Cookie Policy stays unpublished** until the production cookie scan
  confirms the counts it states. One click to publish from Content.
- **The client portal stays off** behind `client_portal_enabled` until Ahmed
  approves that phase in writing.
- **Never build assets on the production host.** It took the site down twice.
  Use `scripts/deploy-assets.sh`.
- **A price change is always a migration, never a seeder edit.** `define()`
  writes a default only when the row does not exist.
