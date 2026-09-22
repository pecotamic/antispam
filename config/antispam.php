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
    | Automatic markup
    |--------------------------------------------------------------------------
    |
    | The addon places its tracking pixel and frontend module on every page
    | that carries a Statamic form, just before </body>. That needs no change
    | to your templates, and it is the only placement that is always correct:
    | the pixel is an <img>, which in the <head> would end the head for the
    | HTML parser and push everything after it into the body.
    |
    | Turn this off only if you need the markup somewhere else — then place the
    | {{ antispam }} tag yourself, inside the body.
    |
    */

    'inject' => true,

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
    | individual rule.
    |
    | Which fields a content rule examines follows from the form blueprint, so
    | it needs no configuring: a field declared as a telephone number, an email
    | address or a URL is skipped by the rules that judge written language,
    | because it is not written language. A field the blueprint does not
    | describe is examined — the conservative way round.
    |
    | "except" remains for the rest: field handles a rule should skip whatever
    | the blueprint says about them.
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
         | Evidence that the form page was really loaded: the addon places a
         | 1x1 image whose request leaves a cookie behind. Most
         | form spam is a blind POST that never fetches anything from the page,
         | so its absence says a great deal — and unlike the interaction proof
         | this covers every visitor, JavaScript or not.
         |
         | That request also reaches PHP when the page itself came from a full
         | static cache, which is the one case the timing middleware never sees.
         |
         | The weight sits below the threshold because ad blockers occasionally
         | swallow pixel-shaped requests, and no visitor should be turned away
         | over a single missing image. Together with a missing interaction
         | proof it is enough; either signal alone lets a visitor through.
         */
        'pixel' => [
            'weight' => 60,
            'cookie' => '_ptap',
            'maximum_age' => 7200,
        ],

        /*
         | Evidence that a human interacted with the form: the frontend fetches
         | a proof on the first mouse move, touch, key press or focus, and sends
         | it along in the field named here. Unlike the timing cookie — which
         | any bare GET collects — a proof costs an extra round trip that a
         | blind POST never makes.
         |
         | The weight sits below the threshold on purpose: forms still submit
         | without JavaScript, and a visitor who has it disabled should not be
         | turned away on that alone. Combined with any second indicator it is
         | enough. Raise it to the threshold on sites whose forms require
         | JavaScript anyway.
         */
        'interaction' => [
            'weight' => 60,
            'field' => 'ptas_proof',
            'minimum_fill_time' => 3,
            'maximum_fill_time' => 7200,
        ],

        /*
         | How many submissions one address may send within "window" seconds.
         | The allowance is generous on purpose: mobile networks put many
         | subscribers behind a single address. Needs the application's trusted
         | proxy configuration to see real client addresses behind a proxy.
         */
        'rate_limit' => [
            'weight' => 100,
            'window' => 3600,
            'maximum' => 5,
        ],

        /*
         | Content that was already submitted within "window" seconds. Bots
         | replay the same payload; visitors rarely send identical text twice,
         | and where they do, the first submission already arrived.
         */
        'duplicate' => [
            'weight' => 100,
            'window' => 86400,
        ],

        /*
         | Links in the submitted values. On a form that exists to arrange
         | appointments, a link is close to a sure sign of spam. Forms that do
         | ask for a website should list that field under "except" rather than
         | raise "maximum", so the rule keeps its bite on the other fields.
         */
        'links' => [
            'weight' => 100,
            'maximum' => 0,
            'except' => [],
        ],

        /*
         | Characters from writing systems the site does not expect. "Common"
         | covers digits, punctuation, whitespace and emoji, "Inherited" the
         | combining marks — without those two, ordinary text would not pass.
         | Add e.g. 'Greek' or 'Cyrillic' where such names are to be expected.
         */
        'script' => [
            'weight' => 100,
            'allowed' => ['Latin', 'Common', 'Inherited'],
            'except' => [],
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

        /*
         | Plausibility of submitted email addresses, recognised by their shape
         | rather than by field name. "disposable_domains" adds to the list of
         | throwaway providers bundled with the addon.
         |
         | "check_mx" is off by default: it puts a DNS round trip in the
         | request path, and a resolver outage would make every address look
         | invalid and take the form down with it. Enable it only where DNS is
         | reliable, preferably at a weight below the threshold so a failed
         | lookup alone cannot reject a submission.
         */
        'email' => [
            'weight' => 100,
            'check_mx' => false,
            'disposable_domains' => [],
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
    | Protection reach
    |--------------------------------------------------------------------------
    |
    | The pixel and interaction rules only judge once the protection markup has
    | been seen doing its work, so that a site whose pages still come from a
    | cache built before it existed is not rejected wholesale. This is how long
    | a confirmed reach is remembered, in seconds.
    |
    */

    'reach_remembered_for' => 604800,

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
