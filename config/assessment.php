<?php

return [
    /*
    |---------------------------------------------------------------------------
    | Named progress stages
    |---------------------------------------------------------------------------
    | The assessment shows progress as named stages and NEVER as a question
    | count. The number of questions depends on the answers, so "3 of 16" would
    | be a promise the engine cannot keep — and the client has forbidden it.
    | These labels are what the customer sees on the progress indicator.
    */
    'section_labels' => [
        'service' => 'What you need',
        'eligibility' => 'Eligibility',
        'about_you' => 'About you',
        'family' => 'Your family',
        'children' => 'Children and guardianship',
        'assets' => 'Your assets',
        'existing' => 'Existing arrangements',
        'wishes' => 'Your wishes',
        'executor' => 'Executor',
        'circumstances' => 'Circumstances',
        'language' => 'Language',
    ],

    /*
    |---------------------------------------------------------------------------
    | Case reference
    |---------------------------------------------------------------------------
    */
    'reference_prefix' => 'SLC',

    /*
    |---------------------------------------------------------------------------
    | Resume window
    |---------------------------------------------------------------------------
    | Overridden at runtime by retention.incomplete_assessment_days.
    */
    'default_expiry_days' => 30,
];
