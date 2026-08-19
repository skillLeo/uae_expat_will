<?php

namespace Database\Seeders;

use App\Domain\Assessment\Enums\Outcome;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Assessment;
use App\Models\CaseStatusHistory;
use App\Models\Customer;
use App\Models\LegalCase;
use App\Models\Payment;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Forty demo cases spanning every status, for development and for showing the
 * admin panel to the client. Never run in production.
 */
class DemoDataSeeder extends Seeder
{
    private const SOURCES = ['organic', 'google_ads', 'referral', 'linkedin', 'direct'];

    private const CAMPAIGNS = ['expat-wills-q3', 'guardianship', 'difc-awareness', null];

    public function run(): void
    {
        $version = Questionnaire::screening()?->publishedVersion();

        if ($version === null) {
            $this->command?->error('Seed the questionnaire first.');

            return;
        }

        $statuses = InternalStatus::cases();
        $target = 40;
        $reference = 1;

        // Exactly 40 cases, spread so that EVERY status has at least one and the
        // remainder is distributed from the top — the pipeline is never empty in
        // any column, which is the point of a demo set.
        $perStatus = intdiv($target, count($statuses));
        $remainder = $target % count($statuses);

        foreach ($statuses as $index => $status) {
            $count = max(1, $perStatus) + ($index < $remainder ? 1 : 0);

            for ($i = 0; $i < $count && $reference <= $target; $i++) {
                $this->makeCase($version, $status, $reference++);
            }
        }

        $this->command?->info('  '.LegalCase::count().' demo cases created.');
    }

    private function makeCase($version, InternalStatus $status, int $n): void
    {
        $restricted = $status->restrictsCase();
        $createdAt = now()->subDays(random_int(0, 90))->subHours(random_int(0, 23));

        $customer = Customer::create([
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+9715'.random_int(10000000, 99999999),
            'nationality' => fake()->randomElement(['GB', 'IN', 'PK', 'PH', 'ZA', 'FR', 'EG', 'LB']),
            'country_of_residence' => fake()->randomElement(['AE', 'GB', 'IN', 'SA']),
            'emirate' => fake()->randomElement(['dubai', 'abu_dhabi', 'sharjah', null]),
            'preferred_contact_method' => fake()->randomElement(['email', 'phone', 'whatsapp']),
        ]);

        $outcome = match (true) {
            $restricted => Outcome::UrgentReview,
            $status->group()->value === 'under_review' => Outcome::Review,
            default => Outcome::Continue_,
        };

        $assessment = Assessment::create([
            'uuid' => (string) Str::uuid(),
            'questionnaire_version_id' => $version->id,
            'status' => 'completed',
            'outcome' => $outcome,
            'trigger_reasons' => $outcome === Outcome::Continue_ ? [] : [[
                'rule_name' => $restricted ? 'R-04 · Capacity or undue influence' : 'R-11 · Existing Will, law election or structure',
                'outcome' => $outcome->value,
                'question_key' => $restricted ? 'q15b' : 'q12',
                'question_prompt' => $restricted
                    ? 'Do you understand the nature and effect of a Will, and are you making your decisions freely?'
                    : 'Do you have any existing Wills or arrangements?',
                'answer_label' => $restricted ? 'I feel pressured or influenced' : 'A Will or draft Will outside the UAE',
                'is_restricted' => $restricted,
            ]],
            'flags' => [],
            'reminders' => [],
            'route_marks' => [],
            'source' => fake()->randomElement(self::SOURCES),
            'campaign' => fake()->randomElement(self::CAMPAIGNS),
            'ip_address' => fake()->ipv4(),
            'started_at' => $createdAt,
            'completed_at' => $createdAt->copy()->addMinutes(random_int(4, 20)),
        ]);

        $case = new LegalCase([
            'reference' => sprintf('SLC-%d-%05d', now()->year, $n),
            'customer_id' => $customer->id,
            'assessment_id' => $assessment->id,
            'status' => $status->group(),
            'internal_status' => $status,
            'is_restricted' => $restricted,
            'service_type' => 'standard_will',
            'quoted_amount' => $status->allowsPayment() ? 2199 : null,
            'currency' => 'AED',
            'countdown_due_at' => $createdAt->copy()->addHours(4),
        ]);

        if ($restricted) {
            $case->setRestrictedReason(
                'Capacity or undue influence indicated at Q15B. Handle under special confidentiality.'
            );
        }

        $case->created_at = $createdAt;
        $case->updated_at = $createdAt;
        $case->save();

        CaseStatusHistory::create([
            'case_id' => $case->id,
            'to_status' => $status->value,
            'reason' => 'Demo data.',
            'changed_at' => $createdAt,
        ]);

        $this->maybePay($case, $status, $createdAt);
    }

    private function maybePay(LegalCase $case, InternalStatus $status, $createdAt): void
    {
        // Anything past the payment stage has a paid payment and stage
        // timestamps, so the refund engine has something real to work from.
        $paidGroups = ['questionnaire_in_progress', 'legal_review_drafting', 'draft_review', 'registration_in_progress', 'completed'];

        if (! in_array($status->group()->value, $paidGroups, true)) {
            return;
        }

        $paidAt = $createdAt->copy()->addDays(1);

        Payment::create([
            'case_id' => $case->id,
            'gateway' => 'telr',
            'gateway_reference' => 'DEMO-'.Str::upper(Str::random(8)),
            'amount' => 2199.00,
            'vat_amount' => 109.95,
            'total_amount' => 2308.95,
            'currency' => 'AED',
            'stage_label' => 'Professional fee',
            'status' => PaymentStatus::Paid,
            'method' => 'card',
            'paid_at' => $paidAt,
        ]);

        $case->update(['paid_amount' => 2308.95]);

        $stages = [CaseStage::Payment];

        if (in_array($status->group()->value, ['legal_review_drafting', 'draft_review', 'registration_in_progress', 'completed'], true)) {
            $stages[] = CaseStage::SubstantiveWorkStarted;
        }
        if (in_array($status->group()->value, ['draft_review', 'registration_in_progress', 'completed'], true)) {
            $stages[] = CaseStage::FirstDraftDelivered;
        }
        if (in_array($status->group()->value, ['registration_in_progress', 'completed'], true)) {
            $stages[] = CaseStage::FinalApproval;
        }
        if ($status->group()->value === 'completed') {
            $stages[] = CaseStage::AuthoritySubmitted;
        }

        foreach ($stages as $i => $stage) {
            $case->stageTimestamps()->create([
                'stage' => $stage,
                'occurred_at' => $paidAt->copy()->addDays($i),
            ]);
        }
    }
}
