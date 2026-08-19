<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\LegalCase;
use App\Models\Payment;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operational analytics.
 *
 * Everything here is aggregate. No answer content, no religion, no family or
 * beneficiary detail reaches this screen — abandonment is reported by question
 * KEY and prompt, never by what anybody answered.
 */
class AnalyticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $days = (int) $request->integer('days', 90);
        $since = now()->subDays($days);

        return Inertia::render('Admin/Analytics/Index', [
            'range' => ['days' => $days, 'since' => $since->toDateString()],

            'leads' => [
                'by_source' => Assessment::where('created_at', '>=', $since)
                    ->select('source', DB::raw('count(*) as total'))
                    ->groupBy('source')->orderByDesc('total')->get()
                    ->map(fn ($r) => ['label' => $r->source ?? 'direct', 'total' => (int) $r->total]),
                'by_campaign' => Assessment::where('created_at', '>=', $since)
                    ->whereNotNull('campaign')
                    ->select('campaign', DB::raw('count(*) as total'))
                    ->groupBy('campaign')->orderByDesc('total')->get()
                    ->map(fn ($r) => ['label' => $r->campaign, 'total' => (int) $r->total]),
            ],

            'outcomes' => Assessment::where('created_at', '>=', $since)
                ->whereNotNull('outcome')
                ->select('outcome', DB::raw('count(*) as total'))
                ->groupBy('outcome')->get()
                ->map(fn ($r) => [
                    'label' => $r->outcome->label(),
                    'value' => $r->outcome->value,
                    'tone' => $r->outcome->tone(),
                    'total' => (int) $r->total,
                ]),

            // Where people give up. Reported by question, never by answer.
            'abandonment' => Assessment::where('created_at', '>=', $since)
                ->where('status', '!=', 'completed')
                ->whereNotNull('current_question_key')
                ->select('current_question_key', DB::raw('count(*) as total'))
                ->groupBy('current_question_key')
                ->orderByDesc('total')
                ->limit(15)
                ->get()
                ->map(fn ($r) => [
                    'key' => $r->current_question_key,
                    'prompt' => Question::where('key', $r->current_question_key)->value('prompt'),
                    'total' => (int) $r->total,
                ]),

            'conversion' => [
                'started' => Assessment::where('created_at', '>=', $since)->count(),
                'completed' => Assessment::where('created_at', '>=', $since)->where('status', 'completed')->count(),
                'cases' => LegalCase::where('created_at', '>=', $since)->count(),
                'paid' => Payment::where('created_at', '>=', $since)
                    ->where('status', PaymentStatus::Paid)->distinct('case_id')->count('case_id'),
            ],

            'revenue' => [
                'total' => (float) Payment::where('status', PaymentStatus::Paid)
                    ->where('paid_at', '>=', $since)->sum('total_amount'),
                'by_month' => Payment::where('status', PaymentStatus::Paid)
                    ->where('paid_at', '>=', now()->subMonths(12))
                    ->get()
                    ->groupBy(fn ($p) => $p->paid_at->format('Y-m'))
                    ->map(fn ($group, $month) => [
                        'month' => $month,
                        'total' => (float) $group->sum('total_amount'),
                        'count' => $group->count(),
                    ])->values()->sortBy('month')->values(),
            ],

            'pipeline' => collect(CaseStatus::cases())->map(fn (CaseStatus $s) => [
                'label' => $s->label(),
                'tone' => $s->tone(),
                'total' => LegalCase::where('status', $s)->count(),
            ])->values(),
        ]);
    }
}
