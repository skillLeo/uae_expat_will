<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CaseController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $user = $request->user('admin');

        $cases = LegalCase::visibleTo($user)
            ->with('customer:id,full_name,email', 'assignee:id,name')
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (LegalCase $c) => $this->row($c));

        return Inertia::render('Admin/Cases/Index', [
            'cases' => $cases,
            'filters' => $request->only(['q', 'status', 'overdue']),
            'statuses' => collect(CaseStatus::cases())->map(fn ($s) => [
                'value' => $s->value, 'label' => $s->label(),
            ])->values(),
        ]);
    }

    public function show(Request $request, LegalCase $case): Response
    {
        $this->audit->caseAccessed($case);

        $readable = $case->isReadableByViewer();

        $case->load([
            'customer', 'assignee:id,name', 'assessment',
            'statusHistory.changedBy:id,name', 'notes.author:id,name',
            'contacts.user:id,name', 'payments', 'stageTimestamps', 'drafts',
        ]);

        return Inertia::render('Admin/Cases/Show', [
            // Named `record` rather than `case`, because `case` is a reserved
            // word in JavaScript and cannot appear in a Vue template expression.
            'record' => array_merge($this->row($case), [
                'internal_status' => $case->internal_status->value,
                'internal_status_label' => $case->internal_status->label(),
                'quoted_amount' => $case->quoted_amount,
                'paid_amount' => $case->paid_amount,
                'currency' => $case->currency,
                'countdown_due_at' => $case->countdown_due_at?->toIso8601String(),
                'is_overdue' => $case->isOverdue(),
                'allows_payment' => $case->allowsPayment(),
                // Only ever populated for a viewer who holds the permission.
                'restricted_reason' => $case->restrictedReason(),
            ]),
            'readable' => $readable,
            // Trigger reasons are redacted per-viewer on the way out.
            'triggerReasons' => $this->triggerReasons($case, $readable),
            'answers' => $readable ? $this->answers($case) : [],
            'statusHistory' => $case->statusHistory->map(fn ($h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'by' => $h->changedBy?->name,
                'reason' => $h->reason,
                'at' => $h->changed_at->toIso8601String(),
            ]),
            'notes' => $readable ? $case->notes->map(fn ($n) => [
                'id' => $n->id, 'body' => $n->body, 'author' => $n->author?->name,
                'is_internal' => $n->is_internal, 'at' => $n->created_at->toIso8601String(),
            ]) : [],
            'internalStatuses' => collect(InternalStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                // The internal-to-customer-facing mapping is shown in the UI so
                // staff can see what the customer will read.
                'group' => $s->group()->label(),
            ])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(LegalCase $case): array
    {
        $readable = $case->isReadableByViewer();

        return [
            'id' => $case->id,
            'reference' => $case->reference,
            'status' => $case->status->value,
            'status_label' => $case->status->label(),
            'tone' => $case->status->tone(),
            'is_restricted' => $case->is_restricted,
            'customer' => $readable
                ? ['name' => $case->customer?->full_name, 'email' => $case->customer?->email]
                : ['name' => LegalCase::REDACTION, 'email' => null],
            'assignee' => $case->assignee?->name,
            'created_at' => $case->created_at->toIso8601String(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function triggerReasons(LegalCase $case, bool $readable): array
    {
        $reasons = $case->assessment?->trigger_reasons ?? [];

        if ($readable) {
            return $reasons;
        }

        return array_map(fn (array $r) => [
            'outcome' => $r['outcome'] ?? null,
            'question_prompt' => LegalCase::REDACTION,
            'answer_label' => LegalCase::REDACTION,
            'is_restricted' => true,
        ], $reasons);
    }

    /** @return array<int, array<string, string>> */
    private function answers(LegalCase $case): array
    {
        $assessment = $case->assessment;

        if ($assessment === null) {
            return [];
        }

        $assessment->load('answers.question');

        return $assessment->answers
            ->filter(fn ($a) => $a->question !== null)
            ->map(fn ($a) => [
                'key' => $a->question_key,
                'prompt' => $a->question->prompt,
                'answer' => $a->question->labelForAnswer($a->value),
                'is_sensitive' => $a->question->is_sensitive,
            ])
            ->values()
            ->all();
    }
}
