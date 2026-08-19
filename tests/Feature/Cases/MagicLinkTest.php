<?php

use App\Domain\Cases\Actions\IssueMagicLink;
use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Models\LegalCase;
use App\Models\MagicLink;

beforeEach(function () {
    seedPlatform();

    $this->case = LegalCase::create([
        'reference' => 'SLC-2026-00010',
        'status' => CaseStatus::AcceptedPaymentRequired,
        'internal_status' => InternalStatus::PaymentLinkSent,
    ]);

    $this->issue = app(IssueMagicLink::class);
});

it('never stores the raw token', function () {
    ['link' => $link, 'url' => $url] = $this->issue->execute($this->case);

    $raw = str($url)->afterLast('/')->toString();

    expect($link->token_hash)->not->toBe($raw)
        ->and($link->token_hash)->toBe(hash('sha256', $raw))
        ->and(MagicLink::where('token_hash', $raw)->exists())->toBeFalse();
});

it('issues a usable link', function () {
    ['link' => $link] = $this->issue->execute($this->case);

    expect($link->isUsable())->toBeTrue()
        ->and($link->failureReason())->toBeNull();
});

it('is single use', function () {
    ['link' => $link] = $this->issue->execute($this->case);

    $link->update(['used_at' => now()]);

    expect($link->fresh()->isUsable())->toBeFalse()
        ->and($link->fresh()->failureReason())->toBe('used');
});

it('expires', function () {
    ['link' => $link] = $this->issue->execute($this->case, hours: 24);

    $this->travel(25)->hours();

    expect($link->fresh()->isUsable())->toBeFalse()
        ->and($link->fresh()->failureReason())->toBe('expired');
});

it('can be revoked by an administrator', function () {
    ['link' => $link] = $this->issue->execute($this->case);
    $admin = adminUser();

    $link->update(['revoked_at' => now(), 'revoked_by' => $admin->id]);

    expect($link->fresh()->isUsable())->toBeFalse()
        ->and($link->fresh()->failureReason())->toBe('revoked');
});

it('reports revoked ahead of expired when both apply', function () {
    // The four failure screens are distinct, and a revoked link is a deliberate
    // administrative act — the customer should be told that, not "expired".
    ['link' => $link] = $this->issue->execute($this->case, hours: 1);
    $link->update(['revoked_at' => now()]);
    $this->travel(2)->hours();

    expect($link->fresh()->failureReason())->toBe('revoked');
});

it('retires an outstanding link when a new one is issued', function () {
    ['link' => $first] = $this->issue->execute($this->case);
    ['link' => $second] = $this->issue->execute($this->case);

    expect($first->fresh()->isRevoked())->toBeTrue()
        ->and($second->fresh()->isUsable())->toBeTrue();
});

it('grants access to exactly one case', function () {
    $other = LegalCase::create([
        'reference' => 'SLC-2026-00011',
        'status' => CaseStatus::AcceptedPaymentRequired,
        'internal_status' => InternalStatus::PaymentLinkSent,
    ]);

    ['link' => $link] = $this->issue->execute($this->case);

    expect($link->case_id)->toBe($this->case->id)
        ->and($link->case_id)->not->toBe($other->id);
});

it('produces an unguessable token', function () {
    $tokens = collect(range(1, 20))->map(
        fn () => str($this->issue->execute($this->case)['url'])->afterLast('/')->toString()
    );

    expect($tokens->unique())->toHaveCount(20)
        ->and($tokens->first())->toHaveLength(64);
});
