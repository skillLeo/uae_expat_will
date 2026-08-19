<?php

use App\Http\Controllers\Public\ConsentController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SitemapController;
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

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

require __DIR__.'/assessment.php';
