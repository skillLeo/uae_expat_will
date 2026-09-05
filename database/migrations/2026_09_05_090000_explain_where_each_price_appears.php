<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Says, on the form itself, where each price is shown on the site.
 *
 * Summit spent four days arguing that the DIFC charge had been set to AED
 * 10,000. It had not. The page carries three unrelated kinds of price — what
 * Summit charges, what a court charges, and the DIFC starting quote — and
 * nothing anywhere said which was which. Two people therefore read the same
 * screen and saw different things, and neither could prove it.
 *
 * The admin form is the right place to settle it, because that is where the
 * person changing a price is standing when they need to know what they are
 * changing.
 *
 * The authority-charges line is the important one. It states outright that
 * those are the court's money and never Summit's.
 */
return new class extends Migration
{
    private const HELP = [
        'commercial.standard_fee' => 'What Summit charges for one Will, excluding VAT. Shown on the Pricing page, How It Works step 4, the FAQ answers, the UAE Will Options table and at checkout. Changing it here changes all of them.',
        'commercial.mirror_fee' => 'What Summit charges a couple for two Wills, excluding VAT. Its own price, not the single fee doubled. Shown on the Mirror Wills card and at checkout when two Wills are chosen.',
        'commercial.difc_starting_fee' => 'Shown only on the DIFC card, always as "From AED …". DIFC work is quoted individually and can never be paid for online, so this is a starting point and not a purchasable price.',
        'commercial.vat_rate' => 'Added to the professional fee at checkout. At 5%, AED 10,000 is shown as 10,500.00.',
        'commercial.currency' => 'Placed in front of every figure on the site.',
        'commercial.authority_fees' => 'The COURT\'S OWN CHARGES, not Summit\'s. Summit never receives this money — the authority sets and collects it. Shown in the "Why court fees are shown separately" table on the Pricing page. Nothing here has any connection to the professional fee above.',
        'commercial.amendment_allowance' => 'How many rounds of amendment are included. Named in the Service Confirmation the client sees before paying.',
        'commercial.first_draft_days' => 'The first-draft target in business days. Appears in client-facing copy, so only put a number here Summit stands behind.',
    ];

    public function up(): void
    {
        foreach (self::HELP as $key => $help) {
            DB::table('settings')->where('key', $key)->update([
                'help_text' => $help,
                'updated_at' => now(),
            ]);
        }

        Cache::flush();
    }

    public function down(): void
    {
        // The previous text was terse and in some cases absent; there is
        // nothing worth restoring, and a wrong explanation is worse than none.
    }
};
