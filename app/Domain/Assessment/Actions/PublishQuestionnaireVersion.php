<?php

namespace App\Domain\Assessment\Actions;

use App\Models\QuestionnaireVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Publishes a draft.
 *
 * Publishing is an explicit, authorised, audited act — never a side effect of
 * saving. The incumbent is archived in the same transaction so there is never a
 * moment with two published versions, and assessments already in flight keep
 * the version they started on because they hold a foreign key to it.
 */
class PublishQuestionnaireVersion
{
    public function execute(QuestionnaireVersion $version): QuestionnaireVersion
    {
        return DB::transaction(function () use ($version) {
            if ($version->status === 'published') {
                return $version;
            }

            $this->assertPublishable($version);

            $questionnaire = $version->questionnaire;
            $previous = $questionnaire->publishedVersion();

            $previous?->update(['status' => 'archived']);

            $version->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => Auth::guard('admin')->id(),
            ]);

            activity('questionnaire')
                ->performedOn($version)
                ->causedBy(Auth::guard('admin')->user())
                ->withProperties([
                    'version' => $version->version_number,
                    'replaced' => $previous?->version_number,
                    'questions' => $version->questions()->count(),
                    'rules' => $version->routingRules()->count(),
                ])
                ->log('Questionnaire version published');

            return $version->fresh();
        });
    }

    /**
     * Refuse to publish something that would break the assessment.
     *
     * These are cheap checks that catch the mistakes an administrator can
     * actually make in the rule builder. Publishing a questionnaire with no
     * terminal rules, or with a rule pointing at a deleted question, would take
     * the live assessment down.
     */
    private function assertPublishable(QuestionnaireVersion $version): void
    {
        $version->loadForEngine();

        if ($version->questions->isEmpty()) {
            throw new RuntimeException('This version has no questions.');
        }

        if ($version->declarations()->count() === 0) {
            throw new RuntimeException('This version has no declarations. A customer cannot submit without them.');
        }

        foreach ($version->routingRules as $rule) {
            if ($rule->conditions->isEmpty()) {
                throw new RuntimeException("Rule \"{$rule->name}\" has no conditions and would never fire.");
            }

            foreach ($rule->conditions as $condition) {
                if ($condition->question === null) {
                    throw new RuntimeException("Rule \"{$rule->name}\" points at a question that no longer exists.");
                }
            }
        }

        $outcomes = $version->resultScreens()->pluck('outcome')->map(fn ($o) => $o->value ?? $o)->all();

        foreach (['continue', 'review'] as $required) {
            if (! in_array($required, $outcomes, true)) {
                throw new RuntimeException("This version has no result screen for the \"{$required}\" outcome.");
            }
        }
    }
}
