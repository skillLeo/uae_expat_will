<?php

namespace App\Domain\Assessment\Actions;

use App\Domain\Assessment\DTOs\AnswerSet;
use App\Domain\Assessment\DTOs\RoutingResult;
use App\Domain\Assessment\RoutingEngine;
use App\Domain\Assessment\Services\AnswerValidator;
use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records one answer and works out what happens next.
 *
 * Every step is re-derived server-side: the question must actually be visible
 * given the answers so far, the value is revalidated, and the terminal rules are
 * re-checked. A client that posts an answer to a hidden question — or to a
 * question it should not have reached — gets a validation error, not a branch.
 */
class RecordAnswer
{
    public function __construct(private AnswerValidator $validator) {}

    /**
     * @return array{assessment: Assessment, terminal: RoutingResult|null}
     *
     * @throws ValidationException
     */
    public function execute(Assessment $assessment, string $questionKey, mixed $value): array
    {
        if ($assessment->isCompleted()) {
            throw ValidationException::withMessages([
                'assessment' => 'This assessment has already been submitted.',
            ]);
        }

        if ($assessment->isExpired()) {
            throw ValidationException::withMessages([
                'assessment' => 'This assessment has expired. Please start again.',
            ]);
        }

        $assessment->load('answers');
        $engine = $assessment->engine();
        $question = $engine->questionByKey($questionKey);

        if ($question === null) {
            throw ValidationException::withMessages([
                'answers' => 'That question is not part of this assessment.',
            ]);
        }

        // The client does not get to decide which questions it may answer.
        if (! $engine->isQuestionVisible($question, $assessment->answerSet())) {
            throw ValidationException::withMessages([
                'answers' => 'That question does not apply to your answers.',
            ]);
        }

        $clean = $this->validator->validate($question, $value);

        return DB::transaction(function () use ($assessment, $question, $clean, $engine) {
            $answer = AssessmentAnswer::firstOrNew([
                'assessment_id' => $assessment->id,
                'question_id' => $question->id,
            ]);

            // Encryption is decided by the question, so it cannot be forgotten.
            $answer->is_encrypted = $question->is_sensitive;
            $answer->question_key = $question->key;
            $answer->value = $clean;
            $answer->answered_at = now();
            $answer->save();

            $assessment->unsetRelation('answers')->load('answers');
            $answers = $assessment->answerSet();

            // Clearing an answer can hide a branch whose answers are now stale.
            // Those must go, or a rule could fire on an invisible question.
            $this->pruneHiddenAnswers($assessment, $engine, $answers);

            $assessment->unsetRelation('answers')->load('answers');
            $answers = $assessment->answerSet();

            $terminal = $engine->checkTerminal($answers);
            $next = $engine->nextQuestion($answers);

            $assessment->current_question_key = $next?->key;
            $assessment->save();

            return ['assessment' => $assessment, 'terminal' => $terminal];
        });
    }

    private function pruneHiddenAnswers(
        Assessment $assessment,
        RoutingEngine $engine,
        AnswerSet $answers,
    ): void {
        $visible = $engine->visibleQuestions($answers)->pluck('key')->all();

        $stale = $assessment->answers
            ->reject(fn (AssessmentAnswer $a) => in_array($a->question_key, $visible, true));

        if ($stale->isNotEmpty()) {
            AssessmentAnswer::whereIn('id', $stale->pluck('id'))->delete();
        }
    }
}
