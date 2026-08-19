<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payments\Actions\CreatePaymentLink;
use App\Domain\Payments\Actions\ProcessRefund;
use App\Domain\Payments\Actions\RecordManualPayment;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Services\RefundCalculator;
use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::with('legalCase:id,reference,is_restricted', 'creator:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'reference' => $p->legalCase?->reference,
                'case_id' => $p->case_id,
                'stage_label' => $p->stage_label,
                'total_amount' => (float) $p->total_amount,
                'currency' => $p->currency,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'tone' => $p->status->tone(),
                'method' => $p->method,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'created_at' => $p->created_at->toIso8601String(),
                'refundable' => $p->isRefundable(),
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'filters' => $request->only('status'),
            'statuses' => collect(PaymentStatus::cases())->map(fn ($s) => [
                'value' => $s->value, 'label' => $s->label(),
            ])->values(),
            'totals' => [
                'paid' => (float) Payment::where('status', PaymentStatus::Paid)->sum('total_amount'),
                'pending' => (float) Payment::where('status', PaymentStatus::Pending)->sum('total_amount'),
                'refunded' => (float) Payment::where('status', PaymentStatus::Refunded)->sum('total_amount'),
            ],
        ]);
    }

    public function createLink(Request $request, LegalCase $case, CreatePaymentLink $action): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
            'stage_label' => 'required|string|max:120',
        ]);

        try {
            $action->execute($case, (float) $validated['amount'], $validated['stage_label']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment link generated and ready to send.');
    }

    public function recordManual(Request $request, LegalCase $case, RecordManualPayment $action): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
            'method' => 'required|string|in:bank_transfer,cash',
            'stage_label' => 'required|string|max:120',
            'reference' => 'nullable|string|max:120',
        ]);

        $action->execute(
            $case,
            (float) $validated['amount'],
            $validated['method'],
            $validated['stage_label'],
            $validated['reference'] ?? null,
        );

        return back()->with('success', 'Payment recorded.');
    }

    /** The refund preview — band and working, before anything is committed. */
    public function refundPreview(Payment $payment, RefundCalculator $calculator): JsonResponse
    {
        $result = $calculator->calculate($payment);

        return response()->json([
            'band' => $result['band']->value,
            'band_label' => $result['band']->label(),
            'band_description' => $result['band']->description(),
            'refundable' => $result['refundable'],
            'deduction' => $result['deduction'],
            'reason' => $result['reason'],
            'calculation' => $result['calculation'],
        ]);
    }

    public function refund(Request $request, Payment $payment, ProcessRefund $action): RedirectResponse
    {
        $validated = $request->validate([
            'documented_deduction' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
        ]);

        if (! $payment->isRefundable()) {
            return back()->with('error', 'This payment is not refundable.');
        }

        $refund = $action->execute(
            $payment,
            isset($validated['documented_deduction']) ? (float) $validated['documented_deduction'] : null,
            $validated['reason'] ?? null,
        );

        return back()->with('success', "Refund issued under band {$refund->band->value}.");
    }

    /** Manual status check against the gateway — the fallback when a webhook is missed. */
    public function checkStatus(Payment $payment, PaymentGateway $gateway): RedirectResponse
    {
        $result = $gateway->checkStatus($payment);

        if (! $result['ok']) {
            return back()->with('error', 'Gateway check failed: '.($result['error'] ?? 'no response'));
        }

        $payment->events()->create([
            'type' => 'manual_status_check',
            'source' => 'manual_check',
            'payload' => $result,
            'occurred_at' => now(),
        ]);

        if ($result['status'] && $result['status'] !== $payment->status->value) {
            $payment->update([
                'status' => PaymentStatus::from($result['status']),
                'paid_at' => $result['status'] === 'paid' ? now() : $payment->paid_at,
            ]);

            return back()->with('success', "Gateway reports: {$result['status']}. Status updated.");
        }

        return back()->with('success', 'Gateway agrees with our record. Nothing changed.');
    }
}
