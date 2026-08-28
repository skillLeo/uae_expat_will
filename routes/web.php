<?php

use App\Http\Controllers\Public\BlogController;
use App\Http\Controllers\Public\ConsentController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\SpecialistRequestController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
| All 13 pages render from the database. The slugs are fixed here rather than
| resolved from the pages table so a content edit can never change a URL — a
| moved URL is an SEO incident, not a content change.
*/

$pages = [
    '/' => 'home',
    'how-it-works' => 'how_it_works',
    'do-you-need-a-uae-will' => 'do_you_need',
    'uae-will-registration-options' => 'will_options',
    'pricing' => 'pricing',
    'faqs' => 'faqs',
    'about-us' => 'about',
    'contact' => 'contact',
    'terms-and-conditions' => 'terms',
    'privacy-policy' => 'privacy',
    'payment-and-refund-policy' => 'refund',
    'legal-disclaimer' => 'disclaimer',
    'cookie-policy' => 'cookies',
];

foreach ($pages as $slug => $key) {
    Route::get($slug, [PageController::class, 'show'])
        ->defaults('key', $key)
        ->name('pages.'.$key);
}

Route::post('consent/cookie', [ConsentController::class, 'cookie'])
    ->middleware('throttle:60,1')
    ->name('consent.cookie');

/*
|--------------------------------------------------------------------------
| Specialist legal review requests
|--------------------------------------------------------------------------
| Amending an existing Will and administering an estate after a death are
| separate legal services. Per Ahmed's instruction of 25 August they skip the
| questionnaire entirely and go straight to the team.
*/
Route::middleware('throttle:assessment')->group(function () {
    Route::get('specialist-request/{service}', [SpecialistRequestController::class, 'show'])->name('specialist.show');
    Route::post('specialist-request/{service}/contact', [SpecialistRequestController::class, 'contact'])->name('specialist.contact');
    Route::post('specialist-request/{service}', [SpecialistRequestController::class, 'submit'])->name('specialist.submit');
    Route::get('request-received', [SpecialistRequestController::class, 'received'])->name('specialist.received');
});

Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
| CSRF-exempt (the gateway has no session), signature-verified inside the
| controller, and throttled so a flood cannot be used as a denial of service.
*/
Route::post('webhooks/payment', PaymentWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.payment');

require __DIR__.'/assessment.php';
