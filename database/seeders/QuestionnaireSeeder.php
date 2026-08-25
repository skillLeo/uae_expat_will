<?php

namespace Database\Seeders;

use App\Domain\Assessment\Enums\ConditionAction;
use App\Domain\Assessment\Enums\ConditionOperator as Op;
use App\Domain\Assessment\Enums\Outcome;
use App\Domain\Assessment\Enums\QuestionType as QT;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\QuestionnaireDeclaration;
use App\Models\QuestionnaireResultScreen;
use App\Models\QuestionnaireVersion;
use App\Models\RoutingRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Final Qualifying Questionnaire — every question, option and routing rule,
 * seeded exactly as written in Part 5 of the master specification.
 *
 * Everything here is DATA. None of it is referenced by name anywhere in the
 * engine, so Summit can rewrite any of it from the admin rule builder without a
 * deploy — which is what the signed contract (Part B clause 5) requires.
 */
class QuestionnaireSeeder extends Seeder
{
    private QuestionnaireVersion $version;

    /** @var array<string, Question> */
    private array $q = [];

    public function run(): void
    {
        DB::transaction(function () {
            $questionnaire = Questionnaire::updateOrCreate(
                ['key' => 'screening'],
                [
                    'name' => 'Qualifying Assessment',
                    'type' => 'screening',
                    'description' => 'The free preliminary assessment. Decides whether a customer may continue to the standard online service or whether Summit should review the matter before the customer pays.',
                ],
            );

            // Rebuild version 1 from scratch on every seed run.
            $questionnaire->versions()->forceDelete();

            $this->version = $questionnaire->versions()->create([
                'version_number' => 1,
                'status' => 'published',
                'published_at' => now(),
                'notes' => 'Seeded from the Final Qualifying Questionnaire, "Final English version for the website journey".',
            ]);

            $this->questions();
            $this->visibility();
            $this->rules();
            $this->declarations();
            $this->resultScreens();
        });
    }

    // =====================================================================
    // QUESTIONS
    // =====================================================================

    private function questions(): void
    {
        $order = 0;

        $this->ask('q1', 'service', QT::SingleSelect, 'What service are you looking for today?', $order += 10, options: [
            'new_will' => 'Prepare a new Will for myself',
            'two_wills' => 'Two separate Wills for myself and my spouse or partner',
            'difc' => 'I specifically want a DIFC Will',
            'review_existing' => 'Review, amend, replace or revoke an existing Will',
            'estate_death' => 'Someone has died and I need help with their estate',
        ], help: 'Each person must provide their own instructions and approve their own Will separately.');

        $this->ask('q2', 'eligibility', QT::SingleSelect, 'Are you 18 years old or above?', $order += 10, options: [
            'yes' => 'Yes',
            'no' => 'No',
        ]);

        $this->ask('q3', 'eligibility', QT::CountrySelect, 'What is your nationality?', $order += 10,
            help: 'Nationality does not determine the registration authority by itself. Any second nationality, domicile or legal connection with another country will be collected later and reviewed by Summit.',
            privacy: 'The Will services available through this platform are not available to UAE citizens.');

        $this->ask('q4', 'about_you', QT::SingleSelect, 'Where do you currently live?', $order += 10, options: [
            'in_uae' => 'In the UAE',
            'outside_uae' => 'Outside the UAE',
        ]);

        $this->ask('q4a', 'about_you', QT::SingleSelect, 'Which Emirate do you live in?', $order += 10, options: [
            'abu_dhabi' => 'Abu Dhabi',
            'dubai' => 'Dubai',
            'sharjah' => 'Sharjah',
            'ajman' => 'Ajman',
            'umm_al_quwain' => 'Umm Al Quwain',
            'ras_al_khaimah' => 'Ras Al Khaimah',
            'fujairah' => 'Fujairah',
        ], help: 'The Emirate is relevant but does not select the registration authority by itself.');

        // Religion is sensitive: encrypted at rest, excluded from exports and
        // from analytics, and never sent to session replay.
        $this->ask('q5', 'about_you', QT::SingleSelect, 'What is your religion?', $order += 10, options: [
            'muslim' => 'Muslim',
            'non_muslim' => 'Non-Muslim',
            'previously_muslim' => 'I was previously Muslim',
            // The fourth option. Open item 01 — wording awaiting Summit's sign-off.
            'prefer_not_to_say' => 'I would prefer not to say',
        ], sensitive: true,
            privacy: 'Religion affects which registration routes may be available. The Dubai Courts route for non-Muslim Wills is not available to Muslims, while an ADJD Civil Will may be available to Muslim and non-Muslim applicants who are not UAE citizens. We use this answer only to assess the route and handle it in accordance with our Privacy Policy.');

        $this->ask('q6', 'family', QT::SingleSelect, 'What is your legal marital status?', $order += 10, options: [
            'never_married' => 'Never been married',
            'married_first' => 'Married, first and only marriage',
            'unmarried_partner' => 'Living with a partner or engaged, not married',
            'widowed' => 'Widowed, no unfinished estate or financial right',
            'married_before' => 'Married and was married before',
            'divorced' => 'Divorced',
            'separated' => 'Separated or divorce proceedings ongoing',
            'agreement' => 'Prenuptial or postnuptial agreement, settlement or claim between spouses',
            'unclear' => 'My position is different or unclear',
        ], sensitive: true);

        $this->ask('q6a', 'family', QT::SingleSelect, 'Do you want your unmarried partner to receive part of your estate?', $order += 10, options: [
            'no' => 'No',
            'yes_no_competing' => 'Yes — there is no existing marriage or competing claim',
            'yes_with_claim' => 'Yes — there is an existing marriage, obligation or competing claim',
            'not_sure' => 'Not sure',
        ], sensitive: true);

        $this->ask('q6b', 'family', QT::SingleSelect, 'Is there an unfinished estate, Will, jointly owned asset or financial right connected with your late spouse?', $order += 10, options: [
            'no' => 'No',
            'yes' => 'Yes',
            'not_sure' => 'Not sure',
        ], sensitive: true);

        $this->ask('q7', 'family', QT::MultiSelect, 'Do you have children or anyone financially dependent on you?', $order += 10, options: [
            'none' => 'No children, and nobody financially dependent on me',
            'all_current' => 'All children are from my current marriage or relationship',
            'adult_only' => 'Adult children only',
            'child_expected' => 'A child is expected',
            'adult_dependant' => 'An adult depends on me financially, with no disability or special care needs',
            'multiple_relationships' => 'Children from more than one marriage or relationship',
            'step_adopted' => 'Stepchildren, adopted children, children under kafala or foster children',
            'child_died' => 'One of my children has died and left children',
            'dependant_disability' => 'A dependant has a disability or long-term care needs',
            'not_sure' => 'Not sure about a child\'s legal status',
        ], exclusive: 'none', sensitive: true, help: 'Select everything that applies.');

        $this->ask('q8a', 'children', QT::SingleSelect, 'Is any child under 18, or does anyone require a legal guardianship arrangement?', $order += 10, options: [
            'no' => 'No',
            'yes' => 'Yes',
            'not_sure' => 'Not sure',
        ], sensitive: true);

        $this->ask('q8b', 'children', QT::SingleSelect, 'Where do the children live?', $order += 10, options: [
            'all_uae' => 'All in the UAE',
            'all_outside' => 'All outside the UAE',
            'mixed' => 'Some in the UAE, some outside',
            'not_sure' => 'Not sure',
        ], sensitive: true);

        $this->ask('q8c', 'children', QT::SingleSelect, 'Are you the legal parent of every child, with no existing or expected dispute concerning parentage, custody, guardianship, travel or parental authority?', $order += 10, options: [
            'yes' => 'Yes',
            'no_dispute' => 'No — there is or may be a dispute, judgment or restriction',
            'not_sure' => 'Not sure',
        ], sensitive: true);

        $this->ask('q8d', 'children', QT::SingleSelect, 'Are you able to nominate a guardian?', $order += 10, options: [
            'yes' => 'Yes, and no dispute is expected',
            'not_selected_yet' => 'Not selected yet, but I can during the detailed questionnaire',
            'no_suitable' => 'There is no suitable person',
            'may_be_dispute' => 'There may be a dispute',
            'not_sure' => 'Not sure',
        ], sensitive: true,
            help: 'A nomination in a Will records your wishes. It does not remove the rights of the other parent or the authority of the court. The child\'s welfare and the applicable law remain decisive.');

        $this->ask('q9', 'assets', QT::SingleSelect, 'Where are your assets?', $order += 10, options: [
            'uae_only' => 'In the UAE only',
            'guardianship_only' => 'No assets, but I want guardianship wishes for minor children living in the UAE',
            'uae_and_other' => 'In the UAE and in another country',
            'outside_only' => 'Outside the UAE only',
            'not_sure' => 'Not sure',
        ]);

        $this->ask('q10', 'uae_assets', QT::MultiSelect, 'What types of UAE assets do you have?', $order += 10, options: [
            'bank' => 'Bank accounts, deposits or cash',
            'real_estate' => 'Real estate',
            'listed_shares' => 'Listed shares or ordinary investment portfolios',
            'possessions' => 'Vehicles, jewellery or personal possessions',
            'end_of_service' => 'End-of-service benefits or employment entitlements',
            'registered_beneficiary' => 'An insurance policy, account or asset with a registered beneficiary',
            'crypto' => 'Cryptocurrency or another digital asset',
            'business' => 'A business, private company or share in a private company',
            'trust_owned' => 'An asset owned through a Trust, Foundation or company, or registered in another person\'s name',
            'ip' => 'Intellectual property, royalties or licensing rights',
            'other' => 'Another asset type, or not sure',
        ], help: 'Select everything that applies.');

        $this->ask('q10b', 'uae_assets', QT::SingleSelect, 'How should your digital assets be handled?', $order += 10, options: [
            'residue' => 'Part of the residue, with no special instructions',
            'specific_wallet' => 'A specific wallet to a specific person, or special access instructions',
            'complex' => 'Multiple owners, platforms, wallets or complicated arrangements',
            'not_sure' => 'Not sure',
        ], security: 'Never enter a password, seed phrase, recovery phrase or private key. We will never ask for one, and this platform does not store them.');

        $this->ask('q11', 'uae_assets', QT::MultiSelect, 'How are your assets owned?', $order += 10, options: [
            'sole_owned' => 'All assets are owned by me and registered in my name, even with an ordinary loan or mortgage',
            'joint_clear' => 'Jointly owned, my share is clear and documented, and the Will addresses my share only',
            'joint_unclear' => 'My share in a jointly owned asset is unclear or undocumented',
            'registered_other' => 'An asset is registered in the name of a company or another person',
            'trust_owned' => 'An asset is owned through a Trust or Foundation',
            'disputed' => 'An asset is disputed, attached, subject to third-party rights or restricted from transfer',
            'not_sure' => 'Not sure about my ownership or share',
        ], help: 'An ordinary bank loan or mortgage does not make a case complicated by itself.');

        $this->ask('q12', 'existing', QT::MultiSelect, 'Do you have any existing Wills or arrangements?', $order += 10, options: [
            'none' => 'None of these',
            'uae_will' => 'A Will or draft Will in the UAE',
            'foreign_will' => 'A Will or draft Will outside the UAE',
            'law_election' => 'I previously selected the law of a particular country to govern my estate',
            'agreement' => 'A marriage, family, shareholder or partnership agreement',
            'incomplete_gift' => 'An incomplete gift or transfer of ownership',
            'trust_owns' => 'A Trust, Foundation or company owns an asset',
            'other_arrangement' => 'Another arrangement for an asset to pass on death',
            'not_sure' => 'Not sure',
        ], exclusive: 'none');

        $this->ask('q13a', 'wishes', QT::MultiSelect, 'How do you want your estate distributed?', $order += 10, options: [
            'to_family' => 'Everything to my spouse, my children, or divided between them',
            'fixed_percentages' => 'Simple fixed percentages between named people',
            'specific_gift' => 'A particular asset or amount to a named person, with the balance distributed afterwards',
            'different_percentages' => 'Different percentages between my children',
            'gift_to_friend' => 'A simple gift to a friend or relative',
            'charity' => 'A gift to a charity, foundation or institution',
            'names_later' => 'I know what I want and will enter names and percentages later',
            'need_advice' => 'Not decided — I need legal advice about distribution',
            'exclude_someone' => 'I want to exclude someone who may expect to inherit',
            'conditions' => 'I want to impose conditions on when or how a beneficiary receives an inheritance',
            'trust_arrangement' => 'I want an arrangement involving a Trust, Foundation or company',
            'other' => 'Another wish not listed',
        ], sensitive: true, help: 'Select everything that applies.');

        // More than one of these can be true at once: a beneficiary can be a
        // minor AND have care needs. One answer made people pick the most
        // serious and lose the rest.
        $this->ask('q13b', 'wishes', QT::MultiSelect, 'Does any beneficiary need protection?', $order += 10, options: [
            'no' => 'No',
            'minor' => 'A beneficiary is a minor with no other special needs',
            'disability' => 'A beneficiary has a disability or long-term care needs',
            'cannot_manage' => 'A beneficiary may not be able to manage money, or there are financial or personal concerns',
            'delay' => 'I want to delay or stage the inheritance',
            'other_protective' => 'Another protective arrangement is required',
            'not_sure' => 'Not sure',
        ], exclusive: 'no', sensitive: true);

        $this->ask('q14', 'executor', QT::SingleSelect, 'Can you appoint an executor?', $order += 10, options: [
            'yes_with_substitute' => 'Yes — a suitable adult and a substitute, with no dispute expected',
            'no_substitute' => 'I have a suitable person but no substitute yet',
            'names_later' => 'I have not chosen names yet, but can during the detailed questionnaire',
            'professional' => 'I want to appoint a company, institution or professional executor',
            'several' => 'I want several executors to act together',
            'conflict' => 'There may be a conflict of interest or a dispute about the appointment',
            'none_suitable' => 'There is no suitable person',
            'not_sure' => 'Not sure about the role or the right person',
        ], help: 'We do not need the person\'s name during the initial assessment. We only need to know whether the role can be arranged clearly.');

        // Q15B is the capacity and undue-influence question. Its answer creates a
        // RESTRICTED case. The answer must never be disclosed to the person who
        // may be influencing the customer, so nothing about it appears in any
        // notification, list, export or search result.
        $this->ask('q15b', 'circumstances', QT::SingleSelect, 'Do you understand the nature and effect of a Will, and are you making your decisions freely, without pressure from another person?', $order += 10, options: [
            'yes' => 'Yes',
            'health_condition' => 'A health or personal condition may affect my ability to understand or decide',
            'someone_helping' => 'Another person is helping me and may influence my choices',
            'feel_pressured' => 'I feel pressured or influenced',
            'no_or_unsure' => 'No, or not sure',
        ], sensitive: true);

        $this->ask('q16', 'language', QT::SingleSelect, 'Can you complete the process in English?', $order += 10, options: [
            'yes' => 'Yes, I can complete it in English',
            'simple_explanation' => 'Yes, but I may need a simple explanation',
            'arabic' => 'I need assistance in Arabic',
            'interpreter' => 'I need an interpreter or another language',
        ], help: 'The website is currently available in English. Our team can assist in Arabic and will explain whether an interpreter or additional translation is required by the chosen registration authority. Asking for language assistance does not mean that your case is legally complex.');
    }

    /** @param array<string, string> $options */
    private function ask(
        string $key,
        string $section,
        QT $type,
        string $prompt,
        int $order,
        array $options = [],
        ?string $exclusive = null,
        bool $sensitive = false,
        ?string $help = null,
        ?string $privacy = null,
        ?string $security = null,
    ): Question {
        $question = $this->version->questions()->create([
            'key' => $key,
            'order' => $order,
            'type' => $type,
            'prompt' => $prompt,
            'help_text' => $help,
            'privacy_note' => $privacy,
            'security_note' => $security,
            'is_required' => true,
            'is_sensitive' => $sensitive,
            'section_key' => $section,
        ]);

        $i = 0;

        foreach ($options as $optionKey => $label) {
            $question->options()->create([
                'key' => $optionKey,
                'order' => $i += 10,
                'label' => $label,
                'is_exclusive' => $optionKey === $exclusive,
            ]);
        }

        return $this->q[$key] = $question->load('options');
    }

    // =====================================================================
    // CONDITIONAL VISIBILITY
    // =====================================================================

    private function visibility(): void
    {
        $this->showWhen('q4a', 'q4', Op::Equals, 'in_uae');
        $this->showWhen('q6a', 'q6', Op::Equals, 'unmarried_partner');
        $this->showWhen('q6b', 'q6', Op::Equals, 'widowed');

        // Q8 opens whenever any child option was selected in Q7.
        $childOptions = ['all_current', 'adult_only', 'child_expected', 'multiple_relationships', 'step_adopted', 'child_died', 'not_sure'];
        $this->showWhen('q8a', 'q7', Op::In, $childOptions);

        foreach (['q8b', 'q8c', 'q8d'] as $key) {
            $this->showWhen($key, 'q8a', Op::Equals, 'yes');
        }

        // The whole UAE-assets section is skipped when the request is
        // guardianship-only. This is a section skip rather than three separate
        // hide conditions, so adding a question to the section later inherits it.
        $this->version->questions()->where('key', 'q10')->first()?->conditions()->create([
            'depends_on_question_id' => $this->q['q9']->id,
            'operator' => Op::Equals,
            'value' => 'guardianship_only',
            'action' => ConditionAction::SkipSection,
            'target_section_key' => 'uae_assets',
        ]);

        $this->showWhen('q10b', 'q10', Op::In, ['crypto']);
        $this->showWhen('q13b', 'q13a', Op::In, ['charity', 'fixed_percentages', 'specific_gift', 'different_percentages']);
    }

    private function showWhen(string $questionKey, string $dependsOn, Op $operator, mixed $value): void
    {
        $this->q[$questionKey]->conditions()->create([
            'depends_on_question_id' => $this->q[$dependsOn]->id,
            'operator' => $operator,
            'value' => $value,
            'action' => ConditionAction::Show,
        ]);
    }

    // =====================================================================
    // ROUTING RULES
    // =====================================================================

    private function rules(): void
    {
        // --- Terminal. These exit the assessment where they stand. ----------
        $this->rule('R-01 · Estate administration enquiry', 10, Outcome::StopRefer, terminal: true,
            detail: 'This relates to the administration of an estate after death, rather than the preparation of a new Will. Our team will need to review the matter as a separate legal service.',
            conditions: [['q1', Op::Equals, 'estate_death']]);

        $this->rule('R-02 · Under 18', 20, Outcome::StopIneligible, terminal: true,
            detail: 'You cannot continue through the online Will preparation service because the person making the Will must be at least 18 years old.',
            conditions: [['q2', Op::Equals, 'no']]);

        $this->rule('R-03 · UAE citizen', 30, Outcome::StopIneligible, terminal: true,
            detail: 'The Will services available through this platform are not intended for UAE citizens. You may contact Summit if you require a different legal service.',
            conditions: [['q3', Op::Equals, 'AE']]);

        // --- Urgent review. Highest precedence, but NOT terminal: Q15B is the
        //     last question, so the journey has already run its course. --------
        $this->rule('R-04 · Capacity or undue influence', 40, Outcome::UrgentReview,
            detail: 'Restricted — authorised legal staff only.',
            conditions: [['q15b', Op::In, ['health_condition', 'someone_helping', 'feel_pressured', 'no_or_unsure']]]);

        // --- Enhanced review. These no longer stop the customer. --------------
        //
        // Per the approved result-screen handoff, August 2026: the distinction
        // between a standard and an enhanced review is an INTERNAL classification
        // and "must not remove the payment option from an ADJD or Dubai Courts
        // candidate". So every rule below flags the case for Summit's attention
        // and lets the customer continue to payment as normal.
        //
        // Two things are deliberately not in this list. DIFC (R-05) still needs a
        // quotation before any money is taken, and the capacity and undue
        // influence rule (R-04) remains an urgent review that is never charged.
        $this->rule('R-05 · DIFC Will requested', 50, Outcome::Review,
            conditions: [['q1', Op::Equals, 'difc']]);

        $this->rule('R-06 · Existing Will to review, amend or revoke', 60, Outcome::StopRefer, terminal: true,
            detail: 'Reviewing, amending, replacing or revoking an existing Will is handled directly by our team rather than through the online assessment. Leave your details and Summit will contact you.',
            conditions: [['q1', Op::Equals, 'review_existing']]);

        $this->rule('R-07 · Religion requires review', 70, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q5', Op::In, ['previously_muslim', 'prefer_not_to_say']]]);

        $this->rule('R-08 · Marital history', 80, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q6', Op::In, ['married_before', 'divorced', 'separated', 'agreement', 'unclear']]]);

        $this->rule('R-08a · Unmarried partner with a competing claim', 81, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q6a', Op::In, ['yes_with_claim', 'not_sure']]]);

        $this->rule('R-08b · Unfinished estate of a late spouse', 82, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q6b', Op::In, ['yes', 'not_sure']]]);

        $this->rule('R-09 · Blended family or dependant with care needs', 90, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q7', Op::In, ['multiple_relationships', 'step_adopted', 'child_died', 'dependant_disability', 'not_sure']]]);

        $this->rule('R-09a · Guardianship position unclear', 91, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q8a', Op::Equals, 'not_sure']]);

        $this->rule('R-09b · Children living outside the UAE', 92, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q8b', Op::In, ['all_outside', 'mixed', 'not_sure']]]);

        $this->rule('R-09c · Parental responsibility dispute', 93, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q8c', Op::In, ['no_dispute', 'not_sure']]]);

        $this->rule('R-09d · No suitable guardian, or a dispute expected', 94, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q8d', Op::In, ['no_suitable', 'may_be_dispute', 'not_sure']]]);

        $this->rule('R-09e · Assets outside the UAE', 95, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q9', Op::In, ['uae_and_other', 'outside_only', 'not_sure']]]);

        $this->rule('R-10 · Business, Trust or complex asset', 100, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q10', Op::In, ['business', 'trust_owned', 'ip', 'other']]]);

        $this->rule('R-10b · Digital assets needing special handling', 102, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q10b', Op::In, ['specific_wallet', 'complex', 'not_sure']]]);

        $this->rule('R-10c · Ownership unclear or restricted', 103, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q11', Op::In, ['joint_unclear', 'registered_other', 'trust_owned', 'disputed', 'not_sure']]]);

        $this->rule('R-11 · Existing Will, law election or structure', 110, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q12', Op::In, ['uae_will', 'foreign_will', 'law_election', 'agreement', 'incomplete_gift', 'trust_owns', 'other_arrangement', 'not_sure']]]);

        // ------------------------------------------------------------------
        // THE CROSS-QUESTION RULES.
        //
        // These are the ones a flat lookup table cannot express: the outcome of
        // Q13A depends on the answer to Q5, given many screens earlier. Both
        // conditions share group_index 0, so they AND together.
        //
        // The rule reads: IF the distribution wish needs a feature only the
        // Dubai Courts route provides AND the customer is Muslim — for whom that
        // route is not available — THEN review before payment.
        // ------------------------------------------------------------------
        $wider = ['specific_gift', 'different_percentages', 'gift_to_friend'];

        $this->rule('R-12 · Distribution needs the wider route, and the customer is Muslim', 120, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            detail: 'An instruction of this kind may require a feature available only through the Dubai Courts route, which is not available to a Muslim customer. Summit will review this and confirm the appropriate route before your Will is drafted.',
            conditions: [
                ['q13a', Op::In, $wider, 0],
                ['q5', Op::Equals, 'muslim', 0],
            ]);

        $this->rule('R-12a · Distribution needs the wider route, non-Muslim', 121, Outcome::ContinueRouteMark,
            routeMark: 'wider_dubai_route',
            conditions: [
                ['q13a', Op::In, $wider, 0],
                ['q5', Op::Equals, 'non_muslim', 0],
            ]);

        $this->rule('R-12b · Unmarried partner inheriting, and the customer is Muslim', 122, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [
                ['q6a', Op::Equals, 'yes_no_competing', 0],
                ['q5', Op::Equals, 'muslim', 0],
            ]);

        $this->rule('R-12c · Unmarried partner inheriting, non-Muslim', 123, Outcome::ContinueRouteMark,
            routeMark: 'wider_dubai_route',
            conditions: [
                ['q6a', Op::Equals, 'yes_no_competing', 0],
                ['q5', Op::Equals, 'non_muslim', 0],
            ]);
        // ------------------------------------------------------------------

        $this->rule('R-13 · Distribution requiring advice or a structure', 130, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q13a', Op::In, ['charity', 'need_advice', 'exclude_someone', 'conditions', 'trust_arrangement', 'other']]]);

        $this->rule('R-13a · Beneficiary needing protection', 131, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q13b', Op::In, ['disability', 'cannot_manage', 'delay', 'other_protective', 'not_sure']]]);

        $this->rule('R-14 · Executor arrangement needs review', 140, Outcome::ContinueFlag,
            flag: 'enhanced_review',
            conditions: [['q14', Op::In, ['professional', 'several', 'conflict', 'none_suitable', 'not_sure']]]);

        // --- Continue, with something recorded on the case -------------------
        $this->rule('F-01 · Expected child', 200, Outcome::ContinueReminder,
            reminder: 'expected_child',
            detail: 'We will collect additional information later so that the legal team can review how an expected or future child should be addressed in the Will.',
            conditions: [['q7', Op::In, ['child_expected']]]);

        $this->rule('F-02 · Guardian to be nominated later', 201, Outcome::ContinueReminder,
            reminder: 'guardian_nomination',
            conditions: [['q8d', Op::Equals, 'not_selected_yet']]);

        $this->rule('F-03 · Guardianship-only matter', 202, Outcome::ContinueFlag,
            flag: 'guardianship_only',
            conditions: [['q9', Op::Equals, 'guardianship_only']]);

        $this->rule('F-05 · Digital assets in the residue', 204, Outcome::ContinueFlag,
            flag: 'digital_assets',
            conditions: [['q10b', Op::Equals, 'residue']]);

        $this->rule('F-06 · Jointly owned asset', 205, Outcome::ContinueFlag,
            flag: 'joint_ownership',
            detail: 'A Will can address only your legal share in a jointly owned asset, not the entire asset. Summit will review the ownership information before drafting.',
            conditions: [['q11', Op::In, ['joint_clear']]]);

        $this->rule('F-07 · Minor beneficiary', 206, Outcome::ContinueFlag,
            flag: 'minor_beneficiary',
            conditions: [['q13b', Op::In, ['minor']]]);

        $this->rule('F-08 · Executor names to follow', 207, Outcome::ContinueReminder,
            reminder: 'executor_names',
            conditions: [['q14', Op::In, ['no_substitute', 'names_later']]]);

        $this->rule('F-09 · Two Wills requested', 208, Outcome::ContinueFlag,
            flag: 'two_wills',
            detail: 'Each person must provide their own instructions and approve their own Will separately.',
            conditions: [['q1', Op::Equals, 'two_wills']]);

        $this->rule('F-10 · Language assistance may be required', 209, Outcome::ContinueFlag,
            flag: 'language_assistance',
            conditions: [['q16', Op::In, ['simple_explanation']]]);

        $this->rule('F-11 · Arabic assistance required', 210, Outcome::ContinueFlag,
            flag: 'arabic_assistance',
            conditions: [['q16', Op::Equals, 'arabic']]);

        $this->rule('F-12 · Interpreter required', 211, Outcome::ContinueFlag,
            flag: 'interpreter_required',
            conditions: [['q16', Op::Equals, 'interpreter']]);

        // R-16 — "no other rule matched → continue online" — is deliberately NOT
        // a row. It is the absence of any match, so a catch-all rule can never be
        // reordered above a stop by an accidental priority edit.
    }

    /** @param array<int, array{0: string, 1: Op, 2: mixed, 3?: int}> $conditions */
    private function rule(
        string $name,
        int $priority,
        Outcome $outcome,
        array $conditions,
        ?string $detail = null,
        ?string $flag = null,
        ?string $reminder = null,
        ?string $routeMark = null,
        bool $terminal = false,
    ): RoutingRule {
        $rule = $this->version->routingRules()->create([
            'name' => $name,
            'priority' => $priority,
            'outcome' => $outcome,
            'outcome_detail' => $detail,
            'flag_key' => $flag,
            'reminder_key' => $reminder,
            'route_mark_key' => $routeMark,
            'is_terminal' => $terminal,
            'is_active' => true,
        ]);

        foreach ($conditions as $condition) {
            [$questionKey, $operator, $value] = $condition;

            $rule->conditions()->create([
                'question_id' => $this->q[$questionKey]->id,
                'operator' => $operator,
                'value' => $value,
                'group_index' => $condition[3] ?? 0,
                'group_operator' => 'and',
            ]);
        }

        return $rule;
    }

    // =====================================================================
    // DECLARATIONS AND RESULT SCREENS
    // =====================================================================

    private function declarations(): void
    {
        $declarations = [
            'I confirm that my answers are true and complete to the best of my knowledge.',
            'I understand that the assessment result is preliminary and is not a final legal opinion or a decision by a registration authority.',
            'I understand that completing the assessment does not create or register a Will.',
            'I understand that the registration authority will not be confirmed until I complete the detailed questionnaire and Summit reviews my information.',
            'I understand that paying Summit\'s fees or receiving a draft does not mean that the Will has been registered.',
            'I understand that government, court, notary and third-party fees are separate, and that the legal translation of the new UAE Will for the recommended standard route is included in Summit\'s professional fee.',
            'I agree that my answers may be used for assessment and service delivery in accordance with the Privacy Policy.',
        ];

        foreach ($declarations as $i => $text) {
            QuestionnaireDeclaration::create([
                'questionnaire_version_id' => $this->version->id,
                'order' => ($i + 1) * 10,
                'text' => $text,
                'is_required' => true,
            ]);
        }
    }

    private function resultScreens(): void
    {
        $screens = [
            [
                // Copy below is transcribed from the approved developer content
                // handoff, August 2026, and must not be reworded here. The fee
                // is a token so the screen can never quote a price the platform
                // has stopped charging.
                Outcome::Continue_,
                'Continue with Our Online UAE Will Service',
                'Based on your answers, you can continue with our standard online UAE Will service.'
                ."\n\n".'After payment, you will complete the detailed Will questionnaire and provide the information required for preparing your Will. Summit Legal Consultancy will then review your circumstances, instructions and supporting information before preparing your draft.'
                ."\n\n".'Our legal team will select the registration route (ADJD or Dubai Courts) that best fits your circumstances and helps provide the strongest protection for your wishes.'
                ."\n\n".'Every Will is reviewed by our legal team before the draft is sent to you for approval.',
                'Continue and Pay {currency} {total_2dp}',
                'I Have a Question Before Paying',
                [
                    'eyebrow' => 'You can continue online',
                    'callout_heading' => 'Your assessment is complete. Your registration route is now under legal review.',
                    'callout_body' => 'You may proceed with payment now. After you provide your detailed instructions, Summit will review your matter, prepare the draft and recommend the appropriate registration authority.',
                    'includes_heading' => 'What the professional fee includes',
                    'includes' => [
                        ['Will preparation', 'Preparation of one standard UAE Will.'],
                        ['Legal review', 'Mandatory human legal review of the client\'s information and instructions.'],
                        ['Legal translation', 'Legal translation of the standard UAE Will.'],
                        ['Route recommendation', 'Recommendation of the appropriate ADJD or Dubai Courts registration route.'],
                        ['Submission assistance', 'Assistance with submitting the approved Will to the relevant authority.'],
                        ['Amendments', 'Reasonable amendments before final approval, subject to the service terms.'],
                    ],
                    'authority_fees_note' => 'Government, court, registry, notary and other third-party charges are separate and will be explained after we confirm the appropriate registration route.',
                    'reassurance' => 'Secure payment · Mandatory legal review included',
                    'notice_heading' => 'Payment does not mean that your Will has been prepared, approved or registered. Registration takes place only after legal review, preparation of the draft, your approval and completion of the competent authority\'s requirements.',
                    'notice_body' => 'If our review identifies an issue requiring additional information, specialised advice or a different service, we will contact you before proceeding further.',
                    'muslim_note' => 'Based on your answers, the ADJD Civil Will route may be available. Summit will confirm this after reviewing your complete information.',
                    'non_muslim_note' => 'Based on your answers, registration through ADJD or Dubai Courts may be available. Summit will recommend the route that best matches your instructions after review.',

                    // Shown instead of the above when question one asked for two
                    // Wills. Same screen, its own approved wording and its own price.
                    'mirror' => [
                        'heading' => 'Continue with Our Online Mirror Wills Service',
                        'subheading' => 'Two coordinated but legally separate standard UAE Wills',
                        'body' => 'Based on your answers, you and your spouse or partner can continue with our online Mirror Wills service for two coordinated but legally separate standard UAE Wills.'
                            ."\n\n".'After payment, you will provide the detailed instructions required for each Will. Summit Legal Consultancy will review both clients\' circumstances, instructions and supporting information before preparing the two drafts.'
                            ."\n\n".'Our legal team will confirm the appropriate registration route for each Will (ADJD or Dubai Courts), based on your circumstances and to help provide strong protection for both of your wishes.'
                            ."\n\n".'Each Will remains a separate legal document. Each person must review and approve their own draft before registration.',
                        'callout_heading' => 'Your joint assessment is complete. Both Wills are now subject to mandatory legal review.',
                        'callout_body' => 'You may proceed with payment now. After receiving complete instructions from both clients, Summit will review the matter, prepare the two drafts and confirm the appropriate registration route for each Will.',
                        'includes' => [
                            ['Two Will preparations', 'Preparation of two coordinated but legally separate standard UAE Wills.'],
                            ['Legal review', 'Mandatory human legal review of both clients\' information and instructions.'],
                            ['Legal translation', 'Legal translation of both standard UAE Wills.'],
                            ['Route recommendation', 'Recommendation of the appropriate ADJD or Dubai Courts registration route for each Will.'],
                            ['Submission assistance', 'Assistance with submitting both approved Wills to the relevant authority or authorities.'],
                            ['Amendments', 'Reasonable amendments to each draft before final approval, subject to the service terms.'],
                        ],
                        'authority_fees_note' => 'Government, court, registry, notary and other third-party charges are separate and may be charged for each Will. They will be explained after we confirm the appropriate registration route for each client.',
                        'primary_action_label' => 'Continue and Pay {currency} {mirror_total_2dp}',
                        'notice_heading' => 'Payment does not mean that either Will has been prepared, approved or registered. Each Will is a separate legal document and each client must review and approve their own draft before registration.',
                        'notice_body' => 'Registration takes place only after legal review, preparation of both drafts, each client\'s approval and completion of the relevant authority\'s requirements. If our review identifies that DIFC registration, specialised advice or a different service may be more appropriate for either client, we will contact you before proceeding further.',
                    ],
                ],
            ],
            [
                Outcome::Review,
                'We would like our legal team to review your circumstances first',
                'One or more of your answers indicates that your circumstances should be reviewed before we recommend a service, registration authority or price. This is not a rejection. A DIFC Will, a customised Will, coordination with a foreign Will or foreign law, or another legal service may be more suitable. Please leave your contact details and a short summary. Summit will review the matter. No payment is required at this stage.',
                'Send for review',
                null,
                ['collect_contact' => true],
            ],
            [
                // The reason is deliberately absent. This screen is written so it
                // is safe to be read over the customer's shoulder by the very
                // person who may be influencing them.
                Outcome::UrgentReview,
                'Thank you — our legal team will contact you directly',
                'Thank you for completing the assessment. Our legal team would like to speak with you personally before recommending a service. This is not a rejection, and there is nothing to pay at this stage. A member of the team will contact you using the details you provide.',
                'Leave my contact details',
                null,
                ['collect_contact' => true, 'suppress_reason' => true],
            ],
            [
                Outcome::StopRefer,
                'This needs a different legal service',
                'This relates to the administration of an estate after death, rather than the preparation of a new Will. Our team will need to review the matter as a separate legal service. Please leave your details and we will contact you.',
                'Contact our team',
                null,
                ['collect_contact' => true],
            ],
            [
                Outcome::StopIneligible,
                'The online Will service is not available for your circumstances',
                'Based on your answers, you cannot continue through the online Will preparation service. This does not mean that nothing can be done — you may contact Summit Legal Consultancy if you require a different legal service.',
                'Contact our team',
                null,
                ['no_payment' => true],
            ],
        ];

        foreach ($screens as [$outcome, $heading, $body, $primary, $secondary, $extra]) {
            QuestionnaireResultScreen::create([
                'questionnaire_version_id' => $this->version->id,
                'outcome' => $outcome,
                'heading' => $heading,
                'body' => $body,
                'primary_action_label' => $primary,
                'secondary_action_label' => $secondary,
                'extra' => $extra,
            ]);
        }
    }
}
