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
    | Rejection threshold
    |--------------------------------------------------------------------------
    |
    | Every rule that objects to a submission adds its weight to a score. Once
    | the score reaches this threshold, the submission is silently discarded.
    |
    | With the default weights each rule rejects on its own. Lower a rule's
    | weight below the threshold to make it a mere indication that only rejects
    | in combination with another rule — for example, weights of 60 on two
    | rules means neither rejects alone but both together do.
    |
    */

    'threshold' => 100,

    /*
    |--------------------------------------------------------------------------
    | Rules
    |--------------------------------------------------------------------------
    |
    | Each rule contributes its "weight" to the score when it objects. A weight
    | of 0 switches the rule off entirely. The remaining keys configure the
    | individual rule; "except" lists field handles a rule should skip.
    |
    */

    'rules' => [

        /*
         | Time between rendering the form and submitting it, measured with an
         | encrypted cookie. Anything faster than "minimum_fill_time" is a bot;
         | anything older than "maximum_fill_time" is a stale token.
         | "maximum_fill_time" also controls the lifetime of the cookie.
         */
        'timing' => [
            'weight' => 100,
            'minimum_fill_time' => 5,
            'maximum_fill_time' => 7200,
        ],

        /*
         | PCRE regular expressions matched against all submitted values joined
         | by newlines, for campaigns specific enough to name outright. Each
         | entry must be a complete, valid pattern including delimiters and
         | flags. An invalid pattern raises an error rather than failing
         | silently, so misconfiguration is caught early.
         |
         | Example:
         |   'expressions' => [
         |       '/heard about (?:this|us) on .*\bradio\b/i',
         |   ],
         */
        'patterns' => [
            'weight' => 100,
            'expressions' => [],
        ],

        /*
         | Values with too few vowels to be a word — keyboard-mash names and
         | the like. Values are transliterated to ASCII first, so umlauts count
         | as the vowels they are. Only values with at least "minimum_length"
         | letters are judged; punctuation, digits and spacing are ignored,
         | which keeps phone numbers and postcodes out of the calculation.
         */
        'gibberish' => [
            'weight' => 100,
            'minimum_length' => 12,
            'minimum_vowel_ratio' => 0.15,
            'vowels' => 'aeiouy',
            'except' => [],
        ],

        /*
         | Values written predominantly in capitals. The minimum length sits
         | well above a name's, because acronym-heavy practice names reach a
         | high capital ratio without being shouted.
         */
        'shouting' => [
            'weight' => 100,
            'minimum_length' => 25,
            'maximum_capital_ratio' => 0.5,
            'except' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | 'rejected' logs every discarded submission with the reasons that led to
    | it. 'scored' additionally logs accepted submissions that a rule objected
    | to — those near misses are the material to tune weights and the threshold
    | against before tightening them. 'none' disables logging.
    |
    | Leave "log_channel" empty to use the application's default channel.
    |
    */

    'log' => 'rejected',
    'log_channel' => null,

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

];
