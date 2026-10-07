<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Honeypot Protection
    |--------------------------------------------------------------------------
    |
    | Blocks automated form spam using a hidden field bots fill in, plus a
    | minimum time that must pass between rendering and submitting the form.
    |
    */

    'enabled' => env('HONEYPOT_ENABLED', true),

    /*
    | Field name that should stay empty. Named like a real field so bots fill it.
    */
    'name_field' => 'website',

    /*
    | Encrypted timestamp field used to detect instant bot submissions.
    */
    'valid_from_field' => '_form_started_at',

    /*
    | Minimum seconds that must elapse after the form is rendered.
    */
    'min_seconds' => (int) env('HONEYPOT_MIN_SECONDS', 2),

];
