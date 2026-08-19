<?php

namespace App\Domain\Assessment\Actions;

use App\Models\Assessment;
use App\Models\Questionnaire;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Starts an assessment. No account, no payment.
 *
 * The assessment is pinned to whatever version is published RIGHT NOW, and stays
 * on it for its whole life. Publishing a new version mid-flight must never
 * change the questions under someone who is halfway through answering them.
 */
class StartAssessment
{
    public function execute(Request $request, array $attribution = []): Assessment
    {
        $version = Questionnaire::screening()?->publishedVersion();

        if ($version === null) {
            throw new RuntimeException('No published questionnaire version is available.');
        }

        return Assessment::create([
            'questionnaire_version_id' => $version->id,
            'status' => 'in_progress',
            'source' => $attribution['source'] ?? $request->query('source'),
            'campaign' => $attribution['campaign'] ?? $request->query('campaign'),
            'utm' => $this->utm($request),
            'referrer' => substr((string) $request->headers->get('referer'), 0, 2000) ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    /** @return array<string, string>|null */
    private function utm(Request $request): ?array
    {
        $utm = collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])
            ->mapWithKeys(fn (string $key) => [$key => $request->query($key)])
            ->filter()
            ->all();

        return $utm === [] ? null : $utm;
    }
}
