<?php

namespace App\Domain\Assessment\Actions;

use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Opens a new draft by deep-copying the published version.
 *
 * The copy is deep on purpose. If a draft shared rows with the published
 * version, editing the draft would silently change the live assessment for
 * everyone mid-flight — which is exactly what version control is meant to
 * prevent.
 */
class CreateDraftVersion
{
    public function execute(Questionnaire $questionnaire, ?string $notes = null): QuestionnaireVersion
    {
        return DB::transaction(function () use ($questionnaire, $notes) {
            if ($existing = $questionnaire->draftVersion()) {
                return $existing;
            }

            $source = $questionnaire->publishedVersion();
            $next = ((int) $questionnaire->versions()->max('version_number')) + 1;

            $draft = $questionnaire->versions()->create([
                'version_number' => $next,
                'status' => 'draft',
                'notes' => $notes,
            ]);

            if ($source === null) {
                return $draft;
            }

            $source->loadForEngine();
            $source->load('declarations', 'resultScreens');

            // question id in the source => question id in the draft
            $map = [];

            foreach ($source->questions as $question) {
                $copy = $draft->questions()->create(
                    collect($question->getAttributes())
                        ->except(['id', 'questionnaire_version_id', 'created_at', 'updated_at', 'deleted_at'])
                        ->all()
                );

                $map[$question->id] = $copy->id;

                foreach ($question->options as $option) {
                    $copy->options()->create(
                        collect($option->getAttributes())
                            ->except(['id', 'question_id', 'created_at', 'updated_at'])
                            ->all()
                    );
                }
            }

            // Conditions and rule conditions are remapped in a second pass,
            // because a condition can point at a question defined after it.
            foreach ($source->questions as $question) {
                foreach ($question->conditions as $condition) {
                    if (! isset($map[$condition->depends_on_question_id])) {
                        continue;
                    }

                    DB::table('question_conditions')->insert([
                        'question_id' => $map[$question->id],
                        'depends_on_question_id' => $map[$condition->depends_on_question_id],
                        'operator' => $condition->operator->value,
                        'value' => json_encode($condition->value),
                        'action' => $condition->action->value,
                        'target_section_key' => $condition->target_section_key,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            foreach ($source->routingRules as $rule) {
                $copy = $draft->routingRules()->create(
                    collect($rule->getAttributes())
                        ->except(['id', 'questionnaire_version_id', 'created_at', 'updated_at', 'deleted_at'])
                        ->all()
                );

                foreach ($rule->conditions as $condition) {
                    if (! isset($map[$condition->question_id])) {
                        continue;
                    }

                    $copy->conditions()->create([
                        'question_id' => $map[$condition->question_id],
                        'operator' => $condition->operator,
                        'value' => $condition->value,
                        'group_index' => $condition->group_index,
                        'group_operator' => $condition->group_operator,
                    ]);
                }
            }

            foreach ($source->declarations as $declaration) {
                $draft->declarations()->create(
                    collect($declaration->getAttributes())
                        ->except(['id', 'questionnaire_version_id', 'created_at', 'updated_at'])
                        ->all()
                );
            }

            foreach ($source->resultScreens as $screen) {
                $draft->resultScreens()->create(
                    collect($screen->getAttributes())
                        ->except(['id', 'questionnaire_version_id', 'created_at', 'updated_at'])
                        ->all()
                );
            }

            activity('questionnaire')
                ->performedOn($draft)
                ->causedBy(Auth::guard('admin')->user())
                ->withProperties(['version' => $next, 'copied_from' => $source->version_number])
                ->log('Draft version created');

            return $draft;
        });
    }
}
