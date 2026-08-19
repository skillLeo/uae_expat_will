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

    public function run(): void
    {
        $this->data = json_decode(file_get_contents(__DIR__.'/data/content.json'), true);

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
            ['pricing', '/pricing', 'Pricing — Clear Professional Fees, Authority Charges Kept Separate', 'AED 2,199 plus VAT for one accepted standard Will. Government, court and notary charges are shown separately because the authority sets them.', 'Pricing', 5],
            ['faqs', '/faqs', 'Frequently Asked Questions', '57 questions about UAE Wills, fees, eligibility, registration, guardianship, privacy and what happens after registration.', 'FAQs', 6],
            ['about', '/about-us', 'About Us — Summit Legal Consultancy UAE', 'UAE Expat Wills is owned and operated by Summit Legal Consultancy UAE, Trade Licence No. 4429232.01.', 'About Us', 7],
            ['contact', '/contact', 'Contact', 'Contact Summit Legal Consultancy UAE by email or WhatsApp. Please do not send matter detail, identity documents or asset information by message.', 'Contact', 8],
            ['terms', '/terms-and-conditions', 'Terms and Conditions', 'The terms on which UAE Expat Wills and Summit Legal Consultancy UAE provide the platform and its legal services.', 'Terms and Conditions', 9],
            ['privacy', '/privacy-policy', 'Privacy Policy', 'How UAE Expat Wills collects, uses, stores and protects your personal information, including sensitive assessment answers.', 'Privacy Policy', 10],
            ['refund', '/payment-and-refund-policy', 'Payment and Refund Policy', 'What the professional fee covers, what is charged separately, and the refund bands that apply at each stage of the work.', 'Payment and Refund Policy', 11],
            ['disclaimer', '/legal-disclaimer', 'Legal Disclaimer', 'UAE Expat Wills and Summit Legal Consultancy UAE are not a court, registry, notary or government authority.', 'Legal Disclaimer', 12],
            ['cookies', '/cookie-policy', 'Cookie Policy', 'The cookies this site sets, what each category does, and how to change your choices at any time.', 'Cookie Policy', 13],
        ];

        foreach ($pages as [$key, $slug, $seoTitle, $description, $breadcrumb, $order]) {
            $page = Page::updateOrCreate(
                ['key' => $key],
                [
                    'slug' => $slug,
                    'title' => $breadcrumb,
                    'seo_title' => $seoTitle,
                    'meta_description' => $description,
                    'breadcrumb' => $breadcrumb,
                    'is_published' => true,
                    'published_at' => now(),
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
                    ['name' => 'Ahmed Mohammedi', 'role' => 'Managing Director and Co-Founder'],
                    ['name' => 'Dr. Mohamed Raouf', 'role' => 'Principal Legal Consultant and Co-Founder'],
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
