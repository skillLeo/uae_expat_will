<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Assessment\Actions\CreateDraftVersion;
use App\Domain\Assessment\Actions\PublishQuestionnaireVersion;
use App\Domain\Assessment\Actions\RollbackQuestionnaireVersion;
use App\Domain\Assessment\Enums\ConditionAction;
use App\Domain\Assessment\Enums\ConditionOperator;
use App\Domain\Assessment\Enums\Outcome;
use App\Domain\Assessment\Enums\QuestionType;
use App\Domain\Assessment\Services\QuestionnairePreview;
use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Models\QuestionOption;
use App\Models\RoutingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The questionnaire and routing editor.
 *
 * Everything a non-technical administrator needs to change the assessment
 * without a developer: question and option CRUD, the condition builder, the
 * rule builder, preview against test answers, publish, history and rollback.
 *
 * Edits are only ever accepted against a DRAFT version. A published version is
 * immutable — that is what makes rollback meaningful and stops a live
 * assessment changing under someone halfway through it.
 */
class QuestionnaireController extends Controller
{
    public function __construct(
        private CreateDraftVersion $createDraft,
        private PublishQuestionnaireVersion $publish,
        private RollbackQuestionnaireVersion $rollback,
        private QuestionnairePreview $preview,
    ) {}

    public function index(): Response
    {
        $questionnaire = Questionnaire::screening();

        return Inertia::render('Admin/Questionnaire/Index', [
            'questionnaire' => $questionnaire?->only(['id', 'key', 'name', 'description']),
            'versions' => $questionnaire?->versions()
                ->with('publisher:id,name')
                ->orderByDesc('version_number')
                ->get()
                ->map(fn (QuestionnaireVersion $v) => [
                    'id' => $v->id,
                    'version_number' => $v->version_number,
                    'status' => $v->status,
                    'notes' => $v->notes,
                    'published_at' => $v->published_at?->toIso8601String(),
                    'published_by' => $v->publisher?->name,
                    'questions' => $v->questions()->count(),
                    'rules' => $v->routingRules()->count(),
                    'assessments' => $v->assessments()->count(),
                ]) ?? [],
        ]);
    }

    public function show(QuestionnaireVersion $version): Response
    {
        $version->loadForEngine();
        $version->load('declarations', 'resultScreens');

        return Inertia::render('Admin/Questionnaire/Edit', [
            'version' => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'status' => $version->status,
                'notes' => $version->notes,
                'editable' => $version->isDraft(),
            ],
            'questions' => $version->questions->map(fn (Question $q) => [
                'id' => $q->id,
                'key' => $q->key,
                'order' => $q->order,
                'type' => $q->type->value,
                'prompt' => $q->prompt,
                'help_text' => $q->help_text,
                'privacy_note' => $q->privacy_note,
                'security_note' => $q->security_note,
                'is_required' => $q->is_required,
                'is_sensitive' => $q->is_sensitive,
                'section_key' => $q->section_key,
                'options' => $q->options->map(fn ($o) => [
                    'id' => $o->id, 'key' => $o->key, 'label' => $o->label,
                    'description' => $o->description, 'is_exclusive' => $o->is_exclusive,
                    'order' => $o->order,
                ])->values(),
                'conditions' => $q->conditions->map(fn ($c) => [
                    'id' => $c->id,
                    'depends_on_question_id' => $c->depends_on_question_id,
                    'depends_on_key' => $c->dependsOnQuestion?->key,
                    'operator' => $c->operator->value,
                    'value' => $c->value,
                    'action' => $c->action->value,
                    'target_section_key' => $c->target_section_key,
                    'sentence' => $c->describe(),
                ])->values(),
            ])->values(),
            'rules' => $version->routingRules->map(fn (RoutingRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'priority' => $r->priority,
                'outcome' => $r->outcome->value,
                'outcome_label' => $r->outcome->label(),
                'tone' => $r->outcome->tone(),
                'outcome_detail' => $r->outcome_detail,
                'flag_key' => $r->flag_key,
                'reminder_key' => $r->reminder_key,
                'route_mark_key' => $r->route_mark_key,
                'is_terminal' => $r->is_terminal,
                'is_active' => $r->is_active,
                // The plain-English rendering the rule builder shows.
                'sentence' => $r->describe(),
                'conditions' => $r->conditions->map(fn ($c) => [
                    'id' => $c->id,
                    'question_id' => $c->question_id,
                    'question_key' => $c->question?->key,
                    'operator' => $c->operator->value,
                    'value' => $c->value,
                    'group_index' => $c->group_index,
                ])->values(),
            ])->values(),
            'declarations' => $version->declarations->map(fn ($d) => [
                'id' => $d->id, 'order' => $d->order, 'text' => $d->text, 'is_required' => $d->is_required,
            ])->values(),
            'resultScreens' => $version->resultScreens->map(fn ($s) => [
                'id' => $s->id, 'outcome' => $s->outcome->value, 'heading' => $s->heading,
                'body' => $s->body, 'primary_action_label' => $s->primary_action_label,
                'secondary_action_label' => $s->secondary_action_label,
            ])->values(),
            'meta' => [
                'question_types' => collect(QuestionType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => str($t->value)->replace('_', ' ')->ucfirst()])->values(),
                'operators' => collect(ConditionOperator::cases())->map(fn ($o) => ['value' => $o->value, 'label' => $o->label()])->values(),
                'actions' => collect(ConditionAction::cases())->map(fn ($a) => ['value' => $a->value, 'label' => $a->label()])->values(),
                'outcomes' => collect(Outcome::cases())->map(fn ($o) => [
                    'value' => $o->value, 'label' => $o->label(), 'tone' => $o->tone(),
                    'terminal' => $o->isTerminal(), 'allows_payment' => $o->allowsPayment(),
                ])->values(),
                'sections' => array_map(
                    fn ($k, $v) => ['value' => $k, 'label' => $v],
                    array_keys(config('assessment.section_labels', [])),
                    array_values(config('assessment.section_labels', [])),
                ),
            ],
        ]);
    }

    // ------------------------------------------------------------ versioning

    public function createDraft(): RedirectResponse
    {
        $draft = $this->createDraft->execute(Questionnaire::screening());

        return redirect()
            ->route('admin.questionnaire.show', $draft)
            ->with('success', "Draft version {$draft->version_number} is ready to edit.");
    }

    public function publish(QuestionnaireVersion $version): RedirectResponse
    {
        try {
            $this->publish->execute($version);
        } catch (\RuntimeException $e) {
            // The publish guard's message is written for an administrator to
            // act on, so surface it rather than a generic failure.
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Version {$version->version_number} is now live.");
    }

    public function rollback(QuestionnaireVersion $version): RedirectResponse
    {
        $this->rollback->execute($version);

        return back()->with('success', "Rolled back to version {$version->version_number}.");
    }

    /** Runs a draft against test answers. Writes nothing. */
    public function runPreview(Request $request, QuestionnaireVersion $version): JsonResponse
    {
        $validated = $request->validate(['answers' => 'array']);

        return response()->json(
            $this->preview->run($version, $validated['answers'] ?? [])
        );
    }

    // --------------------------------------------------------------- editing

    public function storeQuestion(Request $request, QuestionnaireVersion $version): RedirectResponse
    {
        $this->assertDraft($version);

        $validated = $request->validate([
            'key' => 'required|string|max:40|regex:/^[a-z0-9_]+$/',
            'type' => 'required|string|in:'.collect(QuestionType::cases())->pluck('value')->implode(','),
            'prompt' => 'required|string|max:2000',
            'help_text' => 'nullable|string|max:2000',
            'privacy_note' => 'nullable|string|max:2000',
            'security_note' => 'nullable|string|max:2000',
            'section_key' => 'nullable|string|max:40',
            'is_required' => 'boolean',
            'is_sensitive' => 'boolean',
        ]);

        $version->questions()->create($validated + [
            'order' => ((int) $version->questions()->max('order')) + 10,
        ]);

        return back()->with('success', 'Question added.');
    }

    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $this->assertDraft($question->version);

        $question->update($request->validate([
            'prompt' => 'sometimes|string|max:2000',
            'help_text' => 'nullable|string|max:2000',
            'privacy_note' => 'nullable|string|max:2000',
            'security_note' => 'nullable|string|max:2000',
            'section_key' => 'nullable|string|max:40',
            'is_required' => 'boolean',
            'is_sensitive' => 'boolean',
            'type' => 'sometimes|string|in:'.collect(QuestionType::cases())->pluck('value')->implode(','),
        ]));

        return back()->with('success', 'Question updated.');
    }

    public function destroyQuestion(Question $question): RedirectResponse
    {
        $this->assertDraft($question->version);
        $question->delete();

        return back()->with('success', 'Question removed.');
    }

    public function reorderQuestions(Request $request, QuestionnaireVersion $version): RedirectResponse
    {
        $this->assertDraft($version);

        $validated = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        DB::transaction(function () use ($validated, $version) {
            foreach ($validated['order'] as $i => $id) {
                $version->questions()->whereKey($id)->update(['order' => ($i + 1) * 10]);
            }
        });

        return back();
    }

    public function storeOption(Request $request, Question $question): RedirectResponse
    {
        $this->assertDraft($question->version);

        $validated = $request->validate([
            'key' => 'required|string|max:60|regex:/^[a-z0-9_]+$/',
            'label' => 'required|string|max:500',
            'description' => 'nullable|string|max:1000',
            'is_exclusive' => 'boolean',
        ]);

        // At most one exclusive option per question — two would be undefined.
        if ($validated['is_exclusive'] ?? false) {
            $question->options()->update(['is_exclusive' => false]);
        }

        $question->options()->create($validated + [
            'order' => ((int) $question->options()->max('order')) + 10,
        ]);

        return back()->with('success', 'Option added.');
    }

    public function updateOption(Request $request, QuestionOption $option): RedirectResponse
    {
        $this->assertDraft($option->question->version);

        $validated = $request->validate([
            'label' => 'sometimes|string|max:500',
            'description' => 'nullable|string|max:1000',
            'is_exclusive' => 'boolean',
        ]);

        if ($validated['is_exclusive'] ?? false) {
            $option->question->options()->where('id', '!=', $option->id)->update(['is_exclusive' => false]);
        }

        $option->update($validated);

        return back()->with('success', 'Option updated.');
    }

    public function destroyOption(QuestionOption $option): RedirectResponse
    {
        $this->assertDraft($option->question->version);
        $option->delete();

        return back()->with('success', 'Option removed.');
    }

    public function storeCondition(Request $request, Question $question): RedirectResponse
    {
        $this->assertDraft($question->version);

        $question->conditions()->create($request->validate([
            'depends_on_question_id' => 'required|exists:questions,id',
            'operator' => 'required|string|in:'.collect(ConditionOperator::cases())->pluck('value')->implode(','),
            'value' => 'nullable',
            'action' => 'required|string|in:'.collect(ConditionAction::cases())->pluck('value')->implode(','),
            'target_section_key' => 'nullable|string|max:40',
        ]));

        return back()->with('success', 'Condition added.');
    }

    public function destroyCondition(QuestionCondition $condition): RedirectResponse
    {
        $this->assertDraft($condition->question->version);
        $condition->delete();

        return back()->with('success', 'Condition removed.');
    }

    public function storeRule(Request $request, QuestionnaireVersion $version): RedirectResponse
    {
        $this->assertDraft($version);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'priority' => 'required|integer|min:1|max:9999',
            'outcome' => 'required|string|in:'.collect(Outcome::cases())->pluck('value')->implode(','),
            'outcome_detail' => 'nullable|string|max:2000',
            'flag_key' => 'nullable|string|max:60',
            'reminder_key' => 'nullable|string|max:60',
            'route_mark_key' => 'nullable|string|max:60',
            'is_terminal' => 'boolean',
            'is_active' => 'boolean',
            'conditions' => 'required|array|min:1',
            'conditions.*.question_id' => 'required|exists:questions,id',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'nullable',
            'conditions.*.group_index' => 'integer|min:0',
        ]);

        DB::transaction(function () use ($version, $validated) {
            $rule = $version->routingRules()->create(collect($validated)->except('conditions')->all());

            foreach ($validated['conditions'] as $condition) {
                $rule->conditions()->create([
                    'question_id' => $condition['question_id'],
                    'operator' => $condition['operator'],
                    'value' => $condition['value'] ?? null,
                    'group_index' => $condition['group_index'] ?? 0,
                    'group_operator' => 'and',
                ]);
            }
        });

        return back()->with('success', 'Rule added.');
    }

    public function updateRule(Request $request, RoutingRule $rule): RedirectResponse
    {
        $this->assertDraft($rule->version);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'priority' => 'sometimes|integer|min:1|max:9999',
            'outcome' => 'sometimes|string',
            'outcome_detail' => 'nullable|string|max:2000',
            'flag_key' => 'nullable|string|max:60',
            'reminder_key' => 'nullable|string|max:60',
            'route_mark_key' => 'nullable|string|max:60',
            'is_terminal' => 'boolean',
            'is_active' => 'boolean',
            'conditions' => 'sometimes|array|min:1',
            'conditions.*.question_id' => 'required_with:conditions|exists:questions,id',
            'conditions.*.operator' => 'required_with:conditions|string',
            'conditions.*.value' => 'nullable',
            'conditions.*.group_index' => 'integer|min:0',
        ]);

        DB::transaction(function () use ($rule, $validated) {
            $rule->update(collect($validated)->except('conditions')->all());

            if (isset($validated['conditions'])) {
                $rule->conditions()->delete();

                foreach ($validated['conditions'] as $condition) {
                    $rule->conditions()->create([
                        'question_id' => $condition['question_id'],
                        'operator' => $condition['operator'],
                        'value' => $condition['value'] ?? null,
                        'group_index' => $condition['group_index'] ?? 0,
                        'group_operator' => 'and',
                    ]);
                }
            }
        });

        return back()->with('success', 'Rule updated.');
    }

    public function destroyRule(RoutingRule $rule): RedirectResponse
    {
        $this->assertDraft($rule->version);
        $rule->delete();

        return back()->with('success', 'Rule removed.');
    }

    /**
     * A published version is immutable.
     *
     * Without this, "edit" and "publish" would be the same act and rollback
     * would mean nothing.
     */
    private function assertDraft(QuestionnaireVersion $version): void
    {
        abort_unless($version->isDraft(), 403, 'Only a draft version can be edited. Create a new draft first.');
    }
}
