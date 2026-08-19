<?php

namespace App\Domain\Assessment\Services;

use App\Domain\Assessment\DTOs\AnswerSet;
use App\Domain\Assessment\RoutingEngine;
use App\Models\QuestionnaireVersion;

/**
 * Runs a DRAFT version against test answers without saving anything.
 *
 * Ahmed's own note was that he needs "two, three times review on the wording",
 * so this is not a nice-to-have — it is the thing that lets him check a rule
 * change before it reaches a customer. Nothing here writes: no assessment row,
 * no answers, no case, no notification.
 */
class QuestionnairePreview
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function run(QuestionnaireVersion $version, array $answers): array
    {
        $engine = new RoutingEngine($version);
        $set = AnswerSet::make($answers);

        $result = $engine->evaluate($set);
        $visible = $engine->visibleQuestions($set);
        $next = $engine->nextQuestion($set);

        return [
            'outcome' => $result->outcome->value,
            'outcome_label' => $result->outcome->label(),
            'tone' => $result->outcome->tone(),
            'is_terminal' => $result->isTerminal(),
            'allows_payment' => $result->allowsPayment(),
            'is_restricted' => $result->isRestricted(),
            'detail' => $result->outcomeDetail,
            'matched_rules' => $result->matchedRules,
            'trigger_reasons' => $result->triggerReasonsArray(),
            'flags' => $result->flags,
            'reminders' => $result->reminders,
            'route_marks' => $result->routeMarks,
            'is_complete' => $engine->isComplete($set),
            'visible_questions' => $visible->pluck('key')->all(),
            'hidden_questions' => $version->questions
                ->pluck('key')
                ->diff($visible->pluck('key'))
                ->values()
                ->all(),
            'next_question' => $next?->key,
            'progress' => $engine->progress($set)->toArray(),
        ];
    }
}
