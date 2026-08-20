<?php

namespace Database\Seeders;

use App\Domain\Assessment\Enums\ConditionAction;
use App\Domain\Assessment\Enums\ConditionOperator as Op;
use App\Domain\Assessment\Enums\Outcome;
use App\Domain\Assessment\Enums\QuestionType as QT;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The detailed Will questionnaire — Stage 5.
 *
 * Content areas are the ones the specification fixes: personal and
 * identification details, spouse or partner, children and dependants,
 * beneficiaries and substitutes, intended estate-distribution instructions,
 * executor and alternate, guardians, and asset information.
 *
 * It runs on the SAME engine as the screening assessment, so conditional
 * visibility, save-and-resume and server-side revalidation all come for free.
 * Its routing rules flag matters for the reviewer rather than routing to a
 * result screen — by this stage the customer has already been accepted and
 * paid, so there is nothing left to triage them into.
 *
 * The gate is enforced in the controller, not here: the specification is
 * explicit that this must not open before acceptance, engagement and payment.
 */
class DetailedQuestionnaireSeeder extends Seeder
{
    private QuestionnaireVersion $version;

    /** @var array<string, Question> */
    private array $q = [];

    public function run(): void
    {
        DB::transaction(function () {
            $questionnaire = Questionnaire::updateOrCreate(
                ['key' => 'detailed'],
                [
                    'name' => 'Detailed Will Questionnaire',
                    'type' => 'detailed',
                    'description' => 'Collected after acceptance, engagement and payment. This is where the customer gives their actual instructions.',
                ],
            );

            $questionnaire->versions()->forceDelete();

            $this->version = $questionnaire->versions()->create([
                'version_number' => 1,
                'status' => 'published',
                'published_at' => now(),
                'notes' => 'Seeded from the content areas fixed in Stage 5 of the master specification.',
            ]);

            $this->questions();
            $this->visibility();
            $this->rules();
            $this->declarations();
            $this->resultScreens();
        });
    }

    private function questions(): void
    {
        $o = 0;

        // ---------------------------------------------- personal identification
        $this->ask('d_full_name', 'identity', QT::Text, 'What is your full legal name, exactly as it appears on your passport?', $o += 10,
            help: 'This must match your passport character for character. A mismatch is the most common reason an authority rejects a submission.');

        $this->ask('d_passport_number', 'identity', QT::Text, 'What is your passport number?', $o += 10, sensitive: true);

        $this->ask('d_emirates_id', 'identity', QT::Text, 'What is your Emirates ID number, if you have one?', $o += 10,
            required: false, sensitive: true, help: 'Leave blank if you do not hold one.');

        $this->ask('d_dob', 'identity', QT::Date, 'What is your date of birth?', $o += 10, sensitive: true);

        $this->ask('d_second_nationality', 'identity', QT::SingleSelect, 'Do you hold a second nationality, or a legal connection to another country?', $o += 10, options: [
            'no' => 'No',
            'yes' => 'Yes',
            'not_sure' => 'I am not sure',
        ], sensitive: true,
            help: 'This affects which law may apply to parts of your estate, so it matters even if it feels remote.');

        $this->ask('d_second_nationality_detail', 'identity', QT::Text, 'Which country?', $o += 10, sensitive: true);

        // ---------------------------------------------------- spouse or partner
        $this->ask('d_has_spouse', 'spouse', QT::SingleSelect, 'Do you have a spouse or partner to name in your Will?', $o += 10, options: [
            'no' => 'No',
            'spouse' => 'Yes, a spouse',
            'partner' => 'Yes, an unmarried partner',
        ], sensitive: true);

        $this->ask('d_spouse_name', 'spouse', QT::Text, 'What is their full legal name, as it appears on their passport?', $o += 10, sensitive: true);

        $this->ask('d_spouse_nationality', 'spouse', QT::CountrySelect, 'What is their nationality?', $o += 10, sensitive: true);

        // ------------------------------------------------- children, dependants
        $this->ask('d_has_children', 'children', QT::SingleSelect, 'Do you have children or dependants to name?', $o += 10, options: [
            'no' => 'No',
            'yes' => 'Yes',
        ], sensitive: true);

        $this->ask('d_children_detail', 'children', QT::Textarea, 'List each child or dependant: full name, date of birth, and their relationship to you.', $o += 10,
            sensitive: true, help: 'One per line. Use their full legal name as it appears on their passport.');

        $this->ask('d_minor_children', 'children', QT::SingleSelect, 'Is any of them under 18?', $o += 10, options: [
            'no' => 'No',
            'yes' => 'Yes',
        ], sensitive: true);

        // ----------------------------------------------------------- guardians
        $this->ask('d_guardian_permanent', 'guardians', QT::Text, 'Who would you want as permanent guardian?', $o += 10, sensitive: true,
            help: 'Full legal name and their relationship to the child.',
            privacy: 'A nomination in a Will records your wishes. It does not remove the rights of the other parent or the authority of the court. The child\'s welfare and the applicable law remain decisive.');

        $this->ask('d_guardian_interim', 'guardians', QT::Text, 'Who should act as interim guardian, in the hours or days before a permanent arrangement is in place?', $o += 10,
            required: false, sensitive: true, help: 'Usually somebody already in the UAE. Leave blank if you would rather we discuss it.');

        $this->ask('d_guardian_alternate', 'guardians', QT::Text, 'And who should act if your first choice cannot?', $o += 10, required: false, sensitive: true);

        // ----------------------------------------- beneficiaries and substitutes
        $this->ask('d_beneficiaries', 'beneficiaries', QT::Textarea, 'Who should inherit? List each beneficiary with their full legal name and relationship to you.', $o += 10,
            sensitive: true, help: 'One per line. Names must match their passport.');

        $this->ask('d_substitutes', 'beneficiaries', QT::Textarea, 'If a beneficiary cannot inherit, who should take their share?', $o += 10,
            required: false, sensitive: true,
            help: 'Naming a substitute is what stops a share falling into the residue by accident.');

        // ------------------------------------------------------- distribution
        $this->ask('d_distribution_type', 'distribution', QT::SingleSelect, 'How should your estate be divided?', $o += 10, options: [
            'equal' => 'Equally between the beneficiaries named above',
            'percentages' => 'By specific percentages',
            'specific_gifts' => 'Specific gifts first, then the balance divided',
        ], sensitive: true);

        // Percentage validation is a legal requirement, not a nicety: a
        // distribution that does not total 100 cannot be drafted, and the
        // specification says work stops until it is corrected.
        $this->ask('d_percentages', 'distribution', QT::Textarea, 'Set out each beneficiary and their percentage share.', $o += 10,
            sensitive: true,
            help: 'One per line, as "Name — 50%". The percentages must total exactly 100.',
            security: 'We check this totals 100 before your instructions go to the legal team. A distribution that does not add up cannot be drafted.',
            meta: ['validate' => ['percentages_total_100']]);

        $this->ask('d_specific_gifts', 'distribution', QT::Textarea, 'Describe each specific gift and who receives it.', $o += 10, required: false, sensitive: true);

        $this->ask('d_residue', 'distribution', QT::Text, 'Who should receive the residue — anything left after the gifts above?', $o += 10, sensitive: true);

        // ---------------------------------------------------------- executors
        $this->ask('d_executor_name', 'executor', QT::Text, 'Who should act as your executor?', $o += 10, sensitive: true,
            help: 'Full legal name, their relationship to you, and how we can reach them.');

        $this->ask('d_executor_substitute', 'executor', QT::Text, 'And who should act if they cannot?', $o += 10, required: false, sensitive: true);

        // ------------------------------------------------------------- assets
        $this->ask('d_assets', 'asset_detail', QT::Textarea, 'List your UAE assets in as much detail as you can.', $o += 10,
            sensitive: true,
            help: 'Property with its address or title deed number, accounts with the bank name, vehicles with the plate. You do not need balances.',
            security: 'Never enter a password, PIN, seed phrase, recovery phrase or private key. We will never ask for one, and this platform does not store them.');

        $this->ask('d_existing_will', 'asset_detail', QT::SingleSelect, 'Do you have an existing Will anywhere in the world?', $o += 10, options: [
            'no' => 'No',
            'yes_uae' => 'Yes, in the UAE',
            'yes_abroad' => 'Yes, in another country',
            'yes_both' => 'Yes, in both',
        ], sensitive: true);

        $this->ask('d_existing_will_detail', 'asset_detail', QT::Textarea, 'Tell us about it: where it was made, roughly when, and whether it is still intended to apply.', $o += 10, sensitive: true);

        $this->ask('d_funeral_wishes', 'wishes_detail', QT::Textarea, 'Do you have any funeral or repatriation wishes to record?', $o += 10,
            required: false, sensitive: true,
            help: 'Optional. Some authorities give these limited legal weight, but recording them helps your family.');

        $this->ask('d_anything_else', 'wishes_detail', QT::Textarea, 'Is there anything else the legal team should know?', $o += 10, required: false, sensitive: true);
    }

    /** @param array<string, string> $options */
    private function ask(
        string $key,
        string $section,
        QT $type,
        string $prompt,
        int $order,
        array $options = [],
        bool $required = true,
        bool $sensitive = false,
        ?string $help = null,
        ?string $privacy = null,
        ?string $security = null,
        array $meta = [],
    ): Question {
        $question = $this->version->questions()->create([
            'key' => $key,
            'order' => $order,
            'type' => $type,
            'prompt' => $prompt,
            'help_text' => $help,
            'privacy_note' => $privacy,
            'security_note' => $security,
            'is_required' => $required,
            'is_sensitive' => $sensitive,
            'section_key' => $section,
            'meta' => $meta === [] ? null : $meta,
        ]);

        $i = 0;

        foreach ($options as $optionKey => $label) {
            $question->options()->create([
                'key' => $optionKey,
                'order' => $i += 10,
                'label' => $label,
                'is_exclusive' => false,
            ]);
        }

        return $this->q[$key] = $question->load('options');
    }

    private function visibility(): void
    {
        $this->showWhen('d_second_nationality_detail', 'd_second_nationality', Op::Equals, 'yes');

        foreach (['d_spouse_name', 'd_spouse_nationality'] as $key) {
            $this->showWhen($key, 'd_has_spouse', Op::In, ['spouse', 'partner']);
        }

        foreach (['d_children_detail', 'd_minor_children'] as $key) {
            $this->showWhen($key, 'd_has_children', Op::Equals, 'yes');
        }

        // The whole guardian section only opens for a minor child.
        foreach (['d_guardian_permanent', 'd_guardian_interim', 'd_guardian_alternate'] as $key) {
            $this->showWhen($key, 'd_minor_children', Op::Equals, 'yes');
        }

        $this->showWhen('d_percentages', 'd_distribution_type', Op::Equals, 'percentages');
        $this->showWhen('d_specific_gifts', 'd_distribution_type', Op::Equals, 'specific_gifts');
        $this->showWhen('d_existing_will_detail', 'd_existing_will', Op::In, ['yes_uae', 'yes_abroad', 'yes_both']);
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

    /**
     * Rules here FLAG for the reviewer rather than routing to a result screen.
     * By this stage the customer is accepted and has paid — there is nothing
     * left to triage them into, only things the reviewer must be told about.
     */
    private function rules(): void
    {
        $this->rule('D-01 · Second nationality or foreign connection', 10, Outcome::ContinueFlag,
            flag: 'foreign_law_connection',
            conditions: [['d_second_nationality', Op::In, ['yes', 'not_sure']]]);

        $this->rule('D-02 · Unmarried partner named', 20, Outcome::ContinueFlag,
            flag: 'unmarried_partner_named',
            conditions: [['d_has_spouse', Op::Equals, 'partner']]);

        $this->rule('D-03 · Minor children — guardianship applies', 30, Outcome::ContinueFlag,
            flag: 'guardianship_required',
            conditions: [['d_minor_children', Op::Equals, 'yes']]);

        $this->rule('D-04 · Percentage distribution — must total 100', 40, Outcome::ContinueFlag,
            flag: 'verify_percentages',
            detail: 'The reviewer must confirm the percentages total exactly 100 before drafting.',
            conditions: [['d_distribution_type', Op::Equals, 'percentages']]);

        $this->rule('D-05 · Specific gifts before residue', 50, Outcome::ContinueFlag,
            flag: 'specific_gifts',
            conditions: [['d_distribution_type', Op::Equals, 'specific_gifts']]);

        $this->rule('D-06 · Existing Will to reconcile', 60, Outcome::ContinueFlag,
            flag: 'existing_will_to_reconcile',
            conditions: [['d_existing_will', Op::In, ['yes_uae', 'yes_abroad', 'yes_both']]]);

        $this->rule('D-07 · No substitute beneficiary named', 70, Outcome::ContinueReminder,
            reminder: 'no_substitute_beneficiary',
            conditions: [['d_substitutes', Op::NotAnswered, null]]);

        $this->rule('D-08 · No substitute executor named', 80, Outcome::ContinueReminder,
            reminder: 'no_substitute_executor',
            conditions: [['d_executor_substitute', Op::NotAnswered, null]]);
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
    ): void {
        $rule = $this->version->routingRules()->create([
            'name' => $name,
            'priority' => $priority,
            'outcome' => $outcome,
            'outcome_detail' => $detail,
            'flag_key' => $flag,
            'reminder_key' => $reminder,
            'is_terminal' => false,
            'is_active' => true,
        ]);

        foreach ($conditions as [$questionKey, $operator, $value]) {
            $rule->conditions()->create([
                'question_id' => $this->q[$questionKey]->id,
                'operator' => $operator,
                'value' => $value,
                'group_index' => 0,
                'group_operator' => 'and',
            ]);
        }
    }

    private function declarations(): void
    {
        $declarations = [
            'I confirm that the names, dates and details I have given are accurate and match the identity documents I have supplied.',
            'I understand that Summit will review these instructions and may come back to me for clarification before drafting begins.',
            'I understand that a distribution which does not total 100 per cent cannot be drafted, and that work pauses until it is corrected.',
            'I understand that submitting these instructions does not create or register a Will.',
        ];

        foreach ($declarations as $i => $text) {
            $this->version->declarations()->create([
                'order' => ($i + 1) * 10,
                'text' => $text,
                'is_required' => true,
            ]);
        }
    }

    private function resultScreens(): void
    {
        $this->version->resultScreens()->create([
            'outcome' => Outcome::Continue_,
            'heading' => 'Your instructions are with the legal team',
            'body' => 'Thank you. Summit\'s legal team will review everything you have given us, confirm the appropriate registration route, and prepare your draft. We will contact you if anything needs clarifying. Nothing is submitted to any authority until you have personally approved the final wording.',
            'primary_action_label' => 'Back to my matter',
        ]);

        $this->version->resultScreens()->create([
            'outcome' => Outcome::Review,
            'heading' => 'We need to check something first',
            'body' => 'One or more of your answers needs a closer look before drafting begins. A member of the legal team will contact you. Nothing further is payable.',
            'primary_action_label' => 'Back to my matter',
        ]);
    }
}
