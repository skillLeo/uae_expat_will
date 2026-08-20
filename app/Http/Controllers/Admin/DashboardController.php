<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\System\DTOs\HealthCheck;
use App\Domain\System\Services\SystemHealth;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private SystemHealth $health) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user('admin');
        $visible = LegalCase::visibleTo($user);

        return Inertia::render('Admin/Dashboard', [
            // Gated on settings.view, which today means Super Administrator and
            // Administrator. System state belongs with whoever can act on it —
            // a coordinator seeing "backups are failing" can do nothing about it
            // except worry.
            'health' => $user->can('settings.view')
                ? $this->healthPayload()
                : null,
            'stats' => [
                'open' => (clone $visible)->whereNull('closed_at')->count(),
                'overdue' => (clone $visible)->overdue()->count(),
                'held' => (clone $visible)->where('status', CaseStatus::UnderReview)->count(),
                'assessments_today' => Assessment::whereDate('created_at', today())->count(),
            ],
            'pipeline' => collect(CaseStatus::cases())->map(fn (CaseStatus $s) => [
                'key' => $s->value,
                'label' => $s->label(),
                'tone' => $s->tone(),
                'count' => (clone $visible)->where('status', $s)->count(),
            ])->values(),
            'recent' => (clone $visible)
                ->with('customer:id,full_name')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (LegalCase $c) => $this->row($c)),
        ]);
    }

    /**
     * Cached for a minute inside the service, so adding this to the dashboard
     * does not slow it down. A check that throws degrades to "cannot tell"
     * rather than taking the whole page with it.
     *
     * @return array<string, mixed>
     */
    private function healthPayload(): array
    {
        $checks = $this->health->checks();

        return [
            'worst' => $this->health->worst($checks)->value,
            'checks' => array_map(fn (HealthCheck $c) => $c->toArray(), $checks),
            'checked_at' => now()->toIso8601String(),
        ];
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
            // A restricted row stays present and countable, but its body is
            // redacted for anyone without the permission.
            'customer' => $readable ? $case->customer?->full_name : LegalCase::REDACTION,
            'created_at' => $case->created_at->toDateString(),
        ];
    }
}
