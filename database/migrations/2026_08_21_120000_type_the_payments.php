<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every payment a type, and lets a refund exist without a band.
 *
 * Existing rows are classified by reading the free-text stage label they were
 * given. Anything the label does not clearly identify is left as a professional
 * fee — which is what every payment in the system has been until now, since the
 * platform only ever raised one — and printed at the end so a person can check
 * it rather than discovering it in a refund calculation months later.
 */
return new class extends Migration
{
    /** Words that only appear on a charge somebody else levied. */
    private const DISBURSEMENT = '/authorit|government|govt|court|registr|notar|difc|adjd|translat|typing|attestation|legalis|legaliz|embassy|consulate|disbursement|third[ -]?party|stamp duty|filing fee|lodg/i';

    /** Words that clearly mean Summit's own fee. */
    private const PROFESSIONAL = '/professional|legal fee|service fee|consultanc|drafting|preparation|our fee|will fee|balance/i';

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('type', 30)->default('professional_fee')->after('currency')->index();
        });

        // A disbursement has no band — the four bands describe Summit's fee.
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('band', 1)->nullable()->change();
        });

        $this->classifyExistingPayments();
    }

    private function classifyExistingPayments(): void
    {
        $rows = DB::table('payments')->select('id', 'stage_label', 'total_amount')->get();

        if ($rows->isEmpty()) {
            $this->say('No existing payments to classify.');

            return;
        }

        $disbursements = [];
        $professional = 0;
        $ambiguous = [];

        foreach ($rows as $row) {
            $label = (string) $row->stage_label;

            if ($label !== '' && preg_match(self::DISBURSEMENT, $label)) {
                DB::table('payments')->where('id', $row->id)->update(['type' => 'disbursement']);
                $disbursements[] = "#{$row->id} \"{$label}\"";

                continue;
            }

            if ($label !== '' && preg_match(self::PROFESSIONAL, $label)) {
                $professional++;

                continue;
            }

            // Left as a professional fee by the column default, but said out
            // loud rather than assumed.
            $ambiguous[] = "#{$row->id} \"".($label === '' ? '(no label)' : $label)."\" — {$row->total_amount}";
        }

        $this->say('Classified '.$rows->count().' payment(s): '
            .$professional.' professional fee, '.count($disbursements).' disbursement, '
            .count($ambiguous).' unclear.');

        foreach ($disbursements as $line) {
            $this->say('  disbursement: '.$line);
        }

        if ($ambiguous !== []) {
            $this->say('');
            $this->say('  These labels did not clearly say what the payment was for. Each has been');
            $this->say('  left as a professional fee. Check them on Admin -> Payments and correct');
            $this->say('  any that were actually an authority charge:');

            foreach ($ambiguous as $line) {
                $this->say('    '.$line);
            }
        }
    }

    private function say(string $line): void
    {
        if (app()->runningInConsole()) {
            echo $line."\n";
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->string('band', 1)->nullable(false)->change();
        });
    }
};
