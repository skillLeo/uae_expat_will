<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Cases\Enums\CaseStage;
use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use App\Models\SavedView;
use App\Models\User;
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
            'savedViews' => SavedView::availableTo($user)->get()
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'filters' => $v->filters,
                    'is_shared' => $v->is_shared,
                    'is_mine' => $v->user_id === $user->id,
                ]),
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
            'contacts.user:id,name', 'payments', 'stageTimestamps', 'drafts.amendments', 'documents.media',
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
            'contacts' => $readable ? $case->contacts->map(fn ($c) => [
                'channel' => $c->channel, 'direction' => $c->direction,
                'summary' => $c->summary, 'by' => $c->user?->name,
                'at' => $c->occurred_at->toIso8601String(),
            ]) : [],
            'payments' => $case->payments->map(fn ($p) => [
                'id' => $p->id,
                'stage_label' => $p->stage_label,
                'total_amount' => (float) $p->total_amount,
                'currency' => $p->currency,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'tone' => $p->status->tone(),
                'link_url' => $p->link_url,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'refundable' => $p->isRefundable(),
            ]),
            'drafts' => $case->drafts->map(fn ($d) => [
                'id' => $d->id,
                'version_number' => $d->version_number,
                'status' => $d->status,
                'sent_at' => $d->sent_at?->toIso8601String(),
                'approved_at' => $d->approved_at?->toIso8601String(),
                'has_file' => $d->getFirstMedia('draft') !== null,
                'url' => DraftController::signedUrl($d),
                'amendments' => $d->amendments->map(fn ($a) => [
                    'id' => $a->id,
                    'body' => $a->body,
                    'status' => $a->status,
                    'within_allowance' => $a->is_within_allowance,
                    'at' => $a->created_at->toIso8601String(),
                ]),
            ]),
            // Documents follow the same redaction rule as the rest of the body.
            'documents' => $readable ? $case->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'category' => $doc->category,
                'status' => $doc->status,
                'review_note' => $doc->review_note,
                'filename' => $doc->file()?->file_name,
                'uploaded_at' => $doc->created_at->toIso8601String(),
                'url' => DocumentController::signedUrl($doc),
            ]) : [],
            'stages' => collect(CaseStage::cases())->map(fn ($stage) => [
                'value' => $stage->value,
                'label' => $stage->label(),
                'reached_at' => $case->stageAt($stage)?->occurred_at->toIso8601String(),
            ])->values(),
            'staff' => User::admins()->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
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
