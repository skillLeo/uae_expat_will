<?php

namespace App\Http\Controllers\Assessment;

use App\Domain\Assessment\Actions\RecordAnswer;
use App\Domain\Assessment\Actions\StartAssessment;
use App\Domain\Assessment\Actions\SubmitAssessment;
use App\Domain\Assessment\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\StoreAnswerRequest;
use App\Http\Requests\Assessment\SubmitAssessmentRequest;
use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The assessment.
 *
 * No account. No payment. One question per screen, back always available and
 * never destructive, and the whole thing re-derived server-side on every step so
 * a tampered client cannot reveal a hidden branch or skip a stop.
 */
class AssessmentController extends Controller
{
    private const SESSION_KEY = 'assessment_token';

    public function __construct(
        private StartAssessment $start,
        private RecordAnswer $recordAnswer,
        private SubmitAssessment $submit,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $assessment = $this->resolve($request);

        if ($assessment === null) {
            $assessment = $this->start->execute($request);
            $request->session()->put(self::SESSION_KEY, $assessment->session_token);

            // The homepage hero can pass the first two answers straight through.
            $this->seedFromHero($request, $assessment);
        }

        if ($assessment->isCompleted()) {
            return redirect()->route('assessment.result');
        }

        return $this->render($assessment);
    }

    public function answer(StoreAnswerRequest $request): RedirectResponse
    {
        $assessment = $this->resolveOrFail($request);

        $this->recordAnswer->execute(
            $assessment,
            $request->string('question_key')->toString(),
            $request->input('value'),
        );

        return back();
    }

    public function contact(Request $request): RedirectResponse
    {
        $assessment = $this->resolveOrFail($request);

        $validated = $request->validate([
            'contact_name' => 'required|string|max:120',
            'contact_email' => 'required|email:rfc|max:190',
            'contact_phone' => 'required|string|max:40',
        ], [], [
            'contact_name' => 'name',
            'contact_email' => 'email address',
            'contact_phone' => 'contact number',
        ]);

        $assessment->update([...$validated, 'contact_captured_at' => now()]);

        return back();
    }

    /** Back is never destructive — the answer stays, we simply move the cursor. */
    public function back(Request $request): RedirectResponse
    {
        $assessment = $this->resolveOrFail($request);
        $engine = $assessment->engine();

        $current = $request->string('question_key')->toString()
            ?: (string) $assessment->current_question_key;

        $previous = $engine->previousQuestion($assessment->answerSet(), $current);

        $assessment->update(['current_question_key' => $previous?->key]);

        return back();
    }

    public function submit(SubmitAssessmentRequest $request): RedirectResponse
    {
        $assessment = $this->resolveOrFail($request);

        $case = $this->submit->execute(
            $assessment,
            $request,
            $request->input('declarations', []),
            $request->input('contact', []),
        );

        $request->session()->put('assessment_case_reference', $case->reference);

        return redirect()->route('assessment.result');
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $assessment = $this->resolve($request);

        if ($assessment === null || ! $assessment->isCompleted()) {
            return redirect()->route('assessment.show');
        }

        $screen = $assessment->version
            ->resultScreens()
            ->where('outcome', $assessment->outcome)
            ->first();

        $religion = $assessment->answerSet()->get('q5');

        return Inertia::render('Assessment/Result', [
            'outcome' => $assessment->outcome->value,
            'tone' => $assessment->outcome->tone(),
            'allowsPayment' => $assessment->outcome->allowsPayment(),
            'reference' => $request->session()->get('assessment_case_reference'),
            'screen' => $screen ? [
                'heading' => $screen->heading,
                'body' => $screen->body,
                'primary_action_label' => $screen->primary_action_label,
                'secondary_action_label' => $screen->secondary_action_label,
                'extra' => $screen->extra,
            ] : null,
            // The route note depends on religion, but the REASON a case was held
            // is never sent to the client — only the neutral screen copy is.
            'routeNote' => match ($religion) {
                'muslim' => $screen->extra['muslim_note'] ?? null,
                'non_muslim' => $screen->extra['non_muslim_note'] ?? null,
                default => null,
            },
            // Two Wills carry their own price, not twice the single fee.
            'fee' => [
                'amount' => $assessment->answerSet()->get('q1') === 'two_wills'
                    ? (float) setting('commercial.mirror_fee', 2999)
                    : (float) setting('commercial.standard_fee', 1999),
                'vat_rate' => (int) setting('commercial.vat_rate', 5),
                'currency' => setting('commercial.currency', 'AED'),
                'is_mirror' => $assessment->answerSet()->get('q1') === 'two_wills',
            ],
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Asked once, after q2 is answered, and never again.
     *
     * Gated on the answer rather than on a cursor position so that a hero
     * pre-fill, a resumed session or a jump backwards all land in the same
     * place — the question is "do we know who this is yet", not "which screen
     * were they on".
     */
    private function needsContact(Assessment $assessment, $answers): bool
    {
        return $answers->get('q2') !== null && ! $assessment->hasContact();
    }

    private function render(Assessment $assessment): Response
    {
        $assessment->load('answers');
        $engine = $assessment->engine();
        $answers = $assessment->answerSet();

        // If a terminal rule has already fired, the journey ends here — the
        // remaining questions and the declarations are skipped entirely.
        if ($terminal = $engine->checkTerminal($answers)) {
            $screen = $assessment->version->resultScreens()
                ->where('outcome', $terminal->outcome)->first();

            return Inertia::render('Assessment/Terminal', [
                'outcome' => $terminal->outcome->value,
                'tone' => $terminal->outcome->tone(),
                'detail' => $terminal->outcomeDetail,
                'screen' => $screen?->only(['heading', 'body', 'primary_action_label']),
                'token' => $assessment->session_token,
            ]);
        }

        // Contact details are asked for once, straight after the age question.
        // That is the first point at which the person is known to be eligible,
        // and it means somebody who abandons at question nine still leaves a
        // lead Summit can follow up rather than vanishing.
        if ($this->needsContact($assessment, $answers)) {
            return Inertia::render('Assessment/Contact', [
                'progress' => $engine->progress($answers)->toArray(),
                'contact' => $assessment->contact(),
            ]);
        }

        $question = $engine->questionByKey((string) $assessment->current_question_key)
            ?? $engine->nextQuestion($answers);

        // Every visible question answered — time for the review and declarations.
        if ($question === null) {
            return Inertia::render('Assessment/Review', [
                'answers' => $this->answerSummary($engine, $answers),
                'declarations' => $assessment->version->declarations
                    ->map(fn ($d) => ['id' => $d->id, 'text' => $d->text])
                    ->values(),
                'progress' => $engine->progress($answers)->toArray(),
            ]);
        }

        return Inertia::render('Assessment/Question', [
            'question' => $this->questionPayload($question),
            'value' => $answers->get($question->key),
            'progress' => $engine->progress($answers)->toArray(),
            'canGoBack' => $engine->previousQuestion($answers, $question->key) !== null,
            'countries' => $question->type === QuestionType::CountrySelect
                ? config('countries.list')
                : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function questionPayload(Question $question): array
    {
        return [
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
                'key' => $o->key,
                'label' => $o->label,
                'description' => $o->description,
                'is_exclusive' => $o->is_exclusive,
            ])->values(),
        ];
    }

    /** @return array<int, array<string, string>> */
    private function answerSummary($engine, $answers): array
    {
        return $engine->visibleQuestions($answers)
            ->filter(fn (Question $q) => $answers->has($q->key))
            ->map(fn (Question $q) => [
                'key' => $q->key,
                'prompt' => $q->prompt,
                'answer' => $q->labelForAnswer($answers->get($q->key)),
            ])
            ->values()
            ->all();
    }

    private function resolve(Request $request): ?Assessment
    {
        $token = $request->session()->get(self::SESSION_KEY);

        if (! $token) {
            return null;
        }

        $assessment = Assessment::where('session_token', $token)->first();

        if ($assessment === null || ($assessment->isExpired() && ! $assessment->isCompleted())) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $assessment;
    }

    private function resolveOrFail(Request $request): Assessment
    {
        return $this->resolve($request) ?? abort(419, 'Your assessment session has expired. Please start again.');
    }

    /**
     * Carries the homepage hero's two answers into the real assessment.
     *
     * They are recorded through the same action as every other answer, so they
     * are validated and routed identically — the hero gets no shortcut.
     */
    private function seedFromHero(Request $request, Assessment $assessment): void
    {
        foreach (['q1', 'q2'] as $key) {
            $value = $request->query($key);

            if (! is_string($value) || $value === '') {
                continue;
            }

            try {
                $this->recordAnswer->execute($assessment, $key, $value);
            } catch (\Throwable) {
                // A malformed hero parameter simply starts a clean assessment.
                break;
            }
        }
    }
}
