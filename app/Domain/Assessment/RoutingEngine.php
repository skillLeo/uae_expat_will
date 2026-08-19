<?php

namespace App\Domain\Assessment;

use App\Domain\Assessment\DTOs\AnswerSet;
use App\Domain\Assessment\DTOs\Progress;
use App\Domain\Assessment\DTOs\RoutingResult;
use App\Domain\Assessment\DTOs\TriggerReason;
use App\Domain\Assessment\Enums\ConditionAction;
use App\Domain\Assessment\Enums\Outcome;
use App\Models\Question;
use App\Models\QuestionnaireVersion;
use App\Models\RoutingRule;
use Illuminate\Support\Collection;

/**
 * The routing engine.
 *
 * It is 100% data-driven. Every question, option, visibility condition and
 * routing rule is a database row, so changing the behaviour of the assessment is
 * an edit made in the admin rule builder — never a code change. The signed
 * contract (Part B clause 5) requires exactly that.
 *
 * Two things make this harder than a lookup table, and both are handled here:
 *
 *   1. CROSS-QUESTION RULES. A rule may read an answer given many screens
 *      earlier. R-11 is "Q13A wish outside immediate family AND Q5 = Muslim →
 *      review". The engine therefore evaluates against the WHOLE AnswerSet, not
 *      against the question just answered.
 *
 *   2. PRECEDENCE, NOT FIRST-MATCH. A non-terminal rule does not stop
 *      evaluation. Every matching rule is collected and the most severe outcome
 *      governs, so a single review anywhere sends the whole case to review even
 *      if fourteen other answers said continue.
 *
 * Everything here runs SERVER-SIDE on every step. A tampered client cannot
 * reveal a hidden branch or talk its way past a stop.
 */
class RoutingEngine
{
    private QuestionnaireVersion $version;

    /** @var Collection<int, Question> */
    private Collection $questions;

    /** @var Collection<int, RoutingRule> */
    private Collection $rules;

    public function __construct(QuestionnaireVersion $version)
    {
        $this->version = $version->relationLoaded('questions')
            ? $version
            : $version->loadForEngine();

        $this->questions = $this->version->questions->sortBy('order')->values();
        $this->rules = $this->version->routingRules
            ->where('is_active', true)
            ->sortBy('priority')
            ->values();
    }

    public function version(): QuestionnaireVersion
    {
        return $this->version;
    }

    // ---------------------------------------------------------------- visibility

    /**
     * Which questions are visible given these answers.
     *
     * Rules, in order:
     *   - `show` conditions AND together. All must match, or the question is hidden.
     *   - a matching `hide` condition hides the question outright.
     *   - a matching `skip_section` condition hides every question whose
     *     section_key equals the condition's target_section_key.
     *
     * A question whose dependency has not been answered yet is hidden, because
     * its show condition cannot match. That is what keeps conditional branches
     * from appearing before the answer that opens them.
     *
     * @return Collection<int, Question>
     */
    public function visibleQuestions(AnswerSet $answers): Collection
    {
        $skippedSections = $this->skippedSections($answers);

        return $this->questions->filter(
            fn (Question $q) => $this->isVisible($q, $answers, $skippedSections)
        )->values();
    }

    public function isQuestionVisible(Question $question, AnswerSet $answers): bool
    {
        return $this->isVisible($question, $answers, $this->skippedSections($answers));
    }

    /** @param array<int, string> $skippedSections */
    private function isVisible(Question $question, AnswerSet $answers, array $skippedSections): bool
    {
        if ($question->section_key !== null && in_array($question->section_key, $skippedSections, true)) {
            return false;
        }

        $conditions = $question->conditions;

        if ($conditions->isEmpty()) {
            return true;
        }

        foreach ($conditions as $condition) {
            if ($condition->action === ConditionAction::SkipSection) {
                continue; // handled by skippedSections()
            }

            $dependsOn = $condition->dependsOnQuestion;

            if ($dependsOn === null) {
                continue;
            }

            $matches = $condition->operator->matches(
                $answers->get($dependsOn->key),
                $condition->value,
            );

            if ($condition->action === ConditionAction::Hide && $matches) {
                return false;
            }

            if ($condition->action === ConditionAction::Show && ! $matches) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> */
    private function skippedSections(AnswerSet $answers): array
    {
        $skipped = [];

        foreach ($this->questions as $question) {
            foreach ($question->conditions as $condition) {
                if ($condition->action !== ConditionAction::SkipSection) {
                    continue;
                }

                $dependsOn = $condition->dependsOnQuestion;

                if ($dependsOn === null || $condition->target_section_key === null) {
                    continue;
                }

                if ($condition->operator->matches($answers->get($dependsOn->key), $condition->value)) {
                    $skipped[] = $condition->target_section_key;
                }
            }
        }

        return array_values(array_unique($skipped));
    }

    // ------------------------------------------------------------------ traversal

    /** The next visible question that has not been answered, or null when done. */
    public function nextQuestion(AnswerSet $answers): ?Question
    {
        return $this->visibleQuestions($answers)
            ->first(fn (Question $q) => ! $answers->has($q->key));
    }

    /**
     * The previous visible question relative to $currentKey.
     * Back is always available and never destructive — the answer is retained.
     */
    public function previousQuestion(AnswerSet $answers, string $currentKey): ?Question
    {
        $visible = $this->visibleQuestions($answers);
        $index = $visible->search(fn (Question $q) => $q->key === $currentKey);

        if ($index === false || $index === 0) {
            return null;
        }

        return $visible->get($index - 1);
    }

    public function questionByKey(string $key): ?Question
    {
        return $this->questions->firstWhere('key', $key);
    }

    /** Every visible question has an answer, so the assessment can be submitted. */
    public function isComplete(AnswerSet $answers): bool
    {
        return $this->visibleQuestions($answers)
            ->filter(fn (Question $q) => $q->is_required)
            ->every(fn (Question $q) => $answers->has($q->key));
    }

    // ------------------------------------------------------------------ evaluation

    /**
     * Check whether a TERMINAL rule has fired.
     *
     * A terminal rule exits the assessment the moment it matches, skipping the
     * remaining questions and the declarations entirely — Q1 estate, Q2 under 18
     * and Q3 UAE citizen all end the journey where they stand. Call this after
     * every answer.
     *
     * Note that urgent review is NOT terminal in this sense: it has the highest
     * precedence at resolution but does not exit early, because Q15B is the last
     * question anyway.
     */
    public function checkTerminal(AnswerSet $answers): ?RoutingResult
    {
        foreach ($this->rules->where('is_terminal', true) as $rule) {
            if (! $this->ruleMatches($rule, $answers)) {
                continue;
            }

            $reasons = $this->reasonsFor($rule, $answers);

            return new RoutingResult(
                outcome: $rule->outcome,
                outcomeDetail: $rule->outcome_detail,
                triggerReasons: $reasons,
                matchedRules: [$rule->name],
                isComplete: false,
            );
        }

        return null;
    }

    /**
     * Evaluate every active rule and resolve the governing outcome.
     *
     * Collect every match, then take the most severe. Flags, reminders and route
     * marks accumulate from ALL matching rules regardless of which outcome won,
     * because a case that continues still needs its reviewer flags, and a case
     * that goes to review still needs its route marks recorded.
     */
    public function evaluate(AnswerSet $answers): RoutingResult
    {
        if ($terminal = $this->checkTerminal($answers)) {
            return $terminal;
        }

        $outcomes = [];
        $reasons = [];
        $flags = [];
        $reminders = [];
        $routeMarks = [];
        $matched = [];
        $detail = null;

        foreach ($this->rules as $rule) {
            if (! $this->ruleMatches($rule, $answers)) {
                continue;
            }

            $outcomes[] = $rule->outcome;
            $matched[] = $rule->name;
            $reasons = array_merge($reasons, $this->reasonsFor($rule, $answers));

            if ($rule->flag_key) {
                $flags[] = $rule->flag_key;
            }

            if ($rule->reminder_key) {
                $reminders[] = $rule->reminder_key;
            }

            if ($rule->route_mark_key) {
                $routeMarks[] = $rule->route_mark_key;
            }
        }

        $governing = Outcome::mostSevere($outcomes);

        // The detail line comes from the rule that actually governs, so a case
        // held for review does not explain itself with a continue rule's text.
        foreach ($this->rules as $rule) {
            if ($rule->outcome === $governing
                && $rule->outcome_detail
                && in_array($rule->name, $matched, true)) {
                $detail = $rule->outcome_detail;
                break;
            }
        }

        // Only the reasons that produced the governing outcome are worth showing.
        // A continue-flag reason alongside a review is noise in the case detail.
        $governingReasons = array_values(array_filter(
            $reasons,
            fn (TriggerReason $r) => $r->outcome === $governing,
        ));

        return new RoutingResult(
            outcome: $governing,
            outcomeDetail: $detail,
            triggerReasons: $governingReasons !== [] ? $governingReasons : $reasons,
            flags: array_values(array_unique($flags)),
            reminders: array_values(array_unique($reminders)),
            routeMarks: array_values(array_unique($routeMarks)),
            matchedRules: array_values(array_unique($matched)),
            isComplete: $this->isComplete($answers),
        );
    }

    /**
     * Does this rule match?
     *
     * Conditions sharing a group_index AND together; the groups OR together.
     * A rule with no conditions never matches automatically — a rule that fires
     * unconditionally would be a footgun in a rule builder, so the default rule
     * ("no other rule matched → continue") is expressed as the absence of any
     * match rather than as a catch-all row.
     */
    private function ruleMatches(RoutingRule $rule, AnswerSet $answers): bool
    {
        $groups = $rule->conditions->groupBy('group_index');

        if ($groups->isEmpty()) {
            return false;
        }

        foreach ($groups as $group) {
            $allMatch = true;

            foreach ($group as $condition) {
                $question = $condition->question;

                if ($question === null) {
                    $allMatch = false;
                    break;
                }

                // A condition on a question that is not visible cannot fire.
                // Otherwise a hidden branch's stale answer could route the case.
                if (! $this->isQuestionVisible($question, $answers)) {
                    $allMatch = false;
                    break;
                }

                if (! $condition->operator->matches($answers->get($question->key), $condition->value)) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                return true; // one satisfied group is enough — groups OR
            }
        }

        return false;
    }

    /**
     * Itemise why a rule fired, question by question.
     *
     * @return array<int, TriggerReason>
     */
    private function reasonsFor(RoutingRule $rule, AnswerSet $answers): array
    {
        $reasons = [];
        $seen = [];

        foreach ($rule->conditions as $condition) {
            $question = $condition->question;

            if ($question === null) {
                continue;
            }

            $answer = $answers->get($question->key);

            if ($answer === null) {
                continue;
            }

            $dedupeKey = $rule->name.'|'.$question->key;

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $reasons[] = new TriggerReason(
                ruleName: $rule->name,
                outcome: $rule->outcome,
                questionKey: $question->key,
                questionPrompt: $question->prompt,
                answerLabel: $question->labelForAnswer($answer),
                detail: $rule->outcome_detail,
                // Restriction is NOT the same thing as sensitivity, and
                // conflating them is a trap. `is_sensitive` governs encryption
                // at rest and exclusion from analytics — religion, family and
                // debt answers are all sensitive. Restriction is the much
                // narrower capacity-or-undue-influence access control, and only
                // an outcome carries it. Marking every sensitive answer
                // restricted would hide ordinary held cases from the very
                // coordinators whose job is to work them.
                isRestricted: $rule->outcome->isRestricted(),
            );
        }

        return $reasons;
    }

    // -------------------------------------------------------------------- progress

    /**
     * Progress as NAMED STAGES with a growing percentage.
     *
     * There is deliberately no question count here and none is ever sent to the
     * client. The number of questions depends on the answers, so "3 of 16" would
     * be a promise the engine cannot keep — and the client has forbidden it.
     */
    public function progress(AnswerSet $answers): Progress
    {
        $sections = $this->questions
            ->pluck('section_key')
            ->filter()
            ->unique()
            ->values();

        if ($sections->isEmpty()) {
            return new Progress('assessment', 'Assessment', [], $answers->isEmpty() ? 0 : 50);
        }

        $current = $this->nextQuestion($answers);
        $currentSection = $current?->section_key ?? $sections->last();
        $currentIndex = max(0, $sections->search($currentSection));

        $stages = $sections->map(fn (string $key, int $i) => [
            'key' => $key,
            'label' => $this->sectionLabel($key),
            'state' => match (true) {
                $i < $currentIndex => 'done',
                $i === $currentIndex => 'current',
                default => 'upcoming',
            },
        ])->all();

        // Momentum, not arithmetic. Completing the last question reads 100,
        // starting reads a non-zero number so the bar never looks stalled.
        $percent = $current === null
            ? 100
            : (int) round((($currentIndex + 0.5) / max(1, $sections->count())) * 100);

        return new Progress(
            currentStageKey: $currentSection,
            currentStageLabel: $this->sectionLabel($currentSection),
            stages: $stages,
            percent: min(100, max(5, $percent)),
        );
    }

    private function sectionLabel(string $key): string
    {
        $labels = config('assessment.section_labels', []);

        return $labels[$key] ?? ucfirst(str_replace(['_', '-'], ' ', $key));
    }
}
