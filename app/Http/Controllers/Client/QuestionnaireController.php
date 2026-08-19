<?php

namespace App\Http\Controllers\Client;

use App\Domain\Assessment\Actions\RecordAnswer;
use App\Domain\Assessment\Enums\QuestionType;
use App\Domain\Cases\Actions\ChangeCaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\LegalCase;
use App\Models\Question;
use App\Models\Questionnaire;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The detailed post-payment questionnaire.
 *
 * It runs on the SAME engine as the screening assessment — conditional
 * sections, save and resume, server-side visibility — because the engine is
 * generic and a second implementation would be a second set of bugs.
 */
class QuestionnaireController extends Controller
{
    public function __construct(private RecordAnswer $recordAnswer) {}

    public function show(Request $request, LegalCase $case): Response|RedirectResponse
    {
        $this->authoriseCase($request, $case);

        $assessment = $this->detailedAssessment($case);

        if ($assessment === null) {
            return redirect()->route('client.dashboard')
                ->with('error', 'The detailed questionnaire is not open for this matter yet.');
        }

        $assessment->load('answers');
        $engine = $assessment->engine();
        $answers = $assessment->answerSet();

        $question = $engine->questionByKey((string) $assessment->current_question_key)
            ?? $engine->nextQuestion($answers);

        if ($question === null) {
            return Inertia::render('Client/Questionnaire/Review', [
                // Named `record`: `case` is a reserved word in JavaScript and
                // cannot appear in a Vue template expression.
                'record' => ['id' => $case->id, 'reference' => $case->reference],
                'answers' => $engine->visibleQuestions($answers)
                    ->filter(fn (Question $q) => $answers->has($q->key))
                    ->map(fn (Question $q) => [
                        'key' => $q->key,
                        'prompt' => $q->prompt,
                        'answer' => $q->labelForAnswer($answers->get($q->key)),
                    ])->values(),
                'progress' => $engine->progress($answers)->toArray(),
            ]);
        }

        return Inertia::render('Client/Questionnaire/Question', [
            // Named `record`: `case` is a reserved word in JavaScript and
            // cannot appear in a Vue template expression.
            'record' => ['id' => $case->id, 'reference' => $case->reference],
            'question' => [
                'key' => $question->key,
                'type' => $question->type->value,
                'prompt' => $question->prompt,
                'help_text' => $question->help_text,
                'privacy_note' => $question->privacy_note,
                'security_note' => $question->security_note,
                'is_required' => $question->is_required,
                'placeholder' => $question->placeholder,
                'inputmode' => $question->type->inputMode(),
                'multiple' => $question->type->isMultiple(),
                'options' => $question->options->map(fn ($o) => [
                    'key' => $o->key, 'label' => $o->label,
                    'description' => $o->description, 'is_exclusive' => $o->is_exclusive,
                ])->values(),
            ],
            'value' => $answers->get($question->key),
            'progress' => $engine->progress($answers)->toArray(),
            'canGoBack' => $engine->previousQuestion($answers, $question->key) !== null,
            'countries' => $question->type === QuestionType::CountrySelect ? config('countries.list') : null,
        ]);
    }

    public function answer(Request $request, LegalCase $case): RedirectResponse
    {
        $this->authoriseCase($request, $case);

        $validated = $request->validate([
            'question_key' => 'required|string|max:40',
            'value' => 'present',
        ]);

        $assessment = $this->detailedAssessment($case);
        abort_if($assessment === null, 404);

        $this->recordAnswer->execute($assessment, $validated['question_key'], $validated['value']);

        return back();
    }

    public function back(Request $request, LegalCase $case): RedirectResponse
    {
        $this->authoriseCase($request, $case);

        $assessment = $this->detailedAssessment($case);
        abort_if($assessment === null, 404);

        $engine = $assessment->engine();
        $previous = $engine->previousQuestion(
            $assessment->answerSet(),
            $request->string('question_key')->toString() ?: (string) $assessment->current_question_key,
        );

        $assessment->update(['current_question_key' => $previous?->key]);

        return back();
    }

    public function submit(Request $request, LegalCase $case): RedirectResponse
    {
        $this->authoriseCase($request, $case);

        $assessment = $this->detailedAssessment($case);
        abort_if($assessment === null, 404);

        $assessment->update(['status' => 'completed', 'completed_at' => now()]);

        app(ChangeCaseStatus::class)->execute(
            $case,
            InternalStatus::InLegalReview,
            'Detailed questionnaire submitted by the customer.',
        );

        return redirect()->route('client.dashboard')
            ->with('success', 'Your instructions have been sent to the legal team.');
    }

    /**
     * The detailed assessment for this case, created on first open.
     *
     * Returns null when no detailed questionnaire has been published, so the
     * customer is told the section is not open rather than shown an empty form.
     */
    private function detailedAssessment(LegalCase $case): ?Assessment
    {
        $version = Questionnaire::where('key', 'detailed')->first()?->publishedVersion();

        if ($version === null) {
            return null;
        }

        return Assessment::firstOrCreate(
            ['questionnaire_version_id' => $version->id, 'source' => 'detailed:'.$case->reference],
            ['status' => 'in_progress', 'started_at' => now()],
        );
    }

    private function authoriseCase(Request $request, LegalCase $case): void
    {
        abort_unless($case->customer?->user_id === $request->user()->id, 403);
    }
}
