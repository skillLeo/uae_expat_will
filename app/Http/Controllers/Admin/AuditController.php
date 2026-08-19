<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The audit log viewer.
 *
 * READ ONLY. There is no update or delete route here, and there never will be —
 * the table is append-only at the database level and the UI must not imply
 * otherwise by offering an affordance that would fail.
 */
class AuditController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $entries = Activity::with('causer:id,name')
            ->when($request->filled('log'), fn ($q) => $q->where('log_name', $request->string('log')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where('description', 'like', $term);
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'log_name' => $a->log_name,
                'description' => $a->description,
                'causer' => $a->causer?->name ?? 'System',
                'subject' => $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : null,
                'properties' => $a->properties,
                'ip_address' => $a->ip_address,
                'route' => $a->route,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Audit/Index', [
            'entries' => $entries,
            'filters' => $request->only(['log', 'q', 'from', 'to']),
            'logs' => Activity::distinct()->orderBy('log_name')->pluck('log_name'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Activity::with('causer:id,name')
            ->when($request->filled('log'), fn ($q) => $q->where('log_name', $request->string('log')))
            ->latest('id');

        $count = (clone $query)->count();
        $this->audit->exported('audit log', $count);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'when', 'log', 'description', 'by', 'subject', 'ip', 'route', 'properties']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $a) {
                    fputcsv($out, [
                        $a->id,
                        $a->created_at->toIso8601String(),
                        $a->log_name,
                        $a->description,
                        $a->causer?->name ?? 'System',
                        $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : '',
                        $a->ip_address,
                        $a->route,
                        json_encode($a->properties),
                    ]);
                }
            });

            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function consents(Request $request): Response
    {
        return Inertia::render('Admin/Audit/Consents', [
            'consents' => Consent::with('legalCase:id,reference')
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
                ->latest('accepted_at')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (Consent $c) => [
                    'id' => $c->id,
                    'type' => $c->type,
                    'version' => $c->version,
                    'wording_hash' => substr($c->wording_hash, 0, 16).'…',
                    'accepted' => $c->accepted,
                    'reference' => $c->legalCase?->reference ?? $c->related_reference,
                    'ip_address' => $c->ip_address,
                    'language' => $c->language,
                    'accepted_at' => $c->accepted_at->toIso8601String(),
                ]),
            'filters' => $request->only('type'),
            'types' => Consent::distinct()->orderBy('type')->pluck('type'),
        ]);
    }

    public function exportConsents(Request $request): StreamedResponse
    {
        $query = Consent::with('legalCase:id,reference')->latest('accepted_at');
        $this->audit->exported('consent records', (clone $query)->count());

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // The wording hash is the point of the export: it proves WHAT was
            // agreed, not merely that something was.
            fputcsv($out, ['id', 'type', 'version', 'wording_hash', 'accepted', 'reference', 'ip', 'language', 'accepted_at']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, [
                        $c->id, $c->type, $c->version, $c->wording_hash,
                        $c->accepted ? 'yes' : 'no',
                        $c->legalCase?->reference ?? $c->related_reference,
                        $c->ip_address, $c->language,
                        $c->accepted_at->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, 'consent-records-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
