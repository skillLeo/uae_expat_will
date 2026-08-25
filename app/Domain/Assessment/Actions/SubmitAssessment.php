<?php

namespace App\Domain\Assessment\Actions;

use App\Domain\Cases\Actions\CreateCaseFromAssessment;
use App\Models\Assessment;
use App\Models\Consent;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Submits a completed assessment: resolves the outcome, records the seven
 * declarations as consents, and opens the case.
 */
class SubmitAssessment
{
    public function __construct(private CreateCaseFromAssessment $createCase) {}

    /**
     * @param  array<int, int>  $acceptedDeclarationIds
     * @param  array<string, mixed>  $contact
     *
     * @throws ValidationException
     */
    public function execute(
        Assessment $assessment,
        Request $request,
        array $acceptedDeclarationIds = [],
        array $contact = [],
    ): LegalCase {
        // The details were taken after the age question, so by the time anybody
        // reaches this point they are already on the assessment. Anything posted
        // with the submission only fills a gap, it never overwrites what the
        // person actually typed.
        $contact = array_filter($contact, fn ($v) => $v !== null && $v !== '')
            + array_filter($assessment->contact(), fn ($v) => $v !== null && $v !== '');

        $assessment->load('answers', 'version.declarations');
        $engine = $assessment->engine();
        $answers = $assessment->answerSet();

        $terminal = $engine->checkTerminal($answers);

        // A terminal outcome skips the remaining questions AND the declarations —
        // there is nothing to declare about a journey that has already ended.
        if ($terminal === null) {
            if (! $engine->isComplete($answers)) {
                throw ValidationException::withMessages([
                    'assessment' => 'Some questions still need an answer.',
                ]);
            }

            $this->assertDeclarationsAccepted($assessment, $acceptedDeclarationIds);
        }

        $result = $terminal ?? $engine->evaluate($answers);

        return DB::transaction(function () use ($assessment, $request, $result, $acceptedDeclarationIds, $contact, $terminal) {
            $assessment->update([
                'status' => 'completed',
                'outcome' => $result->outcome,
                'outcome_detail' => $result->outcomeDetail,
                // Restricted reasons are stored here in full. The redaction
                // happens on the way OUT, per viewer, never on the way in —
                // otherwise authorised legal staff could not read them either.
                'trigger_reasons' => $result->triggerReasonsArray(),
                'flags' => $result->flags,
                'reminders' => $result->reminders,
                'route_marks' => $result->routeMarks,
                'completed_at' => now(),
                'current_question_key' => null,
            ]);

            if ($terminal === null) {
                $this->recordDeclarationConsents($assessment, $request, $acceptedDeclarationIds);
            }

            return $this->createCase->execute($assessment, $result, $contact);
        });
    }

    /** @param array<int, int> $accepted */
    private function assertDeclarationsAccepted(Assessment $assessment, array $accepted): void
    {
        $required = $assessment->version->declarations
            ->where('is_required', true)
            ->pluck('id')
            ->all();

        $missing = array_diff($required, $accepted);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'declarations' => 'All declarations must be accepted before you can continue.',
            ]);
        }
    }

    /** @param array<int, int> $accepted */
    private function recordDeclarationConsents(Assessment $assessment, Request $request, array $accepted): void
    {
        foreach ($assessment->version->declarations->whereIn('id', $accepted) as $declaration) {
            Consent::create([
                'assessment_id' => $assessment->id,
                'type' => 'declaration',
                'version' => (string) $assessment->version->version_number,
                // The hash is what proves WHICH wording was on screen, not merely
                // that a box was ticked.
                'wording_hash' => $declaration->wordingHash(),
                'accepted' => true,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'language' => app()->getLocale(),
                'related_reference' => $assessment->uuid,
                'accepted_at' => now(),
            ]);
        }
    }
}
