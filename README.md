# Pecotamic Antispam

![Statamic 5](https://img.shields.io/badge/Statamic-5.0+-26BBDD?style=for-the-badge&link=https://statamic.com)
![Statamic 6](https://img.shields.io/badge/Statamic-6.0+-FF269E?style=for-the-badge&link=https://statamic.com)

Silent server-side spam protection for Statamic forms.

Every submission is examined by a set of rules. Each rule that objects adds its
configured weight to a score; once the score reaches the threshold, the
submission is silently discarded through Statamic's `FormSubmitted` event. Bots
receive the regular success response.

## Installation

``` bash
composer require pecotamic/antispam
php artisan vendor:publish --tag=pecotamic-antispam-config
```

Then add the tag once inside each form template, next to the form itself:

``` antlers
{{ antispam }}
```

That is the whole integration. The tag renders the tracking pixel, the frontend
module and the configuration the frontend runs on — no Vite alias, no npm
dependency, no build step, nothing to publish into the site's public
directory.

Protection is active for every form out of the box. Everything below is tuning.

## Rules

| Rule | Objects when |
|---|---|
| `pixel` | The form page was never loaded with its subresources. |
| `interaction` | No valid proof of human interaction accompanies the submission. |
| `timing` | The submission arrives faster than `minimum_fill_time`, later than `maximum_fill_time`, or without the timing cookie. |
| `rate_limit` | One address sends more than `maximum` submissions within `window` seconds. |
| `duplicate` | The same content was already submitted within `window` seconds. |
| `patterns` | A configured regular expression matches the submitted text. |
| `links` | A value contains more links than `maximum` allows. |
| `script` | A value contains characters outside the allowed writing systems. |
| `gibberish` | A single word is long enough to be prose yet has too few vowels to be a word. |
| `shouting` | A value of prose length is written predominantly in capitals. |
| `email` | An address uses a throwaway mail domain, or has no MX record when `check_mx` is on. |

The rule set ships with the addon and is not assembled per site. Sites adjust
weights, thresholds and the individual options instead — there is no place
where a project needs rule code of its own.

## Frontend

The package ships the form JavaScript alongside the PHP and serves it itself,
from a fingerprinted route:

```
/!/pecotamic-antispam/js/<fingerprint>/contact-form.js
```

Two things follow from that. Client and server are released as one version, so
a signal the client sends and a signal the server checks cannot drift apart.
And a fix to the frontend reaches a site through `composer update` alone —
without a Vite build that has to succeed on every site first.

The files are plain JavaScript with JSDoc types, checked by `tsc --checkJs`.
There is no build, so the file served is the file in the repository, and no
compiled artefact can go stale against its source.

Sites that need to deviate pass parameters at the tag rather than forking
anything:

``` antlers
{{ antispam selector="form.enquiry" event_name="sent" }}
```

`selector`, `error_selector`, `consent_field` and `event_name` are accepted.
Everything else — above all the proof field name and endpoint — comes from the
PHP config, so it exists once rather than once per side.

### What the frontend does and does not do

It validates for immediate feedback — required fields, address format, consent
— and fetches the interaction proof. It runs no spam heuristic of its own: a
heuristic maintained in two languages drifts apart, and a false positive in the
browser blocks a real visitor behind an error message, while the server can
weigh the same signal among others.

It leaves the honeypot field in the payload. Stripping it client-side leaves
the server nothing to detect, and a stripped honeypot is indistinguishable from
an empty one — the failure is silent and total. There is a test for exactly
this.

### Without the tag

The proof rules ask for evidence only the tag produces, so on a site that has
not added it they stay quiet rather than reject everything. They switch
themselves on once the tag has been seen — rendered, or its pixel fetched.

This is not a convenience. Weighted at 60 each, a missing pixel and a missing
interaction proof clear the threshold together, so without this a `composer
update` alone would swallow every enquiry on a site whose templates had not
been touched yet — silently, because the visitor is shown a success message
either way. The addon also says so after an install or update.

### The two proofs

`pixel` and `interaction` cover each other, which is why both are weighted
below the threshold. A visitor without JavaScript has the pixel; a visitor
whose ad blocker swallowed the pixel has the interaction proof. Either alone
gets them through. A blind POST has neither and exceeds the threshold on that
count alone.

## Scoring

With the default weight of 100 against a threshold of 100, each rule rejects on
its own. Lowering a weight turns a rule into a mere indication that only
rejects in combination:

``` php
'threshold' => 100,

'rules' => [
    'gibberish' => ['weight' => 60, /* … */],
    'shouting'  => ['weight' => 60, /* … */],
],
```

Neither rule now rejects alone, but a submission that trips both does.

Set a rule's `weight` to `0` to switch it off entirely, and list field handles
under `except` to skip individual fields.

## Tuning against real traffic

Before tightening weights, let the addon report what it would have caught:

``` php
'log' => 'scored',
```

This logs accepted submissions that a rule objected to, alongside the rejected
ones, each with the score and the reasons behind it. Those near misses are the
material to set a threshold against. `'rejected'` (the default) logs only
discarded submissions, `'none'` disables logging.

## Which fields a rule examines

This follows from the form blueprint rather than from configuration. A field
declared as a telephone number, an email address or a URL is skipped by the
rules that judge written language, because it is not written language:

| Blueprint says | skipped by |
|---|---|
| `input_type: tel`, `number`, `date` | `gibberish`, `shouting`, `links` |
| `input_type: email` | `gibberish`, `shouting` |
| `input_type: url` | `links` |
| `select`, `checkboxes`, `radio`, `assets`, … | every content rule |

So a website field may hold a website, an address written in capitals is not
mistaken for shouting, and a note typed into a telephone field is left alone —
without a list of field handles that has to be kept in step with the forms
across every site.

Two rules deliberately ignore this. `script` is applied to everything a person
typed, addresses and URLs included: a cyrillic character in an email address is
as telling as one in a message. And `email` finds addresses by their shape, so
it catches one pasted into a message body as readily as one in the address
field.

A field the blueprint does not describe is examined. Waving a field through
because it is unknown would be the wrong way round.

`except` remains for anything left over: field handles a rule should skip
whatever the blueprint says about them.

## False positives

The content rules are deliberately conservative, because a wrongly discarded
submission disappears without anyone noticing — the visitor sees a success
message either way.

`gibberish` judges words individually rather than whole values. Measured in one
piece, a practice name such as "MVZ Dr. Schmidt GmbH" has barely a vowel per
four letters and would look like a keyboard mash; its individual words are all
short enough to be left alone. Words are also transliterated to ASCII before
their vowels are counted, so "Schröder" is measured as "Schroder" — against a
plain `[aeiou]` class a good share of German names would be rejected.

`shouting` ignores anything below prose length for the same reason: short
acronym-heavy values reach a high capital ratio without being shouted.

`links` does not treat a bare "domain.tld" as a link, because that would match
every email address passed through as a value. Forms that legitimately ask for
a website belong under that rule's `except` rather than a raised `maximum`.

`rate_limit` allows generously, because mobile networks put many subscribers
behind one address. It needs the application's trusted proxy configuration to
see real client addresses when the site sits behind a proxy or CDN.

`interaction` is weighted below the threshold by default. Forms still submit
without JavaScript, and a visitor who has it disabled should not be turned away
on that alone — but a missing proof together with any second indicator is
enough. Raise it to the threshold on sites whose forms require JavaScript
anyway.

`email` keeps its MX lookup off by default: it puts a DNS round trip in the
request path, and a resolver outage would make every address look invalid and
take the form down with it.

## Static caching

With Statamic's **full measure** static caching, pages are served straight from
disk without booting PHP, so the middleware that issues the timing cookie never
runs. The pixel request closes that gap: it reaches PHP whatever the page did,
and seeds the timing cookie when there is none — so `{{ antispam }}` is what
makes full static caching safe here.

Without the tag on a full-measure site, every submission is rejected as if it
had no cookie. Either add the tag, exclude the form pages from the cache
(`statamic.static_caching.exclude`), or set the `timing` rule's weight to `0`.
The **half measure** strategy executes PHP on each request and works either
way.

## Tests

``` bash
composer install && vendor/bin/phpunit
npm install && npm test
```

## Live smoke test

`bin/live-test.php` submits a genuine, a too-fast and a cookie-less submission
against a running site and reports how each was handled.
