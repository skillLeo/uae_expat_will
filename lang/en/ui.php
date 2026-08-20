<?php

/*
|--------------------------------------------------------------------------
| Interface strings
|--------------------------------------------------------------------------
| The chrome: buttons, labels and states that appear across many screens.
|
| PAGE CONTENT DOES NOT LIVE HERE. Every word a customer reads on a public
| page, every FAQ answer and every notification body is a database row with a
| `locale` column, so Summit edits it themselves and a second language is a
| second row rather than a second deployment. This file is only for strings
| that belong to the interface rather than to the content.
|
| To add Arabic: copy this directory to lang/ar, translate the values, add
| 'ar' to config('app.supported_locales'), and seed the content rows with
| locale 'ar'. No component changes.
*/

return [
    'actions' => [
        'continue' => 'Continue',
        'back' => 'Back',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'submit' => 'Submit',
        'close' => 'Close',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'download' => 'Download',
        'open' => 'Open',
        'search' => 'Search',
        'sign_in' => 'Sign in',
        'sign_out' => 'Sign out',
        'start_assessment' => 'Start the assessment',
        'contact_team' => 'Contact our team',
    ],

    'states' => [
        'loading' => 'Loading…',
        'saving' => 'Saving…',
        'sending' => 'Sending…',
        'saved_automatically' => 'saved automatically',
        'nothing_to_show' => 'Nothing to show.',
        'no_results' => 'No matching question',
        'refreshing' => 'Refreshing…',
        'pull_to_refresh' => 'Pull to refresh',
    ],

    'assessment' => [
        'free' => 'Free',
        'no_account' => 'No account needed',
        'five_minutes' => 'About five minutes',
        'answer_required' => 'Please answer this question to continue.',
        'exclusive_cleared' => 'Selecting this clears the other options. Choose any other answer to undo it.',
        'declarations_remaining' => ':accepted of :total confirmed',
    ],

    'legal' => [
        // Rendered on every page and in every email. Compliance requires this
        // exact wording, so it is here rather than hardcoded in a component.
        'not_an_authority' => 'UAE Expat Wills and Summit Legal Consultancy UAE are not a court, registry, notary or government authority.',
        'preliminary_result' => 'This result is preliminary. It is not a legal opinion, not final acceptance by any authority, and it does not mean that a Will has been prepared or registered.',
        'no_payment_while_held' => 'No payment is requested while a matter is held for review.',
        'approval_not_registration' => 'Approving the wording does not register the Will. Registration is completed by the competent authority under its own current requirements.',
    ],

    'restricted' => [
        'label' => 'Restricted — authorised legal staff only',
        'body' => 'This matter carries a restricted flag. Its content is visible only to authorised legal staff. This is not a rejection and the customer has not been told a reason.',
    ],
];
