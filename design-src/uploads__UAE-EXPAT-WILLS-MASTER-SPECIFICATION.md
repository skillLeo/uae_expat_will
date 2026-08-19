# UAE EXPAT WILLS — COMPLETE MASTER SPECIFICATION

**Project:** UAE Expat Wills legal-tech platform, Phase One MVP
**Client:** Summit Legal Consultancy FZC — Ahmed Mohammadi Elsayed Mohamed Awad
**Developer:** SkillLeo SMC Pvt Ltd — Hassam, Chief Executive Officer
**Compiled:** 16 August 2026
**Sources:** the signed Agreement (14 pages, countersigned), the 31-file content package of 13 August 2026, the Final Qualifying Questionnaire, and two client voice notes of 16 August 2026

This is the single reference document for the entire engagement. Everything that has been agreed, specified, written or decided is in here. Where two sources disagree, the conflict is recorded in **Part 10** rather than silently resolved.

---

# TABLE OF CONTENTS

1. Engagement foundation — the commercial and contractual frame
2. The business — what Summit is and what this platform is for
3. Document inventory and order of authority
4. The complete end-to-end flow
5. The Qualifying Questionnaire — every question, answer and outcome
6. The routing engine — what has to be built
7. The public website — page by page
8. Platform functionality
9. Compliance, data and security obligations
10. **The contradictions register**
11. Scope reconciliation against the signed contract
12. Design brief
13. Open items and immediate actions

---

# PART 1 — ENGAGEMENT FOUNDATION

## 1.1 The parties

| | Client | Developer |
|---|---|---|
| Entity | Summit Legal Consultancy FZC | SkillLeo SMC Pvt Ltd |
| Signatory | Ahmed Mohammadi Elsayed Mohamed Awad, Manager | Hassam, Chief Executive Officer |
| Licence / Reg. | Licence 4429232.01, formation 4429232 | Registration 0330718 |
| Address | Business Centre, Sharjah Publishing City Free Zone, Sharjah, UAE | Punjab, Pakistan |
| Notice email | info@summitlegaluae.com | contact@skillleo.com |

Ahmed's title varies across sources: **Manager** in the signed contract, **Managing Director and Co-Founder** on the new About Us page, **Executive Director and Co-Founder** on summitlegaluae.com. The contract title governs the agreement; the About Us title governs the website.

## 1.2 Commercial terms — locked, not renegotiable

| Item | Amount | Status |
|---|---|---|
| Base Phase-One MVP | USD 800 | Fixed |
| Questionnaire version control | USD 200 | Fixed, folded in |
| **Total development price** | **USD 1,000** | Final fixed price |
| 90-day bug warranty | Included | No additional charge |
| Optional monthly maintenance | USD 150/month | Not activated without separate written order |
| Client portal (future phase) | USD 300–500 indicative | Non-binding until separately scoped |

Ahmed never negotiated the price down. He folded USD 800 + USD 200 into a single USD 1,000 himself.

## 1.3 Timeline — locked

**7 to 8 weeks** from kickoff to final written acceptance: approximately 5–6 weeks to production-ready platform on Summit-controlled staging, plus **10 business days** final UAT.

Excludes delays caused by late content from Summit, delayed Summit feedback, or third-party approvals (Meta, payment gateway). Any expected delay must be raised **promptly in writing** — rider R3 makes this SkillLeo's obligation, and it only protects if raised at the time, not retrospectively.

## 1.4 The three Fiverr orders

Raised as **three separate orders**, not milestones inside one — rider R1. Each is delivered, reviewed and formally accepted before the next is raised (rider R2).

| Order | Amount | Deliverables | Target |
|---|---|---|---|
| **1** | USD 200 | Approved sitemap and screen inventory · complete customer journeys · mobile and desktop custom UI/UX · **two design revision rounds** · design source files · data-flow and architecture summary · Summit-controlled Git repository and AWS/staging setup | 5–7 business days |
| **2** | USD 400 | Approved public website · both questionnaires · conditional logic · routing and version control · case creation · admin dashboard · roles, permissions, statuses, audit and consent functions on Summit-controlled staging | 10–12 business days |
| **3** | USD 400 | WhatsApp/email · payment integration · analytics/SEO · security checks · backups and restoration test · production deployment · documentation, credentials, training, handover and final UAT support | 10–12 business days + UAT |

**Acceptance:** Summit has 3–5 business days per milestone, 10 business days for final UAT. A milestone is accepted **only by written confirmation**. Defects must be corrected before acceptance. **Silence is not acceptance**, except where a mandatory Fiverr rule overrides.

**Final acceptance requires all of:** no unresolved critical or major defect · complete agreed functionality · responsive operation on approved devices and browsers · successful payment and notification tests · completed security checks · successful backup restoration test · delivery of the handover package.

## 1.5 Technical stack — agreed

| Layer | Choice | Reason given to the client |
|---|---|---|
| Backend | **Laravel (PHP)** | Mature RBAC, protection against common web vulnerabilities, straightforward audit logging, native 2FA, well documented for future maintainers |
| Frontend | **Vue.js** | Handles the multi-step questionnaire and both dashboards cleanly; fast responsive mobile |
| Database | **MySQL** | Relational structure suits case records, roles, permissions, status history, audit trails |
| Notifications | **WhatsApp Business Cloud API** + transactional email | Official Meta API under Summit-owned accounts |
| Payments | **Telr** primary, Network International alternative | Established for UAE cards and wallets |
| Hosting | **AWS Middle East (UAE), me-central-1** | Data residency inside the UAE |

One early Fiverr message mentioned React. Every document since says **Laravel + Vue.js**. Stay consistent.

## 1.6 Infrastructure obligations

All AWS resources created **in Summit's own account from the beginning**, never migrated later. EC2, RDS for MySQL, S3, CloudWatch. **Separate production and staging.** Real customer data **never** copied to staging or development — synthetic or anonymised only. Encryption in transit and at rest for application data, database, storage and backups. **Automated daily backups retained 30 days.** A **documented restoration test** before final acceptance.

Before deployment, SkillLeo must give Summit the intended AWS resource list and estimated production-plus-staging cost for approval.

## 1.7 Third-party recurring costs — Summit pays directly

AWS ~USD 50–75/month · transactional email ~USD 10–20/month · WhatsApp Business API on Meta usage · payment gateway provider charges, approval fees and transaction fees. SSL included at no separate cost. **These are not development fees.**

## 1.8 Contract obligations that constrain the developer

- **Clause A7.3 — no portfolio use.** SkillLeo may not identify Summit, display the project, use screenshots, publish a case study or include the work in a portfolio without prior written consent.
- **Clause A8.2 — termination for convenience.** Summit may terminate at any time on written notice, paying only for deliverables already accepted in writing. Mitigated by the three-order structure.
- **Clauses A9.1 / A9.2 — liability cap with carve-outs.** General cap is total fees paid. The cap does **not** apply to fraud, wilful misconduct, gross negligence, **breach of confidentiality**, IP infringement, **unauthorised use or disclosure of personal data or credentials**, or indemnity obligations.
- **Clause A10.4 — jurisdiction.** UAE law as applied in the Emirate of Dubai. Courts of Dubai have exclusive jurisdiction.
- **Clauses A3.3, A3.5, 9.4 — confidentiality and AI restriction.** Summit's information, content, customer data, prompts, documents and source code may not be used to train, fine-tune or evaluate any AI model, or entered into a public or third-party AI service, without Summit's prior written approval. Confidentiality survives **five years**. Security incident notification within **24 hours** of discovery.
- **Clause 9.4 / rider R5 — named access.** Only Hassam and Saif Ur Rehman. No other person may access source code, AWS or customer data without Summit's prior written approval and an equivalent confidentiality obligation.
- **Clause 17 — communications.** Written progress update **at least weekly**, continuous staging access, a short weekly call when requested.
- **Clause 14 — repository.** Complete source code in a **Summit-controlled Git repository with continuous Summit access from day one.** Ownership of each milestone's custom work transfers to Summit on payment.

## 1.9 Change control — the three categories

| Category | Rule | Charge |
|---|---|---|
| **1** — Clause 13.1 | Corrections needed to make an already-agreed function meet the specification | Included |
| **2** — Clause 13.2 | A small adjustment inside an existing approved page or feature — a label, minor style, message wording, validation tweak — that creates no new feature, page type or integration. In practice, under about an hour | Included |
| **3** — Clause 13.3 + rider R4 | A genuinely new feature, page type or external integration. **Must be described in writing with price and timeline before work begins**, and may only start after Summit's written approval of that specific item | Quoted separately |

**Clause 1.3 cuts both ways:** no agreed MVP item may later be treated as additional scope merely because further implementation detail is required to make it work as described. Equally, SkillLeo cannot claim something is extra just because it turned out harder.

## 1.10 Warranty

**90 days from written final production acceptance.**

Covers: bugs, errors, security defects introduced by the delivered code, and any agreed function that does not work as specified.
Does not cover: new features, changed requirements, third-party outages, changes made by an unauthorised third party.

| Severity | Acknowledge | Correction target |
|---|---|---|
| Critical outage / payment failure / general login failure | 4 business hours | Work same business day; workaround or correction within 24 hours |
| Major functional defect | 1 business day | 1–2 business days |
| Minor visual defect | 2 business days | Next scheduled update, normally within 7 days |

## 1.11 Handover package — contractually required

Complete source code **and commit history** · database schema and data model · design source files · deployment and environment instructions · **administrator guide and training session** · environment-variable list without exposing secrets · API and integration documentation · backup and restoration instructions · user-role and permission matrix · full credentials and transfer of all Summit-owned accounts · basic security-testing report and restoration-test record.

## 1.12 Express exclusions — signed Clause 16

Unless separately agreed in writing, Phase One does **not** include:

1. Automatic will drafting or document generation
2. Artificial-intelligence legal advice or decision-making
3. **Customer account portal or customer-facing dashboard**
4. **Secure customer document upload, storage or document sharing**
5. Direct electronic filing or technical integration with a court, registry or government authority
6. **Automatic appointment booking with an external registration authority**
7. A full accounting, VAT invoicing or tax-reporting system
8. Native iOS or Android applications
9. Translation or entry of future-language content

The MVP architecture must preserve reusable case, customer, payment and status data so a client portal can be added later **without rebuilding the core backend or database**.

---

# PART 2 — THE BUSINESS

## 2.1 Summit Legal Consultancy

A genuinely licensed UAE legal consultancy, not a startup. Eleven practice areas: Debt Recovery & Cheque Cases · Criminal Cases · Crypto & Digital Currency Cases · Corporate & Commercial Law · Real Estate & Rental Disputes · Labor Law · Tax Disputes & Compliance · Family Law & Wills · Compliance & Regulatory · Legal Translation · Business Services. Claimed scale: 1,200+ clients, 15 legal areas, 3 languages.

Firm philosophy, in their own words: *"Document-first approach"* · *"We start from the file, not from the answer"* · *"Prevention before dispute"* · *"no unrealistic promises."*

**Team on summitlegaluae.com:** Dr. Mohamed Raouf (Principal Legal Consultant, PhD in Law), Ahmed Mohammadi (Executive Director & Co-Founder), Sara Zaki (Senior Legal Consultant).
**Team on the new About Us page: only two** — Ahmed Mohammedi (Managing Director and Co-Founder) and Dr. Mohamed Raouf (Principal Legal Consultant and Co-Founder). Sara Zaki is absent.

## 2.2 The two websites — never confuse them

| Site | State | Role |
|---|---|---|
| **uaeexpatwills.com** | Static content/SEO site in AR/EN/RU | **This becomes the platform. The build target.** |
| **summitlegaluae.com** | Parent firm's own site | Separate. Stays as is. Not part of this build. |

Ahmed's own words: *"my 2 websites is not platform, just websites… uaewxpatwills is the project brother."*

## 2.3 Summit's operational advantage

Summit holds direct court-system access (`enotary.moj.gov.ae`). The firm books the official notary appointment, attaches documents, obtains the video-call link and time from the court, and sends the customer one link plus a reminder. The customer attends a single video call with passport, or Emirates ID + UAE Pass if resident. **None of this happens on the platform** — it is entirely off-system and out of scope.

## 2.4 The verified gap this platform closes

Live research on both Summit sites confirmed by direct fetch: **every single call-to-action routes to a WhatsApp deep link (`wa.me/971524666191`). There is no on-site form anywhere on either site.** Every lead currently begins as a blank WhatsApp message, and Summit's team re-asks the same intake questions from zero every time, with nothing captured automatically.

## 2.5 The competitor — expatwill.ae

Not Summit's site. Shared early as a UX reference only. A self-service PDF generator: answer questions, pick a package, pay, download. No legal professional involved. It then only *tells* the customer how to self-register, leaving them to book and wait, often two to three weeks. It contains a **mirror-will flow** where the customer selects a partner and the system emails that partner to complete their own half.

⚠️ **This connects directly to Questionnaire Question 1**, which offers "two separate Wills for myself and my spouse or partner" — the same partner-invite pattern. The outstanding walkthrough of expatwill.ae's mirror flow is now directly build-relevant, not just a trust item.

## 2.6 The positioning argument

| Area | Self-service tools | Summit's platform |
|---|---|---|
| Who guides the customer | A tool only, no legal team | A licensed legal team |
| Choosing the will type | Customer picks a package and pays alone | Screening plus review identify the right route |
| Complex cases | Same standard template, may not hold up | Reviewed so the will actually works |
| Registration | Shows the customer how to self-register, then leaves them | Summit holds court access and handles it |
| End result | A PDF and uncertainty | A properly prepared, legally confirmed will |

## 2.7 Branding requirement — raised by Ahmed unprompted, hard requirement

> *"we need to point that it's a legal company. It's a legal firm… so people they understand there is an actual legal firm behind it."*
> *"Even the email, invoices, everything will come from Summit Legal Consultancy, a licensed legal firm in UAE."*

- Homepage must carry a visible **"Owned and operated by Summit Legal Consultancy UAE"** line
- **Every transactional email under Summit's identity**, not "UAE Expat Wills" alone
- **Every invoice and payment confirmation branded Summit Legal Consultancy**

Note the content package refines this: the approved line is **"Owned and operated by Summit Legal Consultancy UAE"** with Trade Licence No. 4429232.01. The short brand line is **"A Summit Legal Consultancy UAE Platform."** The phrase **"Supported by Summit" must never be used.**

## 2.8 Content ownership — Ahmed writes it

> *"the content of the website, I'm the one who's going to share it to you, because it has to be chosen carefully. The words that you use makes a difference in legal responsibilities… 90% of the content, I will give it to you."*

Contract clauses 2.3 and 2.4: Summit provides legal content, questions, routing rules, disclaimers, consent wording, pricing and customer messages. **SkillLeo must not materially rewrite, interpret or alter Summit's legal content or routing rules without prior written approval.** This protects Hassam — legal wording liability sits with Summit.

## 2.9 The bigger opportunity

Ahmed has repeatedly signalled a much larger second project — a legal-services marketplace — requiring a bigger team on both sides and a funded in-person meeting in either direction. This USD 1,000 project is an audition. Delivery quality matters far beyond the invoice value.

---

# PART 3 — DOCUMENT INVENTORY AND ORDER OF AUTHORITY

## 3.1 Order of authority when documents conflict

Established by contract clause A2.2 and Part B clause 1.2:

1. A later written amendment signed by both parties
2. **Part A** of the signed Agreement
3. **Part B** of the signed Agreement (the scope of work)
4. The Fiverr order, solely for payment and administration mechanics

Everything else — proposals, responses, messages, the content package, the questionnaire — is **background** and does not override the signed Agreement. Where the content package requires something Part B excludes, the content does not automatically win; it becomes a change request.

Within the content package itself, Ahmed's own hierarchy is:

1. `00-website-map-and-pages-FINAL-v4.md` — "the live source of truth" for structure and journey
2. `00-structural-decisions-FINAL-v4.md` — controls wording that must stay consistent across pages
3. Individual page files
4. `publication_pages/` copies — the same text with internal notes stripped

## 3.2 Full inventory

| Document | What it governs |
|---|---|
| **Signed Agreement** (14 pages, countersigned) | Everything commercial, legal and scope-related |
| `00-website-map-and-pages-FINAL-v4.md` | Site structure, navigation, customer journey, functional areas, dashboard, build order, launch scope control |
| `00-structural-decisions-FINAL-v4.md` | 12 numbered decisions, the cross-page consistency register, standing legal obligations, verification cadence |
| `00-CHANGELOG.md` | What changed on 13 August and why |
| `README-FINAL-HANDOFF.md` | Package structure, confirmed commercial model, items to confirm before launch |
| `SHA256SUMS.txt` | Integrity checksums for all 30 content files |
| 13 page files, `-FINAL-v*` | Full page specs with SEO, build notes and pre-publication checks |
| 13 `publication_pages/` copies | Identical text, internal tails removed. **Verified: zero content unique to these.** |
| **Final Qualifying Questionnaire** (.docx) | The assessment — 16 questions, all answer options, all routing outcomes, result screens, mandatory operating rules |
| Two voice notes, 16 August 2026 | Kickoff instruction, change expectation, design request |

## 3.3 The voice notes — verbatim substance

**Voice note 1:** the zip has all the website pages, this one is the questions. "We're going to use the most." There will be a little bit of changes as a final touch later. *"I don't want to delay you anymore."*

**Voice note 2:** *"It will not be even like changes, changes, it's just like the wording… I need to make like two, three times review on the wording because as I told you, it's legal. One word can take the story in a different path… So this is the things, start working. If you have a design in mind, please share it with me. What color you will use, font, this, that, keep me posted."*

**What this means operationally:**

1. **He has given the go-ahead to start.** This is the kickoff signal.
2. **Expected changes are wording only, not structural.** Copy will be revised two or three times for legal precision. Sentence-level edits inside an approved page are Category 2 — included. A structural change is not.
3. **He is asking for a design proposal** — colours and fonts specifically — and wants to be kept posted. That is Milestone 1's first deliverable and he is inviting it.
4. **He is aware he is the timeline dependency** and is trying not to be one.

⚠️ *"Start working"* in a voice note is not the same as Order 1 being raised on Fiverr. Rider R2 and Part B 12.4 tie work to orders. Raise Order 1 first.

---

# PART 4 — THE COMPLETE END-TO-END FLOW

Three entities must stay distinct at every step. The content repeats this on nearly every page:

| Entity | Responsible for |
|---|---|
| **UAE Expat Wills** (platform) | The digital process, preliminary routing, information capture, status display |
| **Summit Legal Consultancy UAE** | Acceptance, legal review, drafting, advice, registration assistance |
| **The Competent Authority** | Eligibility, fees, appointments, notarisation, registration |

## STAGE 0 — PUBLIC SITE (STATELESS)

13 pages. Every page carries: header with `Start Your Free Assessment` and `Client Login`; the ownership line with Trade Licence No. 4429232.01; the footer legal disclaimer.

**Cookie gate fires before any non-essential tag loads.** Accept All · Reject Non-Essential · Manage Preferences, equally prominent, nothing pre-ticked. Store choice, banner version, timestamp and a limited consent identifier. Persistent Cookie Settings footer link for withdrawal.

Assessment CTA appears in exactly three content positions per page plus the persistent header button. Contact page has **no form** — email and WhatsApp only.

## STAGE 1 — FREE QUALIFYING ASSESSMENT

**Gate: none.** No account, no payment, no login. Stated on every page.

**Welcome screen.** Heading: *"Find the right UAE Will route for your circumstances."* Introductory text explains it takes **four to seven minutes depending on answers**, no account or payment needed, and that the platform will **not** ask for passport numbers, Emirates ID numbers, bank details, asset values or supporting documents at this stage. Important notice states the result is preliminary and does not amount to approval by ADJD, Dubai Courts, DIFC Courts or any authority. Button: `Start the assessment`.

**16 questions** with conditional sub-questions. Full logic in Part 5.

**Captured regardless of completion:** source · campaign · progress · **the exact question at which the visitor abandoned**. Contact details may be captured before completion subject to approved consent wording.

**Seven mandatory declaration checkboxes** before the result. None pre-selected. All seven must be actively ticked.

## STAGE 2 — ROUTING DECISION

The questionnaire produces **six terminal states**, not the three the website describes and not the two the questionnaire's own preamble describes. See Part 5.3 and the contradiction at 10.1.

## STAGE 3A — STANDARD: ACCOUNT AND AUTOMATIC ACCEPTANCE

Client provides basic identity and contact information. Case created. **Acceptance confirmed automatically — "there is no separate waiting period before you can pay."** Creating an account does not create a professional engagement.

## STAGE 3B — HELD: DIRECT CONTACT BEFORE PAYMENT

Platform must: create a **preliminary** case · explain that direct contact is needed · request contact and identity information · **take no payment** · notify the Summit team · show a **visible review status** · send a confirmation email · allow Summit to request information, recommend a service or issue a quotation.

Contact fields on the review result screen: Full name · Email address · Telephone number · Nationality · Country of residence · Preferred contact method · Optional summary up to 500 characters. Button: `Send for review`.

## STAGE 3C — REFERRED OUT

Estate administration, under-18, UAE citizen. No payment, neutral explanation, contact route where appropriate, no promise Summit can accept the matter.

## STAGE 4 — ENGAGEMENT TERMS AND PAYMENT

**Mandatory pre-payment display:** the proposed service · the professional fee **and VAT** · what the fee includes · the **amendment allowance** · known material exclusions · separate charge categories with available estimates · links to the Terms and the Payment and Refund Policy.

**Fee:** AED 2,199 plus VAT standard, or the individual DIFC quotation from AED 3,999 plus VAT.

**Must be recorded:** affirmative acceptance of the Terms storing **version, date, time and user identifier**.

Card numbers and security codes must never reach Summit systems.

**⏱ STAGE TIMESTAMP 1 — PAYMENT**

**Couples:** coordinated but never joint. Each person gives and approves their own instructions, receives a separate Will, completes their own registration. Package treatment shown in the Service Confirmation before payment.

## STAGE 5 — DETAILED WILL QUESTIONNAIRE

**Gate: acceptance + engagement + payment.** Explicit rule: *"Do not open the detailed questionnaire before acceptance, engagement and payment."*

Content areas: personal and identification details · spouse or partner · children and dependants · beneficiaries and substitutes · intended estate-distribution instructions · executor and alternate · permanent, interim and alternative guardians · relevant asset information · existing UAE and foreign Wills · special wishes requiring review.

Requirements: conditional sections · required and optional fields · validation · progress indication · **save and return** · review answers before submission · document upload including **camera capture on mobile** · automatic confirmation to the client and notification to the assigned team member on submission.

**Validation rule with legal weight:** *"If distribution percentages or instructions are mathematically or legally unclear, we may pause the work until they are corrected or reviewed."* Percentage validation is a legal requirement here.

## STAGE 6 — HUMAN LEGAL REVIEW AND DRAFTING

**⏱ STAGE TIMESTAMP 2 — SUBSTANTIVE PROFESSIONAL WORK BEGINS**

Defined precisely because it drives refunds: reviewing the detailed questionnaire or documents, analysing the route, identifying legal issues, preparing tailored advice, drafting or revising, or agreed registration preparation. **Explicitly excludes** automated account creation, issuing a receipt and ordinary payment administration.

The reviewer checks: whether required information appears complete · apparent inconsistencies between answers · beneficiaries, executors, guardians and alternatives · the applicable rules, including for a Muslim client **the one-third and heir-consent rules** · **which authority is suitable, ADJD or Dubai Courts** · whether clarification or documents are needed · matters needing separate advice · preparation of the draft within scope.

**Mandatory post-questionnaire review rule from the questionnaire document:** Summit must review the customer's religion, family circumstances, assets, ownership, previous Wills, distribution wishes, guardianship arrangements and any international connection **before confirming ADJD, Dubai Courts, DIFC or another service.**

| Branch | Action |
|---|---|
| Information missing | Status **"Further Information Required"**; drafting resumes after receipt and review |
| Straightforward | Draft produced, target **2 business days** from complete usable instructions **and** all required documents |
| Needs specialist analysis | Longer, and Summit must tell the client |
| Additional work / materially different service | **Pause**, explain scope and fee, obtain express approval before charging |
| **DIFC identified post-payment** | The re-route sub-flow below |

### 4.1 DIFC re-route sub-flow

1. Summit pauses the matter
2. Explains why and provides the DIFC quotation
3. **No DIFC work and no additional charge without express client approval**
4. Client **proceeds** → the **full** standard fee already paid is **credited** against the agreed DIFC fee
5. Client **declines** → refund the unused balance after deducting **only a reasonable, documented amount for substantive work already completed**, calculation explainable on request

## STAGE 7 — DRAFT REVIEW, AMENDMENTS, APPROVAL

**⏱ STAGE TIMESTAMP 3 — FIRST TAILORED DRAFT DELIVERED**

Client checks names and identification details · beneficiaries and substitutes · distribution instructions · executors and alternatives · guardians and alternatives · asset descriptions · special instructions.

Amendments within the allowance stated in the Service Confirmation. A change that expands scope, creates new complexity or requires substantial redrafting needs separate review and a revised quotation. **No document proceeds to registration preparation without clear personal approval.** Each spouse approves their own separate Will.

If the client approves a document containing an error they could reasonably have identified, correction work may be chargeable.

**⏱ STAGE TIMESTAMP 4 — FINAL APPROVAL**

## STAGE 8 — AUTHORITY REGISTRATION ASSISTANCE

**⏱ STAGE TIMESTAMP 5 — THIRD-PARTY COMMITMENT / AUTHORITY SUBMISSION AUTHORISED**

**Summit does, inside the AED 2,199:** explains required documents · prepares the document for the applicable process · explains submission and appointment steps · prepares the client for an appointment or video meeting · **explains expected authority fees before the client authorises submission or payment** · responds to procedural comments within scope · tracks the stage where information is available.

**Client does personally:** UAE Pass authentication · identity verification · signing · attendance · video interview · payment of authority fees.

**Authority controls:** eligibility · document requirements · appointment availability · corrections · fees · acceptance · rejection · notarisation · registration.

## STAGE 9 — COMPLETION AND AFTERCARE

Client retains the final registered or notarised document. Within scope Summit may explain what was issued, whether a step remains, and when to review.

Review triggers: marriage, divorce or separation · birth or adoption · a child reaching adulthood · death, incapacity or relocation of a beneficiary, executor or guardian · purchase or sale of significant property · starting, selling or restructuring a business · significant change in asset value or location · moving country · acquiring another nationality · making or changing another Will · relevant change in law or authority rules.

**Never allow informal handwritten changes to a registered Will.**

## 4.2 Refund state machine — driven entirely by the five timestamps

| Stage reached | Refund position |
|---|---|
| **A.** Before substantive work begins | Refund the professional fee. Only a genuinely non-refundable, clearly pre-disclosed, lawfully retainable processor charge may be deducted. **No internal "administration fee" deduction permitted.** |
| **B.** After substantive work, before first draft | Refund the unused portion. Retained amount must reflect documented work actually completed, not the whole fee. Calculation explainable on request. |
| **C.** After first draft delivered | Assessment, review and drafting portion normally non-refundable. Unused separately identifiable later-stage fee — unused amendments, registration assistance not yet given — may be refundable. |
| **D.** After approval, submission or registration work | Completed work non-refundable. Unused separately identifiable future work assessed fairly. Third-party charges governed by the authority's own rules. |

**Consequence:** the AED 2,199 must be **internally apportioned** across review, first draft, amendments and registration assistance, or band C cannot be computed. Summit's own pre-publication check requires the back office to calculate an unused portion consistently.

Additional paths: Summit cannot provide the service → correct / re-perform / alternative / refund · duplicate or unauthorised payment · chargeback with transaction, acceptance and service records available · client inactivity → hold after reasonable notice → closure → assessed under the table above.


---

# PART 5 — THE QUALIFYING QUESTIONNAIRE, COMPLETE

**Source:** `UAE-Expat-Wills-Final_Qualifying_Questionnaire.docx`, "Final English version for the website journey".

## 5.1 Stated purpose

The assessment decides whether a customer may continue to the standard online service or whether Summit should review the matter before the customer pays. It does **not** select the final registration authority, create a Will, provide a final legal opinion or guarantee acceptance by any authority. Every accepted instruction is reviewed by the Summit legal team before the route is confirmed and before a draft is released.

## 5.2 Answer outcome vocabulary

Every answer resolves to one of these. The engine needs all of them as distinct states:

| Outcome | Meaning |
|---|---|
| **CONTINUE** | Proceed to the next question |
| **CONTINUE + FLAG** | Proceed, but tag the case so the legal reviewer sees it after payment |
| **CONTINUE + REMINDER** | Proceed, with a message that something must be supplied in the detailed questionnaire |
| **CONTINUE + ROUTE MARK** | Proceed, but mark the matter for the wider Dubai drafting route |
| **REVIEW** | Send to Summit for review before payment. No payment offered |
| **URGENT REVIEW** | Stop the automated journey, restricted internal alert, no payment |
| **STOP — REFER** | End the Will assessment, refer as a different legal service |
| **STOP — INELIGIBLE** | End; no service available through this platform |

## 5.3 The six terminal states

1. **Result 1 — may continue online** (standard pathway)
2. **Result 2 — review before payment** (Summit contacts the customer)
3. **Estate administration referral** (Q1: someone has died)
4. **Hard stop, under 18** (Q2)
5. **Hard stop, UAE citizen** (Q3)
6. **Urgent independent review** (Q15B: capacity or undue influence — restricted alert, special confidentiality handling)

## 5.4 Question-by-question logic

### Q1 — Service required (select one)
*"What service are you looking for today?"*

| Answer | Outcome |
|---|---|
| Prepare a new Will for myself | CONTINUE |
| Two separate Wills for myself and my spouse or partner | CONTINUE — each person answers separately and approves their own Will |
| Specifically want a DIFC Will | REVIEW |
| Review, amend, replace or revoke an existing Will | REVIEW |
| Someone has died and I need help with their estate | STOP — REFER as estate administration enquiry |

**Message for two Wills:** *"Each person must provide their own instructions and approve their own Will separately. We will ask each of you to complete your information and personal approval."*
**Message for an estate enquiry:** *"This relates to the administration of an estate after death, rather than the preparation of a new Will. Our team will need to review the matter as a separate legal service."*

### Q2 — Age (select one)
*"Are you 18 years old or above?"*
Yes → CONTINUE. No → STOP — INELIGIBLE.
**Message:** *"You cannot continue through the online Will preparation service because the person making the Will must be at least 18 years old."*

### Q3 — Nationality
**Question type: searchable dropdown containing all countries.**
Notice on the same screen: *"The Will services available through this platform are not available to UAE citizens."*
United Arab Emirates → STOP — INELIGIBLE, do not offer payment. Any other nationality → CONTINUE.
**Message:** *"The Will services available through this platform are not intended for UAE citizens. You may contact Summit if you require a different legal service."*
Plus: *"Nationality does not determine the registration authority by itself. Any second nationality, domicile or legal connection with another country will be collected later and reviewed by Summit."*

### Q4 — Current residence (select one)
In the UAE / Outside the UAE — both CONTINUE. Country of residence collected later if outside.
**Conditional:** if "In the United Arab Emirates" → *"Which Emirate do you live in?"* Abu Dhabi · Dubai · Sharjah · Ajman · Umm Al Quwain · Ras Al Khaimah · Fujairah.
Note: the Emirate is relevant but does not select the registration authority by itself.

### Q5 — Religion (select one)
**Privacy explanation displayed:** *"Religion affects which registration routes may be available. The Dubai Courts route for non-Muslim Wills is not available to Muslims, while an ADJD Civil Will may be available to Muslim and non-Muslim applicants who are not UAE citizens. We use this answer only to assess the route and handle it in accordance with our Privacy Policy."*

| Answer | Outcome |
|---|---|
| Muslim | CONTINUE — possible standard route is ADJD, subject to review |
| Non-Muslim | CONTINUE — ADJD or Dubai Courts may be considered |
| I was previously Muslim | REVIEW |

Reassurance: a Muslim customer is not automatically rejected.

⚠️ **There is no "prefer not to say" option.** See contradiction 10.3.

### Q6 — Legal marital status (select one)

| Answer | Outcome |
|---|---|
| Never been married | CONTINUE |
| Married, first and only marriage | CONTINUE |
| Living with a partner or engaged, not married | → Q6A |
| Widowed, no unfinished estate or financial right | → Q6B |
| Married and was married before | REVIEW |
| Divorced | REVIEW |
| Separated or divorce proceedings ongoing | REVIEW |
| Prenuptial/postnuptial agreement, settlement or claim between spouses | REVIEW |
| Position is different or unclear | REVIEW |

**Q6A — unmarried partner.** *"Do you want your unmarried partner to receive part of your estate?"*
No → CONTINUE. Yes, no existing marriage or competing claim → **non-Muslim: CONTINUE + ROUTE MARK (wider drafting route); Muslim: REVIEW.** Yes, with existing marriage/obligation/competing claim → REVIEW. Not sure → REVIEW.

**Q6B — widowed.** *"Is there an unfinished estate, Will, jointly owned asset or financial right connected with your late spouse?"* No → CONTINUE. Yes → REVIEW. Not sure → REVIEW.

### Q7 — Children and dependants (select all; "no children" exclusive)

| Answer | Outcome |
|---|---|
| No children, nobody financially dependent | CONTINUE |
| All children from current marriage or relationship | CONTINUE |
| Adult children only | CONTINUE |
| A child is expected | CONTINUE + collect further information later |
| An adult depends on me financially, no disability or special care needs | CONTINUE |
| Children from more than one marriage or relationship | REVIEW |
| Stepchildren, adopted children, children under kafala or foster children | REVIEW |
| One of my children has died and left children | REVIEW |
| A dependant has a disability or long-term care needs | REVIEW |
| Not sure about a child's legal status | REVIEW |

**Message where a child is expected:** *"We will collect additional information later so that the legal team can review how an expected or future child should be addressed in the Will."*

### Q8 — Minor children (show only if children indicated)

**Q8A — age and guardianship.** *"Is any child under 18, or does anyone require a legal guardianship arrangement?"* No → CONTINUE. Yes → ask Q8B, Q8C, Q8D. Not sure → REVIEW.

**Q8B — where the children live.** All in the UAE → CONTINUE. All outside the UAE → REVIEW. Some in, some out → REVIEW. Not sure → REVIEW.

**Q8C — legal responsibility and disputes.** *"Are you the legal parent of every child, with no existing or expected dispute concerning parentage, custody, guardianship, travel or parental authority?"* Yes → CONTINUE. No, there is or may be a dispute, judgment or restriction → REVIEW. Not sure → REVIEW.

**Q8D — ability to nominate a guardian.** Help text: *"A nomination in a Will records your wishes. It does not remove the rights of the other parent or the authority of the court. The child's welfare and the applicable law remain decisive."*
Yes, no dispute expected → CONTINUE. Not selected yet but can during the detailed questionnaire → CONTINUE + REMINDER. No suitable person → REVIEW. There may be a dispute → REVIEW. Not sure → REVIEW.

### Q9 — Location of assets (select one)

| Answer | Outcome |
|---|---|
| In the UAE only | CONTINUE |
| No assets, but want guardianship wishes for minor children living in the UAE | CONTINUE + guardianship note |
| In the UAE and in another country | REVIEW |
| Outside the UAE only | REVIEW |
| Not sure | REVIEW |

### Q10 — Types of UAE assets (select all; skip if guardianship only)

| Answer | Outcome |
|---|---|
| Bank accounts, deposits or cash | CONTINUE |
| Real estate | CONTINUE |
| Listed shares or ordinary investment portfolios | CONTINUE |
| Vehicles, jewellery or personal possessions | CONTINUE |
| End-of-service benefits or employment entitlements | CONTINUE |
| Insurance policy, account or asset with a registered beneficiary | → Q10A |
| Cryptocurrency or another digital asset | → Q10B |
| A business, private company or share in a private company | REVIEW |
| Asset owned through a Trust, Foundation or company, or registered in another person's name | REVIEW |
| Intellectual property, royalties or licensing rights | REVIEW |
| Another asset type, or not sure | REVIEW |

**Q10A — registered beneficiary.** Matches my wishes → CONTINUE + FLAG for confirmation during legal review. I want a different result in my Will → REVIEW. I do not understand the effect → REVIEW.
**Important notice:** *"Some assets may pass under the registered nomination or the provider's contractual terms rather than under the Will alone. Summit must check for a conflict before drafting."*

**Q10B — digital assets.** Part of the residue, no special instructions → CONTINUE + FLAG. Specific wallet to a specific person or special access instructions → REVIEW. Multiple owners, platforms, wallets or complicated arrangements → REVIEW. Not sure → REVIEW.
**Security notice:** *"The platform must never request or store passwords, seed phrases, recovery phrases or private keys."*

### Q11 — Ownership and restrictions (select all)
Help text: *"An ordinary bank loan or mortgage does not make a case complicated by itself."*

| Answer | Outcome |
|---|---|
| All assets owned by me and registered in my name, even with an ordinary loan or mortgage | CONTINUE |
| Jointly owned, share clear and documented, Will addresses my share only | CONTINUE + joint ownership notice |
| My share in a jointly owned asset is unclear or undocumented | REVIEW |
| Asset registered in the name of a company or another person | REVIEW |
| Asset owned through a Trust or Foundation | REVIEW |
| Asset disputed, attached, subject to third-party rights or restricted from transfer | REVIEW |
| Not sure about my ownership or share | REVIEW |

**Joint ownership notice:** *"A Will can address only the customer's legal share in a jointly owned asset, not the entire asset. Summit will review the ownership information before drafting."*

### Q12 — Previous Wills and arrangements (select all; "None of these" exclusive)
None of these → CONTINUE. **Every other option → REVIEW:** a Will or draft Will in the UAE · a Will or draft Will outside the UAE · previously selected the law of a particular country to govern the estate · a marriage, family, shareholder or partnership agreement · an incomplete gift or transfer of ownership · a Trust, Foundation or company owns an asset · another arrangement for an asset to pass on death · not sure.

### Q13 — Intended distribution

**Q13A — general distribution wishes (select all)**

| Answer | Outcome |
|---|---|
| Everything to my spouse, my children or divided between them | CONTINUE |
| Simple fixed percentages between named people | CONTINUE |
| A particular asset or amount to a named person, balance distributed afterwards | **non-Muslim: CONTINUE + ROUTE MARK; Muslim: REVIEW** |
| Different percentages between my children | **non-Muslim: CONTINUE + ROUTE MARK; Muslim: REVIEW** |
| A simple gift to a friend or relative | **non-Muslim: CONTINUE + ROUTE MARK; Muslim: REVIEW** |
| A gift to a charity, foundation or institution | REVIEW |
| I know what I want and will enter names and percentages later | CONTINUE |
| Not decided, need legal advice about distribution | REVIEW |
| Want to exclude someone who may expect to inherit | REVIEW |
| Want to impose conditions on when or how a beneficiary receives an inheritance | REVIEW |
| Want an arrangement involving a Trust, Foundation or company | REVIEW |
| Another wish not listed | REVIEW |

**Governing rule:** *"If an instruction appears to require a feature available only through the Dubai Courts route and the customer is Muslim, the matter must be reviewed before payment because the Dubai Courts non-Muslim Will route is not available to a Muslim customer."*

**Q13B — beneficiaries requiring protection**
No → CONTINUE. Beneficiary is a minor with no other special needs → CONTINUE + FLAG for legal review after payment. Beneficiary has a disability or long-term care needs → REVIEW. May not be able to manage money, or financial/personal concerns → REVIEW. Want to delay or stage the inheritance → REVIEW. Another protective arrangement required → REVIEW. Not sure → REVIEW.
**Hard rule:** *"The platform must not state that a minor's inheritance is automatically held until the age of 21."*

### Q14 — Executor (select one)
Help text: *"We do not need the person's name during the initial assessment. We only need to know whether the role can be arranged clearly."*

| Answer | Outcome |
|---|---|
| Can nominate a suitable adult and a substitute, no dispute expected | CONTINUE |
| Have a suitable person but no substitute yet | CONTINUE + REMINDER |
| Have not chosen names yet, can do so during the detailed questionnaire | CONTINUE + REMINDER |
| Want to appoint a company, institution or professional executor | REVIEW |
| Want several executors to act together | REVIEW |
| There may be a conflict of interest or dispute about the appointment | REVIEW |
| There is no suitable person | REVIEW |
| Not sure about the role or the right person | REVIEW |

### Q15 — Debts, disputes and free will

**Q15A — debts, disputes and special circumstances (select all; "None of these" exclusive)**
Help text: *"An ordinary bank loan, mortgage or credit card does not create a complication by itself unless there is a dispute, personal guarantee or insolvency concern."*

None of these → CONTINUE. Only an ordinary performing bank loan, mortgage or credit card → CONTINUE.
**REVIEW:** family dispute concerning money, ownership or inheritance · court case or enforcement proceeding concerning family, custody or ownership · disputed debt, personal guarantee or material business debt · risk of insolvency or bankruptcy · material obligations or debts outside the UAE · want to forgive a debt owed to me · possible family or financial claim against my estate · urgent deadline or serious health circumstance · not sure about the effect of a debt or dispute.

**Q15B — capacity and freedom of decision**
*"Do you understand the nature and effect of a Will and are you making your decisions freely, without pressure from another person?"*

Yes → CONTINUE. **Every other answer → URGENT REVIEW:** a health or personal condition may affect ability to understand or decide · another person is helping me and may influence my choices · I feel pressured or influenced · no, or not sure.

⚠️ **Special handling, and this is a genuine security requirement:** *"The platform must not request payment and must create a restricted internal alert for the Summit legal team. The answer must not be disclosed to the person who may be influencing the customer."* This means a **restricted-visibility case flag** whose content is excluded from ordinary notifications, ordinary staff views, and any communication the customer might read in another person's presence.

### Q16 — Language and assistance (select one)
*"This answer does not, by itself, make the case legally complex."*
Yes, can complete in English → CONTINUE. Yes but may need a simple explanation → CONTINUE + mark assistance may be required. Need assistance in Arabic → CONTINUE + mark Arabic assistance required. Need an interpreter or another language → CONTINUE + arrange contact from the team.

**Assistance message:** *"The website is currently available in English. Our team can assist in Arabic and will explain whether an interpreter or additional translation is required by the chosen registration authority. Asking for language assistance does not mean that your case is legally complex."*

## 5.5 Final declarations — seven mandatory checkboxes

**The customer must actively select all seven. None may be pre-selected.**

1. I confirm that my answers are true and complete to the best of my knowledge.
2. I understand that the assessment result is preliminary and is not a final legal opinion or a decision by a registration authority.
3. I understand that completing the assessment does not create or register a Will.
4. I understand that the registration authority will not be confirmed until I complete the detailed questionnaire and Summit reviews my information.
5. I understand that paying Summit's fees or receiving a draft does not mean that the Will has been registered.
6. I understand that government, court, notary and third-party fees are separate, and that the legal translation of the new Will for the recommended standard route is included in Summit's professional fee.
7. I agree that my answers may be used for assessment and service delivery in accordance with the Privacy Policy.

## 5.6 Result 1 — may continue online

**Heading:** *"You may continue through our online service"*

**Text:** *"Your answers indicate that your circumstances may be suitable for our standard online UAE Will service. After payment and completion of the detailed Will questionnaire, Summit Legal Consultancy will review all of your instructions and confirm the appropriate registration authority. Depending on your religion, your instructions and the authority's requirements, the recommended route may be ADJD or Dubai Courts. Every draft is subject to human legal review before it is sent to you. This result is not final acceptance by any authority and does not mean that your Will has been prepared or registered."*

**Primary button:** `View the service and fees`
**Secondary button:** `I have a question before continuing`

**Additional message, Muslim customer:** *"Based on your answers, the ADJD Civil Will route may be available. Summit will confirm this after reviewing your complete information."*
**Additional message, non-Muslim customer:** *"Based on your answers, registration through ADJD or Dubai Courts may be available. Summit will recommend the route that best matches your instructions after review."*

## 5.7 Result 2 — review before payment

**Heading:** *"We would like our legal team to review your circumstances first"*

**Text:** *"One or more of your answers indicates that your circumstances should be reviewed before we recommend a service, registration authority or price. This is not a rejection. A DIFC Will, a customised Will, coordination with a foreign Will or foreign law, or another legal service may be more suitable. Please leave your contact details and a short summary. Summit will review the matter. No payment is required at this stage."*

**Contact fields:** Full name · Email address · Telephone number · Nationality · Country of residence · Preferred contact method · Optional summary up to 500 characters.
**Button:** `Send for review`

## 5.8 Price and service screen before payment

**Heading:** *"UAE Will preparation with human legal review"*
**Standard professional fee:** AED 2,199 plus VAT for one accepted standard Will instruction.

**Includes:** collection of instructions through the detailed questionnaire · preparation of the new Will · human legal review by the Summit team · legal translation of the new Will required for the recommended standard court route · the number of amendments stated in the Service Confirmation · assistance preparing and submitting the Will to the recommended court or authority.

**Not included:** court, registry, government or notary fees · identity verification, courier or other third-party fees · translation, certification, attestation or legalisation of supporting or foreign documents unless expressly included · advice on foreign law, tax, Trusts, Foundations, company structures, disputes or services outside the agreed scope.

**Current external fee indications:** ADJD ordinary Civil Will currently AED 950 for one Will · Dubai Courts Will currently approximately AED 2,100 for one Will. Both subject to current authority fees and procedures.

*"The customer pays Summit's professional fee at this stage. After the customer completes the detailed questionnaire, Summit reviews the instructions, recommends the appropriate registration route and explains the applicable government fee before registration begins."*

DIFC: reviewed before payment; professional fees from AED 3,999 plus VAT; DIFC Courts fees and external charges separate.

## 5.9 Mandatory operating rules — eleven hard constraints

1. Show **one decision or question per screen**
2. Display conditional questions **only when relevant**
3. Allow the customer to **go back and change an answer**
4. **Do not promise a fixed number of questions** — the number depends on the answers
5. **Do not ask the customer to select ADJD or Dubai Courts** during the assessment
6. Do not state that the platform has made a final legal decision
7. **Do not generate a final registration recommendation automatically**
8. **Do not request payment from a case requiring review before payment**
9. **Do not release a draft without Summit's human legal review**
10. Do not state that a draft is a registered or enforceable Will
11. **Do not request passwords, seed phrases, private keys or full payment-card details**

---

# PART 6 — THE ROUTING ENGINE

What Part 5 requires the engine to support. This is the specification for the USD 200 version-control module plus the routing logic.

## 6.1 Rule types needed

| Type | Example | Complexity |
|---|---|---|
| **Flat answer → outcome** | Q2 "No" → STOP | Simple lookup |
| **Answer → conditional branch** | Q4 "In the UAE" → show Emirate question | Show/hide dependency |
| **Answer → sub-question set** | Q8A "Yes" → ask Q8B, Q8C, Q8D | Grouped dependency |
| **Answer → outcome, conditional on an earlier answer** | Q13A "different percentages between my children" → CONTINUE if Q5 = Non-Muslim, REVIEW if Q5 = Muslim | **Cross-question rule referencing a stored value** |
| **Section skip** | Q10 skipped entirely if the request is guardianship-only (from Q9) | Conditional section suppression |
| **Exclusive option in a multi-select** | Q7, Q12, Q15A — "None of these" cannot combine | Client and server validation |
| **Aggregation** | Any single REVIEW anywhere overrides all CONTINUEs | Precedence resolution |
| **Terminal override** | Q2, Q3 and Q1-estate end the assessment immediately | Early termination |

⚠️ **The cross-question religion-conditional rule is the hard one.** Q6A and Q13A both change outcome based on the answer to Q5. A flat rules table cannot express this. The engine needs conditions of the form `IF answer(Q13A) = X AND answer(Q5) = Muslim THEN REVIEW ELSE CONTINUE+ROUTE_MARK`. This must be configurable from the dashboard without code changes, per signed Part B clause 5.

## 6.2 Outcome precedence

Resolve in this order, first match wins:

1. **STOP — INELIGIBLE** (Q2 under 18, Q3 UAE citizen) — terminate immediately, no payment, no review path
2. **STOP — REFER** (Q1 someone has died) — terminate, refer as estate administration
3. **URGENT REVIEW** (Q15B) — terminate the automated journey, restricted alert, no payment
4. **REVIEW** — any single REVIEW answer anywhere sends the whole case to Result 2
5. **CONTINUE** with accumulated FLAGS, REMINDERS and ROUTE MARKS → Result 1

## 6.3 Data the case record must carry out of the assessment

Every answer given · the resolved outcome · **every trigger reason, itemised by question** (signed Part B requires "trigger reasons" in the case detail) · every FLAG for the legal reviewer · every ROUTE MARK · every REMINDER owed in the detailed questionnaire · the religion value, held under sensitive-data controls · language-assistance marks · lead source and campaign · the exact abandonment question if incomplete · consent records for the seven declarations · timestamp.

## 6.4 Version control requirements — signed Part B clause 5

Create, edit, add, remove and reorder questions · required and optional questions · conditional dependencies between answers and later questions · **edit routing rules, outcome categories and consultation triggers without changing source code** · **preview and test before publication** · **maintain draft and published versions** · **require an authorised publish action** · **record administrator, date and time for every change** · **view previous versions and roll back** · configure the incomplete-questionnaire retention period, initially 30 days.

Given Ahmed's voice note — *"I need to make like two, three times review on the wording"* — the preview-and-publish workflow is not a nice-to-have. He will use it repeatedly.


---

# PART 7 — THE PUBLIC WEBSITE, PAGE BY PAGE

## 7.0 Global elements

**Header:** logo linking to `/` · How It Works · Do You Need a Will? · UAE Will Options · Pricing · FAQs · About Us · Contact · primary button `Start Your Free Assessment` · utility link `Client Login`.
No Home menu item, no mega-menu, no dropdown, no language selector at launch.

**Mobile header:** logo · Client Login icon or text link · menu button · persistent but unobtrusive Start Assessment button. Same seven links in the menu.

**Footer:** short platform description · "Owned and operated by Summit Legal Consultancy UAE" · main page links **including Client Login** · legal-policy links **plus Cookie Settings** · contact email and WhatsApp · authority and legal disclaimer · copyright.

**Footer ownership line, exact:** *"Ownership: UAE Expat Wills is owned and operated by Summit Legal Consultancy UAE · Trade Licence No. 4429232.01. UAE Expat Wills and Summit Legal Consultancy UAE are not a court, registry, notary or government authority. Registration is completed only by the competent authority under its current requirements."*

**Address:** not displayed anywhere until the registered address and walk-in status are confirmed.

**URL convention:** flat, keyword-bearing. No `/services/` prefix.

## 7.1 Home — `/`

**SEO title:** Prepare a UAE Will Online With Legal Review | UAE Expat Wills
**Meta:** Prepare your UAE Will through a guided online process with human legal review and registration assistance from Summit Legal Consultancy UAE. Start free.
**Structured data:** `Organization` · `WebSite` · `Service`

**H1:** Prepare Your UAE Will Online With Legal Support

**Eight content sections:** 1 Hero · 2 Why a Will may matter and who should consider one · 3 How the service works · 4 Why UAE Expat Wills · 5 Two service pathways · 6 UAE Will options · 7 Common questions · 8 Final call to action.
The trust strip, header and footer are global components, **not** content sections.

**Trust strip:** A Summit Legal Consultancy UAE platform · Human legal review on every Will · One clear professional fee · Registration assistance.

**Starting-fee line:** free initial assessment · no account required to begin · professional fee for one accepted standard Will **AED 2,199 plus VAT**, including Will preparation, human legal review, certified legal translation of the new UAE Will and submission assistance · government, court, registry, notary and other third-party charges separate. **Link "AED 2,199 plus VAT" to `/pricing`.**

**Section 1 — four reason cards:** Who Receives Your Eligible Assets · Who Administers Your Estate · Who May Care for Your Children · What Happens if a First Choice Cannot Act. Followed by an eight-item "who should consider" list.

**Section 4 — two pathway cards:** Standard Online Pathway (applies to almost everyone) and DIFC and Sensitive-Matter Pathway. Note beneath: *"The customer does not choose between these pathways. Both begin with the same assessment."*

**Section 6 — five common questions** kept visible in the page HTML.

**Assessment button in exactly three content positions:** hero · after the two pathway cards · final call to action. The header button is a global control and is not counted.

**Constraints:** no wide comparison table on the homepage · live text for every legal limitation · visible notices never inside collapsed accordions · header and sticky controls must not overlap cookie or WhatsApp controls · no legal meaning communicated by colour alone.

## 7.2 How It Works — `/how-it-works`

**SEO title:** How the UAE Will Process Works | UAE Expat Wills
**Structured data:** `BreadcrumbList` · `Service`
**H1:** How the UAE Expat Wills Process Works

**13 sections.** A vertical nine-step journey with visible anchors. A three-column responsibilities table — *UAE Expat Wills and Summit* / *You, the Client* / *The Competent Authority*, seven rows — rendered as labelled three-part cards on mobile with no horizontal scrolling. Eight FAQ items.

**Required page behaviour:** display "no payment while held for review" **only** for the three narrow categories in Steps 3 and 4 · **do not open the detailed questionnaire before acceptance, engagement and payment** · keep all authority limitations visible, not in accordions · one clear next action at every application status · display the 2-business-day draft target **only alongside the authority-timing caveat**, never standing alone.

## 7.3 Do You Need a UAE Will — `/do-you-need-a-uae-will`

**H1 area covers:** why a UAE Will may matter · what may happen without a Will · who should consider one · what a Will can and cannot do · parents and guardianship · Muslim and non-Muslim considerations · foreign Wills and UAE assets · a draft is not a registered Will · when to review or update · FAQs.

**12 sections. Eight customer-profile cards:** parents of minor children · UAE property owners · bank-account holders and investors · married couples · business owners and shareholders · non-resident UAE asset owners · blended families or non-standard beneficiaries · people with foreign Wills or assets abroad. Seven FAQ items.

**Key legal content:** the non-Muslim civil default — where the federal civil personal-status framework applies and the deceased leaves a spouse and children, the **surviving spouse receives one half and the other half is divided equally among the children without a male-female distinction**. Muslim estates require separate analysis under the one-third rule.

Ends with the assessment button. Does not attempt eligibility itself.

## 7.4 UAE Will Options — `/uae-will-registration-options`

**SEO title:** UAE Will Options: DIFC, ADJD and Dubai Courts Compared
**Structured data:** `BreadcrumbList` · `Article`
**H1:** UAE Will Options: DIFC, Abu Dhabi and Dubai Courts

**The only launch-level route comparison page.** 12 sections with on-page anchor navigation. A five-route comparison table converted to **five labelled cards on mobile**. Six client scenarios. Seven FAQ items. Nine official source links.

**Rules:** do not create or link to separate DIFC, ADJD, Dubai Courts, Muslim, non-Muslim or foreign-Will guides at launch · do not show "Detailed Guide" links · **do not place a separate CTA inside every route section** · keep primary official citations in the visible article · do not insert unverified authority fees or processing times.

## 7.5 Pricing — `/pricing`

**SEO title:** UAE Will Cost and Service Fees | UAE Expat Wills
**Structured data:** `WebPage` · `Service` · `Offer` · `BreadcrumbList`
**H1:** UAE Will Service Pricing

Seven sections, visually separating: standard pathway AED 2,199 plus VAT with ten inclusions and eight exclusions · DIFC from AED 3,999 plus VAT quoted individually · VAT · the authority fee table · amendment allowance · refund and cancellation rules linked to the policy.

**Authority fee table:** ADJD Civil Will AED 950 for one regular Will · Dubai Courts Will approximately AED 2,100 for one Will · DIFC Courts Will varies by Will type.

**Build note:** *"Checkout must display VAT, included work, the amendment allowance and separate-charge categories before the Client commits."*
**Rule:** DIFC clients are never shown a misleading fixed price online.

## 7.6 FAQs — `/faqs`

**57 questions in 8 categories:** The basics and who may need a Will (9) · Registration routes and eligibility (10) · Property, money, business and digital assets (5) · Children, families and executors (7) · Foreign Wills and international estates (5) · How UAE Expat Wills works (8) · Pricing and payment (6) · Registration, probate and aftercare (7).

**Page behaviour:** eight category accordions with visible jump navigation · each question a descriptive heading containing an accessible accordion button · `aria-expanded`, `aria-controls`, keyboard operable · **every answer present in the server-rendered HTML — collapsing must not remove it from the document source** · stable section and question anchors with an optional "Copy link" control · client-side filtering that generates **no indexable internal-search URLs** · a "No matching question — start the assessment or contact us" fallback · **no pagination and no separate thin pages per question**.

**Search placeholder:** "Search UAE Will questions".
**Assessment CTAs: three placements only** — hero, after the registration-routes category, final section.

⚠️ **`FAQPage` structured data is explicitly forbidden.** Summit's stated reason: Google removed the FAQ rich-result feature in 2026. Use `Organization`, `Service`/`WebPage`/`Article`, `BreadcrumbList` instead.

⚠️ **A superseded 50-question FAQ exists.** The changelog says: retire it, do not publish it. Ensure it never surfaces.

## 7.7 About Us — `/about-us`

**Structured data:** `AboutPage` · `Organization` · `Person` · `BreadcrumbList`
**Control:** *"Do not place credentials, registrations or qualifications in `Person` or `Organization` structured data unless the same wording appears visibly on this page and is independently verifiable."*

Five sections: who we are · what makes us different · our approach (four principles) · the team · how the team works together.

**Two team profiles only:** Ahmed Mohammedi (Managing Director and Co-Founder — Business Administration, UAE legal-operations experience, Arabic, English) and Dr. Mohamed Raouf (Principal Legal Consultant and Co-Founder — PhD in Law, legal analysis, Will review and legal supervision).

## 7.8 Contact Us — `/contact`

**Structured data:** `ContactPage` · `Organization` · `BreadcrumbList`
Five sections: how to reach us · before you write · what we can and cannot answer by email · if your situation is urgent · complaints and concerns.

⚠️ **"Do not build a public contact form at launch."** Email and WhatsApp only, as **live text links, not images**. WhatsApp link opens the app or web client with the number pre-filled.

**Working hours:** Monday to Friday, 9:00 AM to 6:00 PM UAE time. Messages outside hours or on UAE public holidays reviewed the next working day.

**Section 2 sensitive-data warning must be visible on load, never inside a collapsed accordion.**

**Consistency rule:** this page must exist at `/contact` as a real page. **Do not redirect it to an anchor on About Us** — five other pages link to it directly. Section 4 must stay aligned with the "Different Service May Be Required" outcome and the FAQ answer for someone who has already died; if one changes, change all three.

## 7.9 The five legal pages

| Page | URL | Scale | Note |
|---|---|---|---|
| Terms and Conditions | `/terms-and-conditions` | 25 clauses | Defines Assessment, Client, Competent Authority, Final Draft, Platform, Service Confirmation, Will |
| Privacy Policy | `/privacy-policy` | 18 clauses | Dated 6 August — deliberately unchanged, no pricing or routing claims |
| Payment and Refund Policy | `/payment-and-refund-policy` | 16 clauses | The stage-based refund engine |
| Legal Disclaimer | `/legal-disclaimer` | 17 clauses | Carries the exact three-outcome customer-facing names |
| Cookie Policy | `/cookie-policy` | 9 clauses | Dated 6 August — deliberately unchanged. **Must not be published until the production cookie scan is complete** |

## 7.10 Visual design constraints — the only ones fixed

Everything else is the designer's decision. These carry legal or accessibility weight:

- Important legal information must be **live text**, never only inside an image
- **No court seals, government logos** or any styling that could suggest the platform is a government authority
- Comparison tables readable on mobile **without horizontal scrolling**
- Legal caveats and limitations must stay **visible, not hidden inside collapsed accordions**
- Readable minimum text sizes and sufficient contrast
- **One primary action per screen**
- No legal meaning communicated by colour alone
- **No emoji in production** — one consistent line-icon set only
- Live links activated only when the destination exists at its final URL

## 7.11 Mobile requirements

Short header · large assessment controls · no wide legal tables · comparison tables converted to labelled cards · save-and-return support · **camera-based document upload** · persistent next-step button · accessible FAQ accordions · no overlapping WhatsApp, cookie and assessment buttons · fast loading on mobile networks.

## 7.12 Analytics and SEO

Google Analytics 4 and Google Tag Manager · Search Console verification support · XML sitemap, robots.txt, canonical tags, appropriate structured data · events for traffic source, campaign, screening start, **abandonment by question**, completion, route outcome, lead creation and payment · operational metrics in the admin dashboard, broader analysis in GA4 · reasonable mobile performance and technical SEO. **All Google properties owned and controlled by Summit.**

Per page: self-referencing canonical · indexable server-rendered text · unique title and meta description · one public H1 · breadcrumb markup · descriptive links · mobile-first layout · sitemap inclusion · Open Graph metadata · appropriate robots directives.

**"Last legally reviewed" is displayed only after named legal sign-off.** Do not use hidden AI text, duplicated answer blocks or unsupported credentials.

## 7.13 Language

**English only at launch.** Architecture and content model **must support additional languages later without rebuilding** the application, database or page templates. Arabic is **not** required for this platform. Russian is under consideration for later. **Translation and entry of future-language content are not part of this MVP.**

---

# PART 8 — PLATFORM FUNCTIONALITY

## 8.1 Ten customer-facing areas (Summit's list)

1. Assessment introduction and qualifying questions
2. Preliminary result
3. Account, verification and basic identity details
4. Acceptance status, engagement terms and payment
5. **Client dashboard**
6. Detailed Will questionnaire and **secure document upload**
7. Further-information requests
8. **Draft review, amendments and final approval**
9. Registration preparation and progress
10. Final documents and aftercare

## 8.2 Six Summit administration areas (Summit's list)

1. Case list and search
2. Assessment, exception-flag and acceptance review
3. Client information and communication requests
4. Questionnaire, documents, draft versions and approvals
5. Payment and registration-status management
6. Final delivery, consent and audit history

## 8.3 The admin dashboard — signed Part B clause 6

Case list, filters, search and pipeline · individual case detail with answers, **trigger reasons**, notes, contact history and full activity history · staff assignment and configurable case statuses · countdowns, overdue flags, reminders and internal escalation · payment-link generation, staged payments, payment history and manual recording of bank transfer or cash · notification settings for email and official WhatsApp Business Cloud API · questionnaire, routing-rule and version-control management · public content management for the approved page templates · operational analytics and reporting · user, role and permission management · audit-log viewer and consent-record export · system and retention settings.

## 8.4 Roles and permissions

**Two full-administrator accounts at launch**, both with complete visibility. Full administrators can later create restricted staff roles **without code changes**.

**Restricted staff CAN:** see and manage only assigned customers · view contact information needed for their work · contact customers · add notes · record calls and follow-ups · change only permitted statuses.

**Restricted staff MUST BE PREVENTED FROM:** viewing unassigned cases · financial reports · questionnaire rules · system settings · user management · other staff members' cases.

**Every material user action and permission change appears in the audit history.**

⚠️ **Q15B adds a requirement beyond this:** the capacity/undue-influence alert must be **restricted** and its content must not be disclosed in a way the influencing person could see. That is a confidentiality layer above ordinary role permissions.

## 8.5 Summit's role definitions from the content

- **Visitor** — reads public pages, completes the preliminary assessment
- **Client** — creates an account, completes the detailed process, uploads documents, reviews drafts, follows registration progress
- **Summit legal reviewer** — reviews every case's questionnaire, documents, legal suitability, draft versions, amendments and approval readiness, plus the held categories before payment
- **Summit administrator** — client communication, payment status, document requests, appointments, registration progress, completion records

The MVP may allow one Summit user to hold more than one internal role, with an audit record of actions.

## 8.6 Case statuses — two parallel systems

**Internal set, signed Part B clause 4.5:** New Lead · Screening In Progress · Incomplete · Straightforward · Consultation Required · Special Review · Contacted · Awaiting Information · Awaiting Payment · Paid · Detailed Questionnaire In Progress · Submitted · Under Legal Review · Drafting / Manual Processing · Awaiting Client Approval · Registration / Appointment Pending · Completed · Cancelled · Refunded.

**Customer-facing groups, from the content package:** 1 Assessment Completed · 2 Under Review or Further Information Required · 3 Accepted — Terms and Payment Required · 4 Questionnaire In Progress · 5 Legal Review and Drafting · 6 Draft Review, Amendments or Approval · 7 Registration Preparation or In Progress · 8 Completed.

Rule: *"Avoid internal legal or technical labels that a customer will not understand."* These are a **display-mapping layer**, not a rename.

## 8.7 Client dashboard card

Client name · service or preliminary pathway · case reference · current status · progress bar · required next action · outstanding information or documents · payment status · draft status including the 2-day target · registration status · **secure messages or requests** · final documents when available.

## 8.8 Notifications — signed Part B clause 7

Official **WhatsApp Business Cloud API** under Summit-owned Meta and WhatsApp accounts · transactional email under a Summit-owned service account · alerts to **two administrator numbers**, configurable from the dashboard · configurable working hours and case countdowns · **delivery success or failure logs** for each WhatsApp and email notification · **email fallback when an operational WhatsApp notification fails** · overdue reminders and internal escalation when the countdown expires.

SkillLeo assists with Meta setup, verification and template submission. Meta's own approval time is outside SkillLeo's control; the setup, submission and testing work are included.

**Sending identity:** every transactional email under Summit Legal Consultancy's identity. Public address `info@uaeexpatwills.com`; automated mail from `noreply@uaeexpatwills.com`; the Summit domain reserved for lawyer correspondence after a matter is accepted.

## 8.9 Payment integration — signed Part B clause 8

Telr or another Summit-approved gateway with a reasonably equivalent documented API · **gateway account opened and owned by Summit** · **automatic payment-link creation from the dashboard; no manual link paste as the normal workflow** · links tied to a specific customer, case, amount and payment stage · **secure webhook updates and a manual status check against the gateway** · statuses Pending, Paid, Failed, Cancelled, Partially Paid, Refunded · full and staged payment requests · manual recording of bank transfer or cash · transaction reference, amount, date, method and permanent failed/refund history · basic payment confirmation stored against the case · **no raw card data received or stored**.

Gateway approval is not guaranteed by SkillLeo. Whether a refund is initiated from the platform or the gateway dashboard **must be confirmed during Milestone 1** according to the selected gateway's API.

A complete accounting or VAT invoicing module, multi-currency accounting and automatic tax reporting are **not included**.

---

# PART 9 — COMPLIANCE, DATA AND SECURITY

## 9.1 Security controls — contractually required

Secure **HTTP-only cookies** for authenticated administrative sessions, not local storage · industry-standard password hashing · **mandatory two-factor authentication for administrators** · **backend-enforced role-based access control** — the frontend is never the only line of defence · rate limiting and brute-force protection on sensitive endpoints · ability to revoke sessions and disable accounts · **least-privilege named access; no shared master credentials** · dependency and vulnerability checks before launch with a basic written security-testing report.

## 9.2 Audit history must record

Successful and failed logins · case access · assignments · status changes · questionnaire and routing changes · payment actions · user and permission changes · notifications · data exports. **Ordinary administrators cannot edit or delete audit records** — append-only by design.

## 9.3 Consent records

Must include the accepted wording or version · date and time · case reference · IP address · language · the related questionnaire or transaction. **Must be exportable.**

**Consent events to capture:**

| Event | Store |
|---|---|
| Cookie choice | choice, banner/policy version, timestamp, limited consent identifier |
| Religion / sensitive data | wording, version, timestamp, express unticked consent |
| Seven assessment declarations | each one individually, with version and timestamp |
| Terms acceptance at checkout | version, date, time, user identifier |
| Privacy notice acceptance | version and timestamp |
| Draft approval | who, which version, when |

## 9.4 Cookie consent

Three equally clear controls: **Accept All · Reject Non-Essential · Manage Preferences**. Rejecting must be as easy as accepting. No pre-ticked optional categories. **Block all non-essential tags before consent.** Test fresh sessions, rejected consent and withdrawn consent. Persistent **Cookie Settings** footer link.

Four categories: strictly necessary · preference · analytics · marketing. If no marketing technology is active, that category must remain switched off and the register must say no marketing cookies are used.

Live cookie register generated from the **production** site — name, provider, purpose, category, duration, first or third party. **The Cookie Policy cannot be published until the production scan is complete.**

## 9.5 Sensitive-data handling

- Religion requested **separately**, with stated purpose, explanation of use, privacy safeguards, and **express unticked consent**
- **Disable session replay** on assessment, account, questionnaire, document, payment and client-portal screens
- **Prevent questionnaire answers, religion, family details, beneficiary details, guardianship wishes and document names from entering analytics, advertising URLs, pixels, event labels or session-replay tools**
- Analytics reports must not contain Will instructions or sensitive answers
- **Never request or store** online-banking passwords, card PINs, private cryptocurrency keys, seed phrases or recovery phrases
- A **data-protection impact assessment** is required for the sensitive-data questionnaire and the automated routing, before launch
- **Build a human-review request route** for preliminary automated outcomes

## 9.6 Retention

A production-data retention schedule approved before launch. The system must support authorised export and deletion of customer records per Summit's approved policy and applicable UAE law. Unfinished assessments, abandoned accounts and unsuccessful enquiries retained for **shorter** periods than completed client files. Incomplete-questionnaire retention configurable, **initially 30 days**. **Do not publish invented fixed periods.**

## 9.7 Legal sources Summit relies on

Federal Decree-Law 41/2022 Civil Personal Status · Cabinet Resolution 122/2023 Executive Regulations · Federal Decree-Law 41/2024 Personal Status Law · Dubai Law 15/2017 non-Muslim Wills register · Dubai Law 2/2025 DIFC Courts, Article 31(5) enforcement and Article 32 Dubai Courts assistance, verified 5 August 2026 · DIFC Courts Wills Service and FAQ · ADJD Civil Family Court · Federal Decree-Law 45/2021 data protection · Federal Decree-Law 46/2021 and Cabinet Resolution 28/2023 electronic transactions · Federal Law 15/2020 and Cabinet Resolution 66/2023 consumer protection.

## 9.8 Route eligibility reference

| Route | Eligibility |
|---|---|
| **DIFC Courts Wills** | Not Muslim and **never** having been Muslim · at least 18 · UAE assets and/or qualifying minor children residing with the testator. **UAE residence and visa not required.** Virtual registration currently available |
| **ADJD Civil Wills** | **Not available to UAE citizens at all**, regardless of religion. Available to non-UAE citizens **regardless of religion**. **No GCC-wide exclusion** |
| **Dubai Courts Wills** | Non-Muslims, under Dubai Law No. 15 of 2017 |

**Six DIFC Will types:** Full · Property (up to 5 qualifying UAE properties) · Business Owners (up to 5 qualifying UAE shareholdings) · Financial Assets (up to 10 qualifying UAE bank or brokerage accounts) · Guardianship · Digital Assets.

**Muslim Wills:** ordinarily implemented within **one third** of the estate unless heirs consent. Further rules apply where a beneficiary is already an heir.

## 9.9 Claims that must never be published without a verified source

All UAE bank accounts freeze automatically on death · joint accounts always pass to the survivor · a registered Will avoids probate · a UAE Will is automatically valid worldwide · a foreign Will is automatically invalid in the UAE · Sharia applies automatically to every expatriate · a spouse automatically receives everything · registration prevents challenge · a court must appoint the nominated guardian · registration completes within a guaranteed period · authority fees are included unless expressly stated · ADGM is a UAE Will registry · **Summit is a DIFC Registered Wills Draftsman before the listing is live** · the 2-business-day target applies to authority timing.

## 9.10 DIFC credential control — Structural Decision 5, still pending

Registration understood to be in progress; no verifiable public listing found. Until visible: **no page, meta description, structured data or marketing material may state or imply Summit is a registered DIFC Wills Draftsman.** Do not activate on the basis of a submitted application, paid fee, completed training or expected approval.

On activation, verify first: exact registered name · listing URL · registration category · effective and expiry dates · scope and conditions · the exact term the register uses. Then update together: Do You Need a Will · UAE Will Options · About · Our Legal Team · engagement terms · assessment routing · structured data.

## 9.11 Locked cross-page wording

| Item | Fixed treatment |
|---|---|
| Ownership | "Owned and operated by Summit Legal Consultancy UAE" |
| Short brand line | "A Summit Legal Consultancy UAE Platform" |
| **Never used** | **"Supported by Summit"** |
| Licence | Trade Licence No. 4429232.01 |
| Registration | "Registration is completed by the competent authority" — never by the platform |
| Assessment result | "Preliminary", never final |
| DIFC credential | No claim until the public listing is verified |
| ADJD eligibility | Not available to UAE citizens; a non-UAE citizen may register regardless of religion |
| Price — standard | "The professional fee for one accepted standard Will is AED 2,199 plus VAT, including preparation, human legal review, certified legal translation of the new UAE Will and submission assistance. Government and other third-party charges are separate." |
| Price — DIFC | "DIFC Will engagements start from AED 3,999 plus VAT, quoted individually" |
| First-draft turnaround | 2 business days for an accepted standard matter after complete, usable instructions and all required documents; specialist matters may take longer |
| Sensitive data | Religion requested separately, with stated purpose, explicit consent and a right to decline |
| Contact published | info@uaeexpatwills.com · WhatsApp +971 52 466 6191 |
| Contact not published | noreply@uaeexpatwills.com for automated mail; the Summit domain for lawyer correspondence after acceptance |
| Address | Not displayed until confirmed |
| Emoji | Never in production; line-icon set only |

## 9.12 Verification cadence Summit will operate

Monthly until launch: ADJD guidance · DIFC service pages, Will types and fees · DIFC register listing · Dubai Courts service availability.
Quarterly after launch: all of the above plus authority URLs, internal-link destinations and the AED 2,199 / AED 3,999 pricing.
On announcement: legislative amendments · authority rule changes · Summit scope or price changes · DIFC registration status.

**Implication for the build:** the content management module must make these updates easy, because they will happen on a schedule, not occasionally.


---

# PART 10 — THE CONTRADICTIONS REGISTER

Every conflict found across the signed Agreement, the content package and the questionnaire. **None of these are resolved unilaterally.** Each needs a decision from Summit before the affected component is built.

## 10.1 ⚠️ CRITICAL — the questionnaire uses the OLD routing model

**This is the most consequential finding in the entire package.**

The 13 August content package rewrote Structural Decision 4 specifically to **narrow** the pre-payment holds to exactly three categories. The changelog states it explicitly:

> *"Previously: Muslim wills, blended families, business owners, trusts, disputed ownership, international connections and more were all described as held for legal review before payment. Now: every Will still gets full human legal review — the only change is timing. Only three categories are held before payment: 1. DIFC requests 2. Capacity or undue-influence concerns 3. Active legal disputes."*

> *"Everyone else — including Muslim clients, blended families, business owners, shareholders, people with existing Wills, and multi-country matters — pays first."*

**The questionnaire sends all of those categories to REVIEW before payment.** Specifically:

| Category the website says pays first | Questionnaire outcome |
|---|---|
| Blended families, children from more than one marriage | Q7 → **REVIEW** |
| Stepchildren, adopted, kafala, foster children | Q7 → **REVIEW** |
| Previous marriages, divorced, separated | Q6 → **REVIEW** |
| Business owners, private company shares | Q10 → **REVIEW** |
| Trusts and Foundations | Q10, Q11, Q12, Q13A → **REVIEW** |
| Existing UAE or foreign Will | Q1, Q12 → **REVIEW** |
| Multi-country assets or connections | Q9, Q15A → **REVIEW** |
| Disputed or unclear ownership | Q11 → **REVIEW** |
| Intellectual property, royalties | Q10 → **REVIEW** |
| Charity gifts, exclusions, conditional gifts | Q13A → **REVIEW** |
| Corporate or multiple executors | Q14 → **REVIEW** |

**Consequence if built as written:** almost nobody reaches checkout. The entire pay-first commercial model collapses, and every public page describing it becomes inaccurate. Ahmed's own stated reason for the 13 August change was that the old model *"meant most enquiries never reached checkout without a manual touch, which does not reflect how the service is actually intended to run."* The questionnaire is the artifact that produces exactly that outcome.

**Most likely explanation:** the questionnaire was written before the 13 August routing change and was not revised alongside the 13 pages. It is titled "Final" but carries no version number or date, unlike every other file in the package.

**What must happen:** Summit decides which model is correct, and the losing document is revised. This cannot be guessed at. It changes the commercial model, the public copy on at least eight pages, and the routing rules.

## 10.2 ⚠️ Number of outcomes — two, three, or six

| Source | States |
|---|---|
| Questionnaire preamble | *"There are only two customer outcomes"* |
| Website map, Terms clause 5, Legal Disclaimer clause 3, FAQ 40 | **Three** outcomes with fixed customer-facing names |
| The questionnaire's own logic | **Six** terminal states — continue, review, estate referral, under-18 stop, UAE-citizen stop, urgent capacity review |

The three named outcomes do not have a home for "under 18", "UAE citizen" or "urgent capacity review". Those three are real terminal states with distinct screens and distinct handling, and none of them is "Different Service May Be Required" in any natural reading.

**Needs:** a confirmed outcome list, and confirmed customer-facing wording for each.

## 10.3 ⚠️ Religion — no decline option in the questionnaire

**How It Works section 3:** *"If the information is not provided, the case is directed to review instead of automatic routing."*
**Privacy Policy section 3:** *"If you choose not to provide route-relevant sensitive data, we may be unable to provide automated routing and may refer the matter for manual review."*
**Structural decisions consistency register:** religion is requested *"with stated purpose, explicit consent and a right to decline."*

**Q5 offers only three options:** Muslim · Non-Muslim · I was previously Muslim. **There is no "prefer not to say".**

Under UAE data-protection law, religion is sensitive personal data and consent must be freely given. A consent mechanism with no refusal option is not a consent mechanism. **A fourth option and its routing outcome must be added.**

## 10.4 Assessment duration

| Source | States |
|---|---|
| Signed Part B clause 4.1 | *"approximately three to five minutes for a straightforward visitor"* |
| Website content, all pages | three to five minutes |
| **Questionnaire welcome screen** | *"normally takes between four and seven minutes"* |

Minor, but it appears on a customer-facing screen and in the signed contract. Pick one.

## 10.5 The payment model — contract versus content

| Signed Part B clause 4.3 | Content package and questionnaire |
|---|---|
| Summit reviews the case and **selects the appropriate amount or payment stage** | The customer sees AED 2,199 published on the website and **pays directly at checkout** |
| **The dashboard generates a customer-specific payment link** through the gateway API | Checkout is embedded in the customer journey |
| Packages and pricing **assigned by Summit after understanding the case, never selected by the customer** | A full public Pricing page with fixed prices |

Both cannot be true. This is the single largest functional divergence and it drives several others below.

## 10.6 Detailed questionnaire — magic link versus client portal

| Signed Part B clause 4.4 | Content package |
|---|---|
| *"Save and resume **without a client portal**"* | *"the detailed questionnaire opens in the secure client area"* |
| *"A time-limited, unguessable and revocable email magic link over HTTPS"* | Accessed through the authenticated Client Login |

## 10.7 Filename versions do not match in-document versions

Nine of thirteen page files:

| File | Filename | Header |
|---|---|---|
| 01-homepage | v4 | Final version v3 |
| 03-do-you-need | v3 | Version 2 |
| 04-registration-options | v3 | Version 2 |
| 07-about-us | v4 | Version 3 |
| 08-contact-us | v2 | Version 1 |
| 09-terms | v4 | Publication draft v3 |
| 11-payment-and-refund | v4 | Publication draft v3 |
| 12-legal-disclaimer | v4 | Publication draft v3 |
| 10-privacy, 13-cookie | v3 | no number, dated 6 August |

Correct: `00-structural-decisions`, `00-website-map`, `02-how-it-works`, `05-pricing`.

**Not an error:** Privacy and Cookie carry the earlier date because the changelog records them as copied through unchanged.

## 10.8 Stale filenames in the changelog

The changelog refers to `05-faqs-FINAL.md`, `05-FAQ.md`, `07-contact-us-v1.md`, `10-privacy-policy-v2.md` and `13-cookie-policy-v2.md`. Under the current numbering the FAQ is 06 and Contact is 08. Harmless in itself, but it names a superseded **50-question FAQ** that must never be published.

## 10.9 The DIFC fee row is missing from the questionnaire's price screen

The Pricing page shows three authority rows — ADJD, Dubai Courts, DIFC. The questionnaire's price screen shows only ADJD and Dubai Courts. Consistent with DIFC never reaching that screen, but worth confirming as deliberate.

## 10.10 Team roster

**summitlegaluae.com:** Dr. Mohamed Raouf, Ahmed Mohammadi, Sara Zaki.
**New About Us page:** Ahmed and Dr. Mohamed Raouf only.
Sara Zaki is absent. Deliberate or an omission?

## 10.11 Ahmed's own title

**Manager** in the signed contract · **Managing Director and Co-Founder** on About Us · **Executive Director and Co-Founder** on summitlegaluae.com. Three titles for one person across three live documents.

---

# PART 11 — SCOPE RECONCILIATION

## 11.1 Category 3 — outside signed scope, requires written price and timeline before work begins

| # | Requirement | Signed position | Where it appears |
|---|---|---|---|
| 1 | **Client Login / client portal / client dashboard** | Clause 16 excl. 3 | Header, mobile header and footer of every page; site map; a full dashboard spec; build order Stage 2 says *"Client Login must be built alongside the public pages"* |
| 2 | **Customer account creation, verification, identity, credentials** | Not in Part B; 4.4 specifies magic link **without** a portal | Journey step 3; build order Stage 4; Terms clause 14 |
| 3 | **Secure document upload and storage, including camera capture** | Clause 16 excl. 4 | Functional area 6; Client role; mobile requirements; Privacy scope |
| 4 | **Draft delivery, versioning, amendment loop, client approval** | Not in Part B; excl. 4 covers document sharing | Journey step 7; functional area 8; Terms clause 10 |
| 5 | **Embedded on-site checkout, customer pays directly** | Part B 4.3 — Summit selects the amount, dashboard generates the link | How It Works step 4; questionnaire price screen; Pricing build note |
| 6 | **Published fixed pricing, self-selected service** | Part B — packages assigned by Summit after understanding the case | Entire Pricing page; homepage hero |
| 7 | **Secure client-to-Summit messaging** | Not in Part B | Dashboard card: "secure messages or requests" |
| 8 | **Customer-visible registration progress tracking** | Clause 16 excl. 5 and 6 | Functional area 9; dashboard; status group 7 |
| 9 | **Five-stage timestamp engine plus fee apportionment** | Part B has payment statuses, no apportionment model | Payment Policy sections 5 and 6; pre-publication checks 3 and 7 |
| 10 | **DIFC credit-against-a-different-service logic** | Part B supports Refunded, not conditional credit | Payment Policy section 8; Structural Decision 6 |
| 11 | **Human-review-request route for automated outcomes** | Not in Part B | Privacy Policy section 8; pre-publication check 7 |
| 12 | **Restricted-visibility confidential alert** (Q15B) | Part B has role-based access, not per-record confidentiality above role level | Questionnaire Q15B |
| 13 | **Authority appointment booking**, if Summit confirms it is included | Clause 16 excl. 6 | Listed as "still outstanding" in Summit's own open items |

**Items 1, 2, 3, 4, 7 and 8 together are the client portal** that Clause 16 priced indicatively at USD 300–500 over two to three weeks. That estimate covered login and a dashboard. It did not cover document upload, draft versioning, an approval workflow, secure messaging and registration tracking.

**Items 5, 6, 9 and 10 replace the agreed payment model** with a self-serve checkout plus a stage-based refund engine. That is a different build, not a larger version of the same one.

## 11.2 Inside signed scope — Category 1 and 2, no charge

The 13 public pages (Part B 3.2 says "at minimum", so additional page types under the reusable template are covered) · both questionnaires · conditional logic · routing rules · questionnaire version control with draft/publish/rollback · case creation with trigger reasons · admin dashboard, roles, permissions, configurable statuses · countdowns, overdue flags, escalation · audit log and consent-record export · cookie consent and preferences · WhatsApp and email notifications with delivery logs and email fallback · payment-link generation, webhooks, manual status check, manual bank/cash recording · GA4, GTM, Search Console, sitemap, robots, canonicals, structured data, abandonment-by-question events · English-only launch with multilingual-ready architecture · custom mobile-first design with two revision rounds · AWS setup, encryption, backups, restoration test · security controls, 2FA, RBAC, rate limiting.

**Ahmed's expected wording revisions are Category 2** — sentence-level edits inside approved pages, included. His voice note is explicit that changes will be *"just the wording."* That is exactly what Clause 13.2 covers.

## 11.3 Genuine alignments worth noting

- **No public contact form** — Summit's instruction matches Part B, which never included one
- **Three routing outcomes** map onto Part B 4.2's three configurable outcomes
- **Detailed questionnaire released after payment** — same trigger as Part B 4.4
- **No `FAQPage` structured data** — narrows work slightly
- **Visual design left to the designer** — no conflict over direction
- **English only, no Arabic** — matches Part B 3.4 exactly

---

# PART 12 — DESIGN BRIEF

Ahmed asked directly: *"If you have a design in mind, please share it with me. What color you will use, font, this, that, keep me posted."*

## 12.1 What Milestone 1 owes him

Approved sitemap and screen inventory · complete customer journeys · mobile and desktop custom UI/UX · **two design revision rounds** · design source files · data-flow and architecture summary · Summit-controlled Git repository and AWS/staging setup. **5–7 business days.**

## 12.2 Screen inventory to price and design

**Public — 13 pages.**
**Assessment — welcome screen · up to 16 question screens plus conditional sub-screens · declarations screen · Result 1 · Result 2 · estate-referral screen · under-18 screen · UAE-citizen screen · urgent-review screen · price and service screen.**
**Account and payment — account creation · verification · engagement terms · checkout · confirmation.**
**Client area — dashboard · detailed questionnaire · document upload · further-information request · draft review and approval · registration progress · final documents.**
**Admin — case list and pipeline · case detail · staff assignment · payments · notification settings · questionnaire and routing management with version control · content management · analytics · users and roles · audit log · settings.**

Everything under "Client area" is Category 3 and must not be designed until the change request is approved. **Designing a header with a Client Login button in it silently concedes the portal.**

## 12.3 Design constraints that are already fixed

See Part 7.10. In summary: live text for legal information, no government-style imagery, mobile-readable tables, caveats never hidden in accordions, one primary action per screen, no meaning by colour alone, no emoji, line icons only.

## 12.4 What to propose to him

He asked for colour and font specifically. What will land well with this client, based on everything he has written:

- A palette that reads as **licensed legal firm**, not legal-tech startup, and that carries no resemblance to government or court branding — he has ruled out seals and emblems explicitly and repeatedly
- Typography chosen for **long-form legal readability**, since five of the thirteen pages are dense legal text and the FAQ is 57 accordions
- A visible **trust treatment** for the ownership line and Trade Licence number, since he raised firm credibility unprompted and it appears on every page
- **Mobile-first**, because he has said many customers arrive from Google or WhatsApp
- A **status and progress system** with enough states to carry all eight customer-facing status groups
- Table-to-card patterns for the five-route comparison and the three-column responsibilities table

Present it as a direction with reasoning, not a finished product. He responds to reasoning.

---

# PART 13 — OPEN ITEMS AND ACTIONS

## 13.1 Blocking, on SkillLeo's side

| # | Action | Why |
|---|---|---|
| 1 | **Confirm the effective date of the Agreement** | Ahmed signed on the date line; no date was written. Clause A1.3 makes the effective date the date of the last signature |
| 2 | **Obtain written approval under Clause A3.3** before Summit content goes into any third-party AI service | Breach of confidentiality is uncapped under A9.2 |
| 3 | **Deliver Clause 17 preconditions** — live work examples and a summary of Saif's role — before Milestone 1 activates | Contractual precondition |
| 4 | **Raise Order 1 on Fiverr** | A voice note is not an order. Rider R2 and Part B 12.4 tie work to orders |
| 5 | **Issue the Category 3 change request in writing** with price and timeline | Clause 13.3 and rider R4. Must precede design work on any portal screen |
| 6 | **Complete the expatwill.ae mirror-will walkthrough** | Outstanding promise, and now directly relevant to questionnaire Q1's two-Wills flow |

## 13.2 Blocking, on Summit's side

| # | Item | Impact |
|---|---|---|
| 1 | **Resolve contradiction 10.1** — which routing model is correct | Blocks the routing engine, and the copy on at least eight pages |
| 2 | **Confirm the outcome list** — two, three or six | Blocks result screens |
| 3 | **Add a "prefer not to say" option to Q5** and its routing outcome | Legal requirement under UAE data-protection law |
| 4 | **Confirm the payment model** — self-serve checkout or Summit-generated link | Blocks Milestone 3 and the whole checkout design |
| 5 | Brand assets — logo, colours, fonts | Blocks Milestone 1 visual work |
| 6 | Full registered legal entity name | Required on legal pages and `Organization` schema |
| 7 | Registered office address | Currently displayed nowhere |

## 13.3 Summit's own declared open items

- **Number and scope of included amendments** — appears in Pricing, Terms, Payment Policy and the Service Confirmation
- **Whether authority appointment booking is included** — collides with Clause 16 exclusion 6
- **What counts as out-of-scope work**
- **Whether a DIFC contact-first case can carry its own consultation fee** before the AED 3,999 quotation
- Payment provider identity, supported methods, settlement currency, non-refundable processor fees and actual refund times
- Production cookie scan and the live Cookie Settings register
- Written ADJD confirmation from `wills.non-muslim@adjd.gov.ae`, and whether registration for a non-UAE-citizen Muslim is an entitlement or discretionary
- DIFC Wills Draftsman registration status
- Confirmation that the 2-business-day draft target is operationally realistic
- Final UAE legal, data-protection and operational sign-off on every page

## 13.4 Live Milestone 1 tasks written into the contract

- Final sitemap approved during Milestone 1
- **Refund initiation method** — from the platform or the gateway dashboard — must be confirmed during Milestone 1 according to the selected gateway's API
- Production-data retention schedule approved before launch

## 13.5 How to work with this client

1. **Never skim anything he sends.** He chose SkillLeo over 34 other candidates purely for catching a buried instruction in his brief. His words: *"i appreciate people who look into details and read. this is what we do in legal. missing one part of paragraph makes everything goes wrong after."*
2. **Never renegotiate price or timeline.** Both are locked and both were settled fairly.
3. **Never absorb a Category 3 change silently.** The change-control process protects the fixed price and both parties signed it.
4. **Never push him when he says he is tired.** He always comes back.
5. **Catching an error in his own document is a trust-building act with this client, not a confrontational one.** It closed the deal once already. Contradiction 10.1 is the next occasion.
6. **Every chat message to him: all lowercase, no bullets, no symbols, paragraphs only.** Formal documents use normal capitalisation.

---

*End of master specification. Sources: signed Agreement of 1 August 2026 · the 31-file content package of 13 August 2026 · the Final Qualifying Questionnaire · two client voice notes of 16 August 2026. Superseded companion files: `SUMMIT-CONTENT-KNOWLEDGE-BASE.md` and `SUMMIT-PLATFORM-FLOW-SPEC.md` — both are folded into this document.*
