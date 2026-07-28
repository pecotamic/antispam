<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Protected forms
    |--------------------------------------------------------------------------
    |
    | List of Statamic form handles that should be protected. Use the wildcard
    | '*' to protect every form. To protect only specific forms, replace it
    | with their handles, e.g. ['contact', 'newsletter'].
    |
    */

    'forms' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Timing thresholds (seconds)
    |--------------------------------------------------------------------------
    |
    | A submission is only accepted when the time between rendering the form
    | and submitting it lies within this window. Anything faster than
    | "minimum_fill_time" is treated as a bot; anything older than
    | "maximum_fill_time" is considered an expired (stale) token.
    |
    | "maximum_fill_time" also controls the lifetime of the timing cookie.
    | Keep minimum_fill_time < maximum_fill_time.
    |
    */

    'minimum_fill_time' => 5,
    'maximum_fill_time' => 7200,

    /*
    |--------------------------------------------------------------------------
    | Timing cookie
    |--------------------------------------------------------------------------
    |
    | The encrypted timing cookie is only set on pages that contain a Statamic
    | form action. "same_site" accepts 'Lax', 'Strict' or 'None' (per RFC 6265;
    | 'None' requires a secure connection).
    |
    */

    'cookie' => [
        'name' => '_ptas',
        'same_site' => 'Lax',
    ],

    /*
    |--------------------------------------------------------------------------
    | Content patterns
    |--------------------------------------------------------------------------
    |
    | Optional PCRE regular expressions matched against the submitted scalar
    | field values (joined by newlines). A submission is rejected as soon as
    | one pattern matches. Each entry must be a complete, valid pattern
    | including delimiters and flags. An invalid pattern raises an error
    | rather than failing silently, so misconfiguration is caught early.
    |
    | Example:
    |   'patterns' => [
    |       '/heard about (?:this|us) on .*\bradio\b/i',
    |   ],
    |
    */

    'patterns' => [],

];
