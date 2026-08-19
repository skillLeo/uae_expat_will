<?php

use App\Domain\Assessment\DTOs\AnswerSet;
use App\Domain\Assessment\RoutingEngine;
use App\Domain\Cases\Actions\CreateCaseFromAssessment;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Models\Assessment;
use App\Models\LegalCase;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    seedPlatform();
    $this->version = seedQuestionnaire();
    $this->seed(NotificationTemplateSeeder::class);
    Mail::fake();
});

/** Runs a full assessment to the given answers and opens the case. */
function completeAssessment(array $answers): LegalCase
{
    $assessment = Assessment::create([
        'questionnaire_version_id' => test()->version->id,
        'status' => 'completed',
        'started_at' => now(),
    ]);

    $engine = new RoutingEngine(test()->version);
    $result = $engine->evaluate(AnswerSet::make($answers));

    return app(CreateCaseFromAssessment::class)->execute($assessment, $result, [
        'full_name' => 'Test Customer',
        'email' => 'customer@example.com',
    ]);
}

it('never puts the restricted reason in any notification body', function () {
    $case = completeAssessment(cleanAnswers(['q15b' => 'feel_pressured']));

    expect($case->is_restricted)->toBeTrue();

    $logs = NotificationLog::where('case_id', $case->id)->get();
    expect($logs)->not->toBeEmpty();

    $forbidden = [
        'pressured', 'influence', 'capacity', 'undue',
        'q15b', 'health condition', 'someone is helping',
    ];

    foreach ($logs as $log) {
        $body = strtolower(json_encode($log->payload).' '.$log->recipient.' '.$log->template_key);

        foreach ($forbidden as $word) {
            expect($body)->not->toContain($word,
                "Notification payload leaked '{$word}'");
        }
    }
});

it('sends only the reference and the outcome bucket in the internal alert', function () {
    $case = completeAssessment(cleanAnswers(['q15b' => 'someone_helping']));

    $log = NotificationLog::where('case_id', $case->id)->first();

    expect($log->payload)->toHaveKey('reference')
        ->and($log->payload['reference'])->toBe($case->reference)
        // The bucket for a restricted case is the neutral phrasing, never the
        // internal status and never the reason.
        ->and($log->payload['outcome'])->toBe('Requires immediate legal attention')
        // A count is safe: it says HOW MANY triggers fired, never what they were.
        ->and($log->payload)->toHaveKey('trigger_count')
        ->and($log->payload)->not->toHaveKey('trigger_reasons')
        ->and($log->payload)->not->toHaveKey('answers');
});

it('names the ordinary outcome for a non-restricted held case', function () {
    $case = completeAssessment(cleanAnswers(['q12' => ['foreign_will']]));

    expect($case->is_restricted)->toBeFalse();

    $log = NotificationLog::where('case_id', $case->id)->first();

    expect($log->payload['outcome'])->toBe('Held for review');
});

it('appends the Summit identity and trade licence to every email it sends', function () {
    // The dispatcher appends the ownership line and the not-an-authority
    // statement to EVERY email body, so the identity cannot be forgotten by
    // whoever edits a template next.
    $sent = [];

    // Capture the raw body the mailer is handed.
    Mail::shouldReceive('raw')
        ->atLeast()->once()
        ->andReturnUsing(function (string $body) use (&$sent) {
            $sent[] = $body;
        });

    app(NotificationDispatcher::class)->send(
        'assessment_result_continue',
        NotificationChannel::Email,
        'someone@example.com',
        ['first_name' => 'Jordan', 'reference' => 'SLC-2026-00001'],
    );

    expect($sent)->not->toBeEmpty();

    foreach ($sent as $body) {
        expect($body)
            ->toContain(setting('branding.ownership_line'))
            ->toContain('4429232.01')
            ->toContain('not a court, registry, notary or government authority');
    }
});

it('never claims a Will is registered in any seeded template', function () {
    $forbidden = [
        'your will is registered',
        'will be accepted by the authority',
        'guaranteed registration',
        'registration is complete',
    ];

    foreach (NotificationTemplate::all() as $template) {
        $body = strtolower($template->body.' '.$template->subject);

        foreach ($forbidden as $phrase) {
            expect($body)->not->toContain($phrase,
                "Template {$template->key} ({$template->channel->value}) contains a forbidden claim");
        }
    }
});

it('never asks for a password, card number or seed phrase in any template', function () {
    $forbidden = ['seed phrase', 'private key', 'card number', 'your password', 'cvv'];

    foreach (NotificationTemplate::all() as $template) {
        $body = strtolower($template->body);

        foreach ($forbidden as $phrase) {
            // The only permitted mention is a warning NOT to send one.
            if (str_contains($body, $phrase)) {
                expect($body)->toMatch('/(never|do not|don\'t)[^.]{0,60}'.preg_quote($phrase, '/').'/',
                    "Template {$template->key} mentions '{$phrase}' outside a warning");
            }
        }
    }
});
