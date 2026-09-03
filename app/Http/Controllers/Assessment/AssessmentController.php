<?php

namespace App\Http\Controllers\Assessment;

use App\Domain\Assessment\Actions\RecordAnswer;
use App\Domain\Assessment\Actions\StartAssessment;
use App\Domain\Assessment\Actions\SubmitAssessment;
use App\Domain\Assessment\Enums\QuestionType;
use App\Domain\Cases\Enums\RequestType;
use App\Domain\Settings\Services\CommercialTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\StoreAnswerRequest;
use App\Http\Requests\Assessment\SubmitAssessmentRequest;
use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        // Somebody arriving with an answer to question one is telling us what
        // they want, and it must win over whatever is already in the session.
        //
        // The homepage hero navigates here as /assessment?q1=...&q2=..., and
        // this used to be read only when a brand new assessment was created.
        // So a person who had already finished was bounced to their previous
        // result — Ahmed's "option 4 gives no contact and also difc final
        // result" — and a person still mid-assessment kept the service they
        // picked the first time, whatever they clicked on the homepage.
        if ($request->filled('q1')) {
            if ($assessment->isCompleted()) {
                $assessment = $this->start->execute($request);
                $request->session()->put(self::SESSION_KEY, $assessment->session_token);
            }

            $this->seedFromHero($request, $assessment);
        }

        if ($assessment->isCompleted()) {
            return redirect()->route('assessment.result');
        }

        // Amending an existing Will and administering an estate are not Will
        // preparation. They leave the questionnaire at question one and go to
        // the request form, rather than being shown a page saying the online
        // Will service is not available.
        if ($redirect = $this->specialistRedirect($assessment)) {
            return $redirect;
        }

        return $this->render($assessment);
    }

    public function answer(StoreAnswerRequest $request): RedirectResponse
    {
        $assessment = $this->resolveOrFail($request);

        $key = $request->string('question_key')->toString();

        // A submitted assessment is the record of what somebody declared and
        // agreed to; answering into it afterwards rewrites a case that has
        // already been created from it. Somebody answering again wants a new
        // assessment, so give them one rather than silently corrupting the old.
        if ($assessment->isCompleted()) {
            $assessment = $this->start->execute($request);
            $request->session()->put(self::SESSION_KEY, $assessment->session_token);
        }

        // A stop blocks PROGRESS, not CORRECTION.
        //
        // Refusing everything after a stop locked people out permanently: one
        // mis-click on the age question and the session was dead, with no way
        // back and no way to start over. Somebody who meant to click "yes" is
        // not a person to punish.
        //
        // So a question that already has an answer can be changed — that is how
        // you undo a mistake — while a question that has never been answered
        // cannot, which is what stops somebody walking past the stop towards a
        // payment screen they are not entitled to.
        if ($assessment->engine()->checkTerminal($assessment->answerSet()) !== null
            && ! $assessment->answers()->where('question_key', $key)->exists()) {
            return back();
        }

        $this->recordAnswer->execute($assessment, $key, $request->input('value'));

        return back();
    }

    /** Sends an existing-Will or estate enquiry to the request form. */
    private function specialistRedirect(Assessment $assessment): ?RedirectResponse
    {
        $type = RequestType::fromServiceAnswer($assessment->answerSet()->get('q1'));

        if (! $type->isDirectSpecialistRequest()) {
            return null;
        }

        return redirect()->route('specialist.show', ['service' => $type->value]);
    }

    /**
     * Abandons this assessment and begins a clean one.
     *
     * The safety net behind the correction rule above. Somebody who has talked
     * themselves into a corner — or who simply wants to try again for a spouse
     * — should never have to clear cookies to do it.
     */
    public function restart(Request $request): RedirectResponse
    {
        if ($assessment = $this->resolve($request)) {
            $assessment->update(['abandoned_at_question_key' => $assessment->current_question_key]);
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('assessment.show');
    }

    public function contact(Request $request): RedirectResponse
    {
        $assessment = $this->resolveOrFail($request);

        $rules = [
            'contact_name' => 'required|string|max:120',
            'contact_email' => 'required|email:rfc|max:190',
            'contact_phone' => 'required|string|max:40',
        ];

        $names = [
            'contact_name' => 'name',
            'contact_email' => 'email address',
            'contact_phone' => 'contact number',
        ];

        // Mirror Wills: the partner's details are taken on the same screen and
        // are not optional. Agreed with Summit on 28 August 2026.
        //
        // The nationality list is the same one the nationality question uses,
        // with the UAE removed — the service is not available to UAE citizens,
        // and that has to be true of the partner as much as the applicant.
        // Because the UAE is absent from the list, an ineligible partner
        // cannot be entered at all, which is why there is no "what happens if
        // the partner is a UAE national" branch to write.
        //
        // The email is asked for twice. A typo in your own address is
        // self-correcting, because nothing arrives; a typo in someone else's
        // is not, and the partner's invitation would go silently nowhere.
        if ($assessment->isMirror()) {
            $countries = array_keys(
                collect(config('countries.list'))
                    ->except(config('countries.uae_code', 'AE'))
                    ->all()
            );

            $rules += [
                'partner_name' => 'required|string|max:120',
                'partner_nationality' => ['required', 'string', Rule::in($countries)],
                'partner_phone' => 'required|string|max:40',
                'partner_email' => 'required|email:rfc|max:190|confirmed|different:contact_email',
            ];

            $names += [
                'partner_name' => "partner's full name",
                'partner_nationality' => "partner's nationality",
                'partner_phone' => "partner's contact number",
                'partner_email' => "partner's email address",
            ];
        }

        $validated = $request->validate($rules, [
            'partner_email.confirmed' => 'The two partner email addresses do not match.',
            'partner_email.different' => 'Your partner needs their own email address, different from yours.',
        ], $names);

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
            ->where('outcome', $assessment->outcome->screenOutcome())
            ->first();

        $religion = $assessment->answerSet()->get('q5');
        $isMirror = $assessment->answerSet()->get('q1') === 'two_wills';

        // Two Wills use the same screen with its own approved wording. The
        // mirror block overrides only what it names, so anything the handoff
        // left common — the reassurance line, the eyebrow — is not duplicated.
        $extra = $screen?->extra ?? [];
        $mirror = $isMirror ? ($extra['mirror'] ?? []) : [];
        unset($extra['mirror']);
        $extra = [...$extra, ...$mirror];

        $tokens = app(CommercialTokens::class);

        return Inertia::render('Assessment/Result', [
            'outcome' => $assessment->outcome->value,
            'tone' => $assessment->outcome->tone(),
            'allowsPayment' => $assessment->outcome->allowsPayment(),
            'reference' => $request->session()->get('assessment_case_reference'),
            'screen' => $screen ? [
                'heading' => $tokens->apply($mirror['heading'] ?? $screen->heading),
                'subheading' => isset($extra['subheading']) ? $tokens->apply($extra['subheading']) : null,
                'body' => $tokens->apply($mirror['body'] ?? $screen->body),
                'primary_action_label' => $tokens->apply(
                    $mirror['primary_action_label'] ?? (string) $screen->primary_action_label
                ),
                'secondary_action_label' => $screen->secondary_action_label,
                'extra' => array_map(
                    fn ($v) => is_string($v) ? $tokens->apply($v) : $v,
                    $extra,
                ),
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
     * A contextual notice for the question on screen.
     *
     * DIFC Wills are open only to people who are not Muslim and never have
     * been. Somebody who asked for a DIFC Will at question one therefore needs
     * telling that before they answer the religion question, not after — and
     * needs telling what happens to them if the answer rules DIFC out.
     *
     * It lives here rather than on the question row because the question is
     * asked of everybody and this only applies to the DIFC path.
     *
     * @return array{heading: string, body: string, conflict_options: list<string>, conflict_body: string}|null
     */
    private function noticeFor(Question $question, $answers): ?array
    {
        if ($question->key !== 'q5' || $answers->get('q1') !== 'difc') {
            return null;
        }

        return [
            'heading' => 'DIFC eligibility notice',
            'body' => 'DIFC Wills are available only to individuals who are not Muslim and have never been Muslim. '
                .'Please answer accurately so our legal team can confirm whether DIFC is suitable or recommend '
                .'another UAE Will registration route.',
            // Shown the moment one of these is selected, before they continue.
            'conflict_options' => ['muslim', 'previously_muslim'],
            'conflict_body' => 'Based on your answer, the DIFC Wills route may not be available to you. However, '
                .'another UAE Will registration option may be suitable. Please continue the assessment, and our '
                .'legal team will review your answers and recommend the appropriate route. No payment will be '
                .'taken at this stage.',
        ];
    }

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
                'isMirror' => $assessment->isMirror(),
                'partner' => $assessment->partner(),
                // Same list as the nationality question, minus the UAE.
                'countries' => $assessment->isMirror()
                    ? collect(config('countries.list'))
                        ->except(config('countries.uae_code', 'AE'))
                        ->map(fn ($name, $code) => ['code' => $code, 'name' => $name])
                        ->values()
                    : null,
                'partnerNotice' => $assessment->isMirror()
                    ? 'The Will services available through this platform are not available to UAE citizens.'
                    : null,
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
            // The UAE is deliberately absent. The service is not available to
            // UAE citizens, and offering the option only to reject the person a
            // screen later is a poor way to tell them. It stays in the config
            // and R-03 still fires, so a crafted request is still stopped —
            // it is simply never offered.
            'countries' => $question->type === QuestionType::CountrySelect
                ? collect(config('countries.list'))
                    ->except(config('countries.uae_code', 'AE'))
                    ->all()
                : null,
            // Wording supplied by Summit, 26 August 2026. Reproduced exactly.
            'notice' => $this->noticeFor($question, $answers),
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
