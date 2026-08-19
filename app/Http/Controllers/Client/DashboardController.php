<?php

namespace App\Http\Controllers\Client;

use App\Domain\Cases\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer dashboard.
 *
 * ONLY the eight customer-facing statuses appear here. The internal status is
 * never serialised into a customer response — an endpoint returning
 * "Held — capacity or influence" would be a data breach, not a bug.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $cases = $this->casesFor($request);

        return Inertia::render('Client/Dashboard', [
            'cases' => $cases->map(fn (LegalCase $case) => $this->present($case)),
        ]);
    }

    /** @return Collection<int, LegalCase> */
    private function casesFor(Request $request)
    {
        $user = $request->user();

        return LegalCase::whereHas('customer', fn ($q) => $q->where('user_id', $user->id))
            ->with('drafts', 'payments', 'documents')
            ->latest()
            ->get();
    }

    /** @return array<string, mixed> */
    private function present(LegalCase $case): array
    {
        $status = $case->status;

        return [
            'id' => $case->id,
            'reference' => $case->reference,
            // The GROUP, never the internal status.
            'status' => $status->value,
            'status_label' => $status->label(),
            'tone' => $status->tone(),
            'sequence' => $status->sequence(),
            'stages' => collect(CaseStatus::cases())->map(fn (CaseStatus $s) => [
                'label' => $s->label(),
                'state' => match (true) {
                    $s->sequence() < $status->sequence() => 'done',
                    $s->sequence() === $status->sequence() => 'current',
                    default => 'upcoming',
                },
            ])->values(),
            'quoted_amount' => $case->quoted_amount ? (float) $case->quoted_amount : null,
            'paid_amount' => (float) $case->paid_amount,
            'currency' => $case->currency,
            // A held matter shows no payment control at all.
            'allows_payment' => $case->allowsPayment(),
            'has_outstanding_payment' => $case->payments
                ->contains(fn ($p) => $p->status->value === 'pending'),
            'payment_link' => $case->payments
                ->firstWhere(fn ($p) => $p->status->value === 'pending')?->link_url,
            'documents_count' => $case->documents->count(),
            'latest_draft' => $case->drafts->first() ? [
                'id' => $case->drafts->first()->id,
                'version_number' => $case->drafts->first()->version_number,
                'status' => $case->drafts->first()->status,
            ] : null,
            'created_at' => $case->created_at->toIso8601String(),
        ];
    }
}
