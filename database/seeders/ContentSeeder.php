<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * All 13 public pages, their sections, and the 57 FAQs.
 *
 * Copy is seeded VERBATIM from the design project. The contract forbids
 * rewriting Summit's legal wording, so nothing here is paraphrased, tightened or
 * "improved" — what the design says is what goes in the database.
 *
 * Every page and section is a row, so an administrator edits any word of it from
 * the content manager and the change is live immediately.
 */
class ContentSeeder extends Seeder
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, mixed> */
    private array $pageData;

    public function run(): void
    {
        $this->data = json_decode(file_get_contents(__DIR__.'/data/content.json'), true);
        $this->pageData = json_decode(file_get_contents(__DIR__.'/data/pages.json'), true);

        DB::transaction(function () {
            $this->pages();
            $this->faqs();
        });
    }

    private function pages(): void
    {
        $pages = [
            ['home', '/', 'UAE Wills for Expatriates — Assessment, Legal Review and Registration Assistance', 'Prepare a UAE Will with human legal review. Free assessment, no account needed. Owned and operated by Summit Legal Consultancy UAE.', 'Home', 1],
            ['how_it_works', '/how-it-works', 'How It Works — From Free Assessment to Registration Assistance', 'Nine steps from the free assessment to registration assistance. Each step states who acts: you, Summit\'s legal team, or the competent authority.', 'How It Works', 2],
            ['do_you_need', '/do-you-need-a-uae-will', 'Do You Need a UAE Will?', 'Eight circumstances in which a UAE Will matters most — minor children, blended families, UAE property, business interests and international assets.', 'Do You Need a UAE Will?', 3],
            ['will_options', '/uae-will-registration-options', 'UAE Will Registration Options — DIFC, ADJD and Dubai Courts', 'The main UAE Will pathways compared: DIFC Courts, Abu Dhabi Civil Wills, Dubai Courts, the Muslim Will pathway and foreign Wills involving UAE assets.', 'UAE Will Options', 4],
            ['pricing', '/pricing', 'Pricing — Clear Professional Fees, Authority Charges Kept Separate', 'AED {fee} plus VAT for one accepted standard Will. Government, court and notary charges are shown separately because the authority sets them.', 'Pricing', 5],
            ['faqs', '/faqs', 'Frequently Asked Questions', '57 questions about UAE Wills, fees, eligibility, registration, guardianship, privacy and what happens after registration.', 'FAQs', 6],
            ['about', '/about-us', 'About Us — Summit Legal Consultancy UAE', 'UAE Expat Wills is owned and operated by Summit Legal Consultancy UAE, Trade Licence No. 4429232.01.', 'About Us', 7],
            ['contact', '/contact', 'Contact', 'Contact Summit Legal Consultancy UAE by email or WhatsApp. Please do not send matter detail, identity documents or asset information by message.', 'Contact', 8],
            ['terms', '/terms-and-conditions', 'Terms and Conditions', 'The terms on which UAE Expat Wills and Summit Legal Consultancy UAE provide the platform and its legal services.', 'Terms and Conditions', 9],
            ['privacy', '/privacy-policy', 'Privacy Policy', 'How UAE Expat Wills collects, uses, stores and protects your personal information, including sensitive assessment answers.', 'Privacy Policy', 10],
            ['refund', '/payment-and-refund-policy', 'Payment and Refund Policy', 'What the professional fee covers, what is charged separately, and the refund bands that apply at each stage of the work.', 'Payment and Refund Policy', 11],
            ['disclaimer', '/legal-disclaimer', 'Legal Disclaimer', 'UAE Expat Wills and Summit Legal Consultancy UAE are not a court, registry, notary or government authority.', 'Legal Disclaimer', 12],
            ['cookies', '/cookie-policy', 'Cookie Policy', 'The cookies this site sets, what each category does, and how to change your choices at any time.', 'Cookie Policy', 13],
        ];

        // The Cookie Policy is written and stored, but it stays unpublished
        // until the production cookie scan confirms the counts it states. Every
        // other page ships live.
        $unpublished = ['cookies'];

        foreach ($pages as [$key, $slug, $seoTitle, $description, $breadcrumb, $order]) {
            $page = Page::updateOrCreate(
                ['key' => $key],
                [
                    'slug' => $slug,
                    'title' => $breadcrumb,
                    'seo_title' => $seoTitle,
                    'meta_description' => $description,
                    'breadcrumb' => $breadcrumb,
                    'is_published' => ! in_array($key, $unpublished, true),
                    'published_at' => in_array($key, $unpublished, true) ? null : now(),
                    'order' => $order,
                    'locale' => 'en',
                ],
            );

            $page->sections()->delete();
            $this->sectionsFor($page);
        }
    }

    private function sectionsFor(Page $page): void
    {
        $sections = match ($page->key) {
            'home' => $this->homeSections(),
            'how_it_works' => $this->howItWorksSections(),
            'will_options' => $this->willOptionsSections(),
            'pricing' => $this->pricingSections(),
            'about' => $this->aboutSections(),

            'do_you_need' => [[
                'key' => 'profiles',
                'type' => 'profile_grid',
                'heading' => 'Eight circumstances in which a UAE Will matters most',
                'subheading' => 'Not every person needs the same Will or registration route. The assessment identifies the likely next step without asking you to choose the legal pathway yourself.',
                'items' => $this->data['PROFILES'],
            ]],

            // The five legal pages. Clause numbers sit in the margin column and
            // the body is capped at 64ch, per the design's legal-page spec.
            'terms', 'privacy', 'refund', 'disclaimer', 'cookies' => $this->legalSections($page->key),

            'contact' => [[
                'key' => 'channels',
                'type' => 'contact_channels',
                'heading' => 'Contact our team',
                // There is NO contact form on this page. Email and WhatsApp as
                // live text links only — a written client rule.
                'subheading' => 'Email and WhatsApp only. Please do not send instructions, identity documents or asset detail by message — those are exchanged inside your secure account.',
                'items' => [],
            ]],

            default => [],
        };

        foreach ($sections as $i => $section) {
            $page->sections()->create([
                'key' => $section['key'],
                'order' => ($i + 1) * 10,
                'type' => $section['type'],
                'heading' => $section['heading'] ?? null,
                'subheading' => $section['subheading'] ?? null,
                'body' => $section['body'] ?? null,
                'items' => $section['items'] ?? null,
                'settings' => $section['settings'] ?? null,
                'locale' => 'en',
            ]);
        }

        // The cookie policy also carries the four category descriptions the
        // preferences panel reads from.
        if ($page->key === 'cookies') {
            $page->sections()->create([
                'key' => 'categories',
                'order' => 20,
                'type' => 'cookie_categories',
                'heading' => 'The four categories',
                'items' => $this->data['COOKIE_CATS'],
                'locale' => 'en',
            ]);
        }
    }

    /**
     * How It Works — nine steps, each naming who acts.
     *
     * The actor label on every step is the point of the page: most services
     * blur the line between what the firm does and what the authority does,
     * and that blur is where the unkeepable promises come from.
     *
     * @return array<int, array<string, mixed>>
     */
    private function howItWorksSections(): array
    {
        return [
            [
                'key' => 'intro',
                'type' => 'page_intro',
                'heading' => 'From free assessment to registration assistance',
                'subheading' => 'How it works',
                'body' => "Nine steps. Each one states who acts — you, Summit's legal team, or the competent authority — because the difference matters and most services blur it.",
            ],
            [
                'key' => 'steps',
                'type' => 'journey_steps',
                'items' => $this->pageData['HowItWorks_STEPS'],
            ],
            [
                'key' => 'responsibilities',
                'type' => 'responsibility_matrix',
                'heading' => 'Who is responsible for what',
                'subheading' => 'Three parties, three sets of obligations. The authority column is the one most services leave out, and it is the reason no timetable can be guaranteed.',
                'items' => $this->pageData['HowItWorks_RESP'],
                'settings' => [
                    'columns' => ['Summit Legal Consultancy', 'You', 'The competent authority'],
                ],
            ],
            [
                'key' => 'timing',
                'type' => 'note_pair',
                'heading' => 'About timing',
                'body' => 'For an accepted standard matter we aim to send the first draft within {first_draft_days} business days after receiving complete, usable instructions and all required documents. A matter requiring specialist analysis, further clarification or additional documents may take longer, and we will tell you if that applies.',
                'settings' => [
                    'second' => 'Registration timing is set by the competent authority. We cannot guarantee that any authority will accept a Will or complete registration within a particular period.',
                    'aside' => 'Related: the five registration routes and how they differ are set out on UAE Will options, and every charge is itemised on pricing.',
                    'links' => [
                        ['label' => 'UAE Will options', 'href' => '/uae-will-registration-options'],
                        ['label' => 'Pricing', 'href' => '/pricing'],
                    ],
                ],
            ],
            [
                'key' => 'cta',
                'type' => 'cta',
                'heading' => 'Begin at step one',
                'body' => 'The free assessment identifies the likely next step. No account or payment is required to complete it.',
                'settings' => [
                    'primary' => ['label' => 'Start the assessment', 'href' => '/assessment'],
                ],
            ],
        ];
    }

    /**
     * UAE Will Options — the five routes.
     *
     * Deliberately NO call to action inside any route section. Nothing should
     * pressure a decision while the reader is still working out which route
     * they are even in; the single CTA sits at the end.
     *
     * @return array<int, array<string, mixed>>
     */
    private function willOptionsSections(): array
    {
        return [
            [
                'key' => 'intro',
                'type' => 'page_intro',
                'heading' => 'The main UAE Will pathways at a glance',
                'subheading' => 'UAE Will options',
                'body' => 'This comparison is a practical starting point, not a final eligibility or legal-effect decision. You do not choose the route yourself — the assessment and the legal review identify it.',
                'settings' => [
                    'note' => 'The assessment provides a preliminary pathway only. It does not confirm final authority eligibility, create a final Will or complete registration.',
                ],
            ],
            [
                'key' => 'routes_table',
                'type' => 'route_table',
                'items' => $this->pageData['WillOptions_ROUTES'],
                'settings' => [
                    'columns' => ['Route', 'Who it may be relevant for', 'Main features', 'How it is handled'],
                ],
            ],
            [
                'key' => 'beyond',
                'type' => 'note_pair',
                'heading' => 'Beyond registration',
                'body' => 'Route selection should consider what happens after death as well as the registration appointment. The chosen framework can affect the court dealing with probate or implementation, document language, enforcement steps, asset-transfer procedures and the work required from the executor or family.',
                'settings' => [
                    'second_heading' => 'What the comparison does not decide',
                    'second' => 'It does not confirm that an authority will accept a particular Will, that every asset falls within the document, that a registered Will avoids probate, or that one document will operate automatically in every country.',
                ],
            ],
            [
                'key' => 'detail',
                'type' => 'anchored_sections',
                'items' => [
                    [
                        'anchor' => 'difc',
                        'title' => 'DIFC Courts Wills',
                        'pill' => 'Reviewed before payment',
                        'body' => 'A specialist route operating under the DIFC Wills and Probate Registry Rules and Dubai\'s framework for non-Muslim Wills. Because DIFC has its own eligibility rules and its own professional-registration requirement for drafting assistance, DIFC matters are not processed through the standard online checkout. Engagements are quoted individually and currently start from AED {difc_fee} plus VAT.',
                        'columns' => [
                            [
                                'heading' => 'Current DIFC eligibility, per its published FAQ',
                                'items' => [
                                    'Not Muslim, and never having been Muslim',
                                    'At least 18 years old',
                                    'UAE assets, or qualifying minor children residing with the testator in the UAE',
                                    'A UAE residence visa is not required, and virtual registration is currently permitted',
                                ],
                            ],
                            [
                                'heading' => 'Will types currently presented by DIFC',
                                'items' => [
                                    'Full — movable and immovable assets, with guardianship where applicable',
                                    'Property — up to five qualifying UAE properties or shares in them',
                                    'Business Owners — up to five qualifying UAE shareholdings',
                                    'Financial Assets — up to ten qualifying UAE bank or brokerage accounts',
                                    'Guardianship — appointments without asset distribution under that Will type',
                                    'Digital Assets — a specialist option connected to the supported DIFC wallet',
                                ],
                            ],
                        ],
                        'footnote' => 'The available route, scope and portal requirements must be checked at the time of engagement.',
                    ],
                    [
                        'anchor' => 'adjd',
                        'title' => 'Abu Dhabi Civil Wills — ADJD',
                        'body' => 'ADJD states that its Civil Wills Office is available to a person who is not a UAE citizen, and that religion does not prevent access. Its current process provides a standard bilingual template as well as special or customised registration routes.',
                        'footnote' => 'Handled through the standard online pathway at AED {fee} plus VAT, with human legal review by Summit\'s legal team after payment to confirm the correct wording and structure.',
                    ],
                    [
                        'anchor' => 'dubai',
                        'title' => 'Dubai Courts Wills',
                        'body' => 'Dubai Law No. 15 of 2017 establishes a Dubai Courts register for the Wills of non-Muslims and sets rules on registration, implementation and estate administration.',
                        'footnote' => 'The current service channel, document, language and procedural requirements are confirmed for each proposed application as part of the standard service.',
                    ],
                    [
                        'anchor' => 'muslim',
                        'title' => 'Muslim Will pathway',
                        'body' => 'For Muslim residents, investors or asset owners who wish to make a Will within the rules applicable to them. A Will may record permitted gifts and appointments under the applicable Personal Status framework, and ADJD registration may be available to a person who is not a UAE citizen. Muslim clients use the same online process as everyone else: complete the instructions, pay the professional fee, and Summit\'s legal team applies the applicable rules during review and selects the registration authority.',
                        'footnote' => 'We do not state that any single framework applies automatically to every person. What applies depends on the individual circumstances and the authority\'s own requirements.',
                    ],
                    [
                        'anchor' => 'foreign',
                        'title' => 'Foreign Will involving UAE assets',
                        'body' => 'A Will already made in another country may remain relevant and form part of a coordinated international estate plan. It is reviewed alongside the new document through the standard online pathway.',
                        'footnote' => 'One document does not operate automatically in every country. Where a foreign Will exists, the review considers how the two documents sit together and whether anything needs revoking or amending.',
                    ],
                    [
                        'anchor' => 'identify',
                        'title' => 'How a route is identified',
                        'body' => 'Nationality, religion, residency, family circumstances, asset type and location, existing Wills and international connections may all affect the analysis. The assessment produces a preliminary indication; the legal review confirms it.',
                        'steps' => [
                            ['n' => '01', 'body' => 'The free assessment indicates a likely pathway from your answers.'],
                            ['n' => '02', 'body' => 'Human legal review examines the detail and confirms the recommended authority.'],
                            ['n' => '03', 'body' => 'The authority applies its own current requirements and decides.'],
                        ],
                    ],
                ],
                'settings' => ['anchors' => $this->pageData['WillOptions_ANCHORS']],
            ],
            [
                'key' => 'cta',
                'type' => 'cta',
                'heading' => 'You do not have to pick the route',
                'body' => 'Answer the assessment and the legal team identifies the appropriate framework. No account or payment is required to complete it.',
                'settings' => [
                    'primary' => ['label' => 'Start the assessment', 'href' => '/assessment'],
                    'secondary' => ['label' => 'See how the service works', 'href' => '/how-it-works'],
                ],
            ],
        ];
    }

    /**
     * Pricing.
     *
     * The DIFC figure is never rendered as a fixed purchasable price — it is
     * always "from", always quoted individually, and never payable online.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pricingSections(): array
    {
        return [
            [
                'key' => 'intro',
                'type' => 'page_intro',
                'heading' => 'Clear professional fees, with authority charges kept separate',
                'subheading' => 'Pricing',
                'body' => 'You should know what you are paying for before work begins. Our standard professional fee covers the preparation and legal review of one accepted standard UAE Will, its certified legal translation and assistance with submission to the competent authority.',
                'settings' => [
                    'second' => 'Government, court, registry, notary and other third-party charges are separate because they are set by the relevant authority or provider and may depend on the registration route confirmed after legal review.',
                ],
            ],
            [
                'key' => 'fee',
                'type' => 'fee_block',
                'heading' => 'Standard UAE Will · online pathway',
                'body' => 'The professional fee for one accepted standard Will proceeding through the online pathway.',
                'items' => [
                    [
                        'heading' => 'Included in the professional fee',
                        'items' => [
                            'The free preliminary assessment',
                            'Secure account creation and the detailed Will questionnaire',
                            'Review of the information and documents required for the accepted scope',
                            'Preparation of a tailored Will draft',
                            "Human legal review by Summit's legal team",
                            'Certified legal translation of the new UAE Will',
                            'The amendment allowance stated in the Service Confirmation shown before payment',
                            'Confirmation of the recommended registration route after legal review',
                            'Assistance preparing and submitting the application to the competent authority',
                            'Guidance on the next authority steps and the final document after registration',
                        ],
                    ],
                    [
                        'heading' => 'Not included unless expressly stated',
                        'items' => [
                            'Government, court or registry fees',
                            'Notary, certification or attestation charges',
                            'Courier, identity-verification or other third-party charges',
                            'Legalisation, recognition or translation of a separate foreign document',
                            'Foreign-law, tax, accounting, investment or financial advice',
                            'Litigation, probate, succession proceedings or estate administration after death',
                            'Trust, foundation, company restructuring or matrimonial work',
                            'Materially different or additional work outside the Service Confirmation',
                        ],
                        'footnote' => 'No additional professional fee is charged without your express approval.',
                    ],
                ],
                'settings' => [
                    'note' => 'For an accepted standard matter, we aim to send the first draft within {first_draft_days} business days after receiving complete, usable instructions and all required documents. A matter requiring specialist analysis, further clarification or additional documents may take longer.',
                ],
            ],
            [
                'key' => 'mirror_fee',
                'type' => 'fee_block',
                'heading' => 'Mirror Wills · two people, one pathway',
                'body' => "The total professional fee for a couple's two accepted standard Wills, prepared and reviewed together through the online pathway.",
                'items' => [
                    'The first person to pay covers the professional fee for both Wills — a package rate for the pair, not the standard fee charged twice.',
                    "Each person's Will remains a separate document and a separate authority registration, with its own personal approval before anything is submitted.",
                    'Everything included in the standard fee above — preparation, human legal review, certified translation, the amendment allowance and registration assistance — applies to both Wills.',
                ],
                'settings' => [
                    'note' => 'Authority charges normally apply separately to each Will, in the same way as for a single Will.',
                ],
            ],
            [
                'key' => 'difc',
                'type' => 'difc_block',
                'heading' => 'DIFC Will service',
                'items' => [
                    'DIFC engagements are quoted individually because DIFC Wills have specific eligibility, drafting, professional and registration requirements, and the appropriate scope depends on the Will type and circumstances.',
                    'A DIFC request is held for direct contact before checkout. Summit confirms whether it can accept the matter, explains the proposed scope and provides the quotation before any DIFC payment or work begins.',
                    'If the standard fee has already been paid and later legal review determines that a DIFC Will is required, the matter is paused and the full standard fee already paid is credited against the agreed DIFC professional fee. If you decline, the unused balance is refunded after deducting only a reasonable, documented amount for substantive work already completed.',
                ],
                'settings' => [
                    'pill' => 'Quoted individually',
                    'note' => 'This is never shown as a fixed purchasable price, and no DIFC matter can be paid for online.',
                    'cta' => ['label' => 'Contact our team', 'href' => '/contact'],
                ],
            ],
            [
                'key' => 'authority_fees',
                'type' => 'authority_fee_table',
                'heading' => 'Why court fees are shown separately',
                'subheading' => 'The authority — not UAE Expat Wills — sets and collects its registration or notarisation fee. The amount can change and may depend on the selected service, document or registration route.',
                'settings' => [
                    'columns' => ['Possible route', 'Current expected authority charge', 'Important note'],
                    'note' => 'These figures are not part of the AED {fee} professional fee and are not a promise that a particular route will apply. Before payment of our professional fee we identify the known categories and the current estimates available. After legal review confirms the recommended route, we explain the then-current authority fee before asking you to authorise or make that payment.',
                ],
            ],
            [
                'key' => 'terms',
                'type' => 'card_grid',
                'items' => [
                    [
                        'title' => 'Each person has a separate Will',
                        'body' => 'A couple does not share one Will. Each person gives and approves their own instructions, and each Will is a separate document and authority registration. The total professional fee and any package treatment is shown in the Service Confirmation or quotation before payment. Authority fees normally apply separately to each Will unless the authority expressly provides otherwise.',
                    ],
                    [
                        'title' => 'We explain any change before charging it',
                        'body' => 'Further work may be required if the detailed information reveals a different service, a foreign-law issue, a trust or company structure, a materially changed distribution plan, undisclosed litigation, or another issue outside the accepted scope. Summit pauses the affected work, explains what has changed and provides the proposed scope and fee. You may accept or decline; refunds are handled under the Payment and Refund Policy.',
                    ],
                    [
                        'title' => 'What you will see before you pay',
                        'list' => [
                            'The proposed service',
                            'The professional fee and VAT',
                            'What the fee includes',
                            'The amendment allowance',
                            'Known material exclusions',
                            'Separate charge categories and available estimates',
                            'Links to the Terms and Conditions and Payment and Refund Policy',
                        ],
                        'footnote' => 'Completing payment does not register the Will.',
                    ],
                ],
            ],
            [
                'key' => 'cta',
                'type' => 'cta',
                'heading' => 'Start with a free assessment',
                'body' => 'The assessment takes a few minutes and helps identify whether you can continue through the standard online pathway or should speak with the team first. No account or payment is required to complete it.',
                'settings' => [
                    'primary' => ['label' => 'Start the assessment', 'href' => '/assessment'],
                    'secondary' => ['label' => 'Contact our team', 'href' => '/contact'],
                    'aside' => 'Every accepted matter receives human legal review, you must approve the final draft, and registration or notarisation is completed only through the competent authority under its current requirements.',
                ],
            ],
        ];
    }

    /**
     * About Us — the two named consultants.
     *
     * The headshot slots are marked 3:4 and left empty. Open item 05: real
     * photographs are required and cannot be generated.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aboutSections(): array
    {
        return [
            [
                'key' => 'intro',
                'type' => 'page_intro',
                'heading' => 'The people who will read your instructions',
                'subheading' => 'About us',
                'body' => 'Every Will prepared through this platform is reviewed by one of the two consultants below. They are named because you are entitled to know who is doing the work.',
            ],
            [
                'key' => 'people',
                'type' => 'people',
                'items' => [
                    [
                        'name' => 'Ahmed Mohammedi',
                        'role' => 'Managing Director and Co-Founder',
                        'body' => [
                            'Ahmed leads Summit Legal Consultancy UAE and is responsible for how this platform accepts, prices and delivers a matter. His view is that the difference between a document and a Will is the judgement applied before it is signed.',
                            'He decides which matters the firm can properly accept, and which should be held for direct contact rather than pushed through an online checkout.',
                        ],
                        'facts' => [
                            ['label' => 'Role', 'value' => 'Managing Director and Co-Founder, Summit Legal Consultancy UAE'],
                            ['label' => 'Responsible for', 'value' => 'Matter acceptance, scope and fees; service delivery'],
                            ['label' => 'Licence', 'value' => '{trade_licence}'],
                        ],
                        'photo' => '/storage/profile/ahmed.jpeg',
                        'mirrored' => false,
                    ],
                    [
                        'name' => 'Dr. Mohamed Raouf',
                        'role' => 'Principal Legal Consultant and Co-Founder',
                        'body' => [
                            'Dr. Raouf leads the legal review that every Will passes through. He confirms the wording, checks that the instructions are internally consistent, and identifies which registration authority is appropriate before a client is asked to approve anything.',
                            'Where a distribution is mathematically or legally unclear, the work stops until it is corrected. That is the step an online form cannot perform.',
                        ],
                        'facts' => [
                            ['label' => 'Role', 'value' => 'Principal Legal Consultant and Co-Founder'],
                            ['label' => 'Responsible for', 'value' => 'Human legal review, drafting standards and authority selection'],
                            ['label' => 'Applies to', 'value' => 'Every Will, without exception'],
                        ],
                        'photo' => '/storage/profile/muhammad.jpeg',
                        'mirrored' => true,
                    ],
                ],
            ],
            [
                'key' => 'commitments',
                'type' => 'commitments',
                'heading' => 'What the firm commits to',
                'items' => [
                    'Human legal review on every single Will, without exception',
                    'One clear professional fee, with authority charges named separately',
                    'No payment requested while a matter is held for review',
                    'Nothing submitted anywhere until you have personally approved the wording',
                    'A named consultant accountable for the review of your document',
                ],
                'settings' => [
                    'note' => 'Summit Legal Consultancy UAE is not a court, registry, notary or government authority, and registration is completed by the competent authority under its own requirements.',
                ],
            ],
            [
                'key' => 'cta',
                'type' => 'cta',
                'heading' => 'Start with the assessment',
                'body' => 'The free assessment identifies the likely next step and takes a few minutes.',
                'settings' => [
                    'primary' => ['label' => 'Start the assessment', 'href' => '/assessment'],
                ],
            ],
        ];
    }

    /**
     * The homepage, section by section.
     *
     * All of it lives in the database rather than in the Vue component, because
     * the contract requires an administrator to be able to edit every word from
     * the content manager and see the change immediately. Copy is verbatim from
     * the design.
     *
     * @return array<int, array<string, mixed>>
     */
    private function homeSections(): array
    {
        return [
            [
                'key' => 'hero',
                'type' => 'hero',
                'heading' => 'Without a Will, the decision is not yours',
                'body' => 'Protect your family, children and assets through a guided digital process. Our legal team reviews your instructions, drafts your Will and confirms the correct registration authority before you approve anything.',
                'items' => [
                    ['name' => 'Ahmed Mohammedi', 'role' => 'Managing Director and Co-Founder', 'photo' => '/storage/profile/ahmed.jpeg'],
                    ['name' => 'Dr. Mohamed Raouf', 'role' => 'Principal Legal Consultant and Co-Founder', 'photo' => '/storage/profile/muhammad.jpeg'],
                ],
                'settings' => ['fee_label' => 'plus VAT · one accepted standard Will'],
            ],
            [
                'key' => 'reasons',
                'type' => 'card_grid',
                'heading' => 'Make Your Wishes Clear for the People and Assets That Matter',
                'subheading' => 'If you live, work, invest or own assets in the UAE, the rules that apply after your death may not produce the result you expect. A properly prepared Will can record your wishes and provide a clearer basis for the administration of your estate, subject to the applicable law and authority process.',
                'items' => [
                    ['title' => 'Who Receives Your Eligible Assets', 'body' => 'State how you want eligible property, financial assets, business interests and personal belongings to be distributed.'],
                    ['title' => 'Who Administers Your Estate', 'body' => 'Appoint an executor and an alternative to take the necessary estate-administration steps after your death.'],
                    ['title' => 'Who May Care for Your Children', 'body' => "Record guardianship wishes for minor children where the selected Will route permits, subject to applicable law and the competent court's decision."],
                    ['title' => 'What Happens if a First Choice Cannot Act', 'body' => 'Name alternative beneficiaries, executors or guardians in case your first choice dies, declines or is unable to act.'],
                ],
            ],
            [
                'key' => 'who',
                'type' => 'list_split',
                'heading' => 'Who should consider making or reviewing a UAE Will?',
                'body' => 'Not every person needs the same Will or registration route. The assessment identifies the likely next step without asking you to choose the legal pathway yourself.',
                'items' => [
                    'A parent of minor children',
                    'Part of a blended family or previous marriage',
                    'A UAE property owner, account holder or investor',
                    'Supporting an unmarried partner, stepchild, relative or charity',
                    'A business owner or shareholder',
                    'Holding assets or an existing Will in more than one country',
                    'A married couple preparing coordinated but separate Wills',
                    'A non-resident who owns UAE assets',
                ],
                'settings' => ['link' => ['label' => 'Read the full guide', 'href' => '/do-you-need-a-uae-will']],
            ],
            [
                'key' => 'process',
                'type' => 'steps',
                'heading' => 'From Free Assessment to Registration Assistance',
                'subheading' => "The full process runs to nine steps, grouped here into four stages. Each stage tells you who acts: you, Summit's legal team, or the competent authority.",
                'items' => [
                    ['n' => '01', 'title' => 'Complete the free assessment', 'body' => 'Answer a short set of questions about your UAE connection, family and the general nature of your assets. You do not need an account and no payment is required.'],
                    ['n' => '02', 'title' => 'Pay and provide your instructions', 'body' => 'Most clients — including Muslim clients, blended families, business owners and people with international connections — go straight to account creation, engagement terms and payment, then complete the detailed Will questionnaire. If your matter involves a DIFC Will, a capacity or influence concern, or an active legal dispute, we contact you directly before any payment is requested.'],
                    ['n' => '03', 'title' => 'Receive full legal review and drafting', 'body' => "Every Will receives human legal review by Summit's legal team, who confirm the appropriate wording and registration authority and prepare your draft. For an accepted standard matter, we aim to send the first draft within {first_draft_days} business days after receiving complete, usable instructions and all required documents."],
                    ['n' => '04', 'title' => 'Approve your Will and prepare for registration', 'body' => 'You review the draft, request the amendments included in your service and personally approve the final wording. We then assist with the applicable registration preparation and steps.'],
                ],
                'settings' => [
                    'caveat' => "Registration is completed by the competent authority. Identity verification, signing, attendance, payment of authority fees and other personal steps remain subject to that authority's current requirements.",
                    'link' => ['label' => 'See all nine steps', 'href' => '/how-it-works'],
                ],
            ],
            [
                'key' => 'trust',
                'type' => 'trust',
                'heading' => 'A Digital Process With a Legal Team Behind It',
                'subheading' => 'An online form on its own cannot recognise a previous marriage, children from different relationships, a company shareholding, jointly held property, an asset abroad or an existing Will. Our process is built to identify those issues and to put a legal professional in front of your document before you approve it.',
                'items' => [
                    ['title' => 'A licensed consultancy, not a document generator', 'body' => 'UAE Expat Wills is owned and operated by Summit Legal Consultancy UAE, Trade Licence No. 4429232.01. The platform delivers the digital process, while the legal services and human review are provided by Summit Legal Consultancy UAE.'],
                    ['title' => 'Human legal review on every single Will', 'body' => 'No document is treated as ready for your approval and registration until a legal professional has reviewed it — this applies to every client, not only complex cases.'],
                    ['title' => 'One clear professional fee', 'body' => 'AED {fee} plus VAT covers one accepted standard Will: preparation, human legal review, certified legal translation of the new UAE Will and assistance submitting the application to the competent authority. Government, court, registry, notary, certification, attestation, courier and identity-verification charges are separate unless expressly included. After legal review confirms the appropriate authority, we confirm the expected authority charge before you authorise submission or payment.'],
                    ['title' => 'Support that continues to registration and beyond', 'body' => 'We assist with the applicable registration steps rather than handing you a file, and we provide a route for future updates when your family, assets or wishes change.'],
                ],
            ],
            [
                'key' => 'pathways',
                'type' => 'pathways',
                'heading' => 'A Process Matched to Your Circumstances',
                'subheading' => 'Almost every matter runs through the standard online pathway. Three narrow categories are held for direct contact before any payment is taken.',
                'items' => [
                    [
                        'eyebrow' => 'Applies to almost everyone',
                        'title' => 'Standard Online Pathway',
                        'body' => 'Including Muslim clients, blended families, business owners and shareholders, people with existing Wills, and clients with assets or family connections in more than one country.',
                        'list' => [
                            'Complete the assessment, create your account and pay the professional fee — AED {fee} plus VAT',
                            'Secure detailed questionnaire',
                            "Full human legal review, drafting and authority selection by Summit's legal team",
                            'Client amendments and approval',
                            'Registration assistance within the selected service',
                        ],
                        'footnote' => 'The standard fee covers the scope shown before payment. If the detailed review identifies additional specialist work, a materially different service or a DIFC Will, we pause the matter, explain the reason and obtain your approval before any additional professional fee is charged.',
                    ],
                    [
                        'pill' => 'Reviewed before payment',
                        'title' => 'DIFC and Sensitive-Matter Pathway',
                        'body' => 'Applies where:',
                        'list' => [
                            'You specifically want a DIFC Will, or your circumstances indicate DIFC is the likely route',
                            'There is a concern about legal capacity or about pressure or influence from another person',
                            'There is an active legal dispute connected to the family, custody or estate',
                        ],
                        'footnote' => 'Summit contacts you directly to review the matter before confirming the service, route and price. DIFC engagements are quoted individually and currently start from AED {difc_fee} plus VAT.',
                        'emphasis' => 'No payment is taken while a matter is held for this review.',
                    ],
                ],
                'settings' => ['note' => 'You do not choose between these. Both begin with the same assessment.'],
            ],
            [
                'key' => 'routes',
                'type' => 'routes',
                'heading' => 'The Appropriate Route Depends on Your Circumstances',
                'subheading' => 'The UAE has more than one Will framework and registration process. A possible route may involve the DIFC Courts Wills Service, Abu Dhabi Judicial Department Civil Wills, Dubai Courts or another legally appropriate process.',
                'items' => [
                    ['title' => 'DIFC Courts Wills', 'body' => 'A specialist route for eligible people who are at least 18 years old, are not Muslim and have never been Muslim, and meet the relevant asset or minor-child requirements. The DIFC Courts website currently presents six Will options — Full, Property, Business Owners, Financial Assets, Guardianship and Digital Assets — each with its own scope and limits. DIFC engagements are handled through direct contact with our team rather than the standard online checkout.'],
                    ['title' => 'Abu Dhabi Civil Wills — ADJD', 'body' => "ADJD states that its Civil Wills Office is available to a person who is not a UAE citizen, regardless of religion, subject to its current requirements. Every case — Muslim or non-Muslim — receives full legal review from Summit's team as part of the standard service, after payment, to confirm the correct wording and structure."],
                    ['title' => 'Dubai Courts Wills', 'body' => 'Dubai law establishes a Dubai Courts register for the Wills of non-Muslims. The current service channel, document, language and procedural requirements are confirmed for each proposed application as part of the standard service.'],
                ],
                'settings' => [
                    'note' => 'Nationality, religion, residency, family circumstances, asset type and location, existing Wills and international connections may all affect the analysis. A preliminary route is not final until it has been reviewed, and official registration remains subject to the competent authority.',
                    'link' => ['label' => 'Compare UAE Will options', 'href' => '/uae-will-registration-options'],
                ],
            ],
            [
                'key' => 'cta',
                'type' => 'cta',
                'heading' => 'Start With Your Circumstances',
                'body' => 'You do not need to know which Will type or registration authority is appropriate before you begin. Complete the free initial assessment to identify the likely next step.',
                'settings' => [
                    'aside' => 'No account or payment is required to complete the initial assessment. Your result is preliminary and remains subject to the applicable acceptance and legal-review process.',
                    'primary' => ['label' => 'Start the assessment', 'href' => '/assessment'],
                    'secondary' => ['label' => 'Contact our team', 'href' => '/contact'],
                ],
            ],
        ];
    }

    /**
     * A legal page: an intro block plus its numbered clauses.
     *
     * Copy is verbatim. The contract forbids rewriting Summit's legal wording,
     * so nothing here is edited, shortened or rephrased.
     *
     * @return array<int, array<string, mixed>>
     */
    private function legalSections(string $key): array
    {
        $legal = $this->data['LEGAL'][$key] ?? null;

        if ($legal === null) {
            return [];
        }

        return [
            [
                'key' => 'intro',
                'type' => 'legal_intro',
                'heading' => $legal['title'] ?? null,
                'subheading' => $legal['eyebrow'] ?? null,
                'body' => $legal['intro'] ?? null,
                'settings' => ['updated' => $legal['updated'] ?? null],
            ],
            [
                'key' => 'clauses',
                'type' => 'legal_clauses',
                'items' => array_values(array_map(
                    fn (int $i, array $clause) => [
                        'number' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                        'title' => $clause[0],
                        'body' => $clause[1],
                        'anchor' => Str::slug($clause[0]),
                    ],
                    array_keys($legal['clauses'] ?? []),
                    $legal['clauses'] ?? [],
                )),
            ],
        ];
    }

    private function faqs(): void
    {
        Faq::query()->forceDelete();
        FaqCategory::query()->delete();

        foreach ($this->data['FAQ_CATS'] as $c => $category) {
            FaqCategory::create([
                'key' => $category['id'],
                'order' => ($c + 1) * 10,
                'label' => $category['label'],
                'locale' => 'en',
            ]);

            foreach ($category['qs'] as $q => [$question, $answer]) {
                Faq::create([
                    'category_key' => $category['id'],
                    'order' => ($q + 1) * 10,
                    'question' => $question,
                    'answer' => $answer,
                    'is_published' => true,
                    'locale' => 'en',
                ]);
            }
        }
    }
}
