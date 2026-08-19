<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    /**
     * Records a cookie choice server-side.
     *
     * localStorage alone is not a consent record — it is the visitor's own copy.
     * This row carries the wording version, the timestamp, the IP and the
     * language, which is what makes the consent evidential and exportable.
     */
    public function cookie(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'choice' => 'required|string|in:accept_all,reject_non_essential,manage_preferences',
            'preferences' => 'array',
            'preferences.analytics' => 'boolean',
            'preferences.functional' => 'boolean',
            'version' => 'required|string|max:20',
        ]);

        Consent::create([
            'type' => 'cookie',
            'version' => $validated['version'],
            'wording_hash' => Consent::hashWording(
                $validated['version'].'|'.json_encode($validated['preferences'] ?? [])
            ),
            'accepted' => $validated['choice'] !== 'reject_non_essential',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'language' => app()->getLocale(),
            'accepted_at' => now(),
        ]);

        return response()->json(['recorded' => true]);
    }
}
