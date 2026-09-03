<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- The three type roles: Newsreader for display, Instrument Sans for every
         interface string, IBM Plex Mono for references, timestamps and money. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500&family=Instrument+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

    @if ($verification = setting('analytics.search_console_verification'))
        <meta name="google-site-verification" content="{{ $verification }}">
    @endif

    @if ($ga4 = setting('analytics.ga4_measurement_id'))
        {{-- Google tag, under Consent Mode v2.

             The tag loads on every page but is told, before it does anything,
             that it has no permission to store or read anything. No analytics
             cookie and no identifier exists until the visitor accepts, so the
             promise in the cookie banner still holds exactly.

             It is done this way rather than by withholding the script because
             a script that is absent until someone clicks Accept cannot be
             found by Google's own "Test installation" check, which does not
             accept cookies. That reads as a broken install when it is not.

             wait_for_update gives the banner a moment to restore a previously
             saved choice before the first measurement decision is made. --}}
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('consent', 'default', {
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
                analytics_storage: 'denied',
                wait_for_update: 500
            });
            gtag('js', new Date());
            {{-- Page paths only. Analytics must never receive questionnaire
                 answers, religion, family or beneficiary detail, or document
                 names. --}}
            gtag('config', @json($ga4), { anonymize_ip: true, allow_google_signals: false });
        </script>
    @endif

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="antialiased">
    <a href="#main" class="skip-link">Skip to content</a>
    @inertia
</body>
</html>
