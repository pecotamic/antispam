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

Protection is active for every form out of the box. Everything below is tuning.

## Rules

| Rule | Objects when |
|---|---|
| `timing` | The submission arrives faster than `minimum_fill_time`, later than `maximum_fill_time`, or without the timing cookie. |
| `patterns` | A configured regular expression matches the submitted text. |
| `gibberish` | A single word is long enough to be prose yet has too few vowels to be a word. |
| `shouting` | A value of prose length is written predominantly in capitals. |

The rule set ships with the addon and is not assembled per site. Sites adjust
weights, thresholds and the individual options instead — there is no place
where a project needs rule code of its own.

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

## Static caching

The timing cookie is issued by middleware while the page is rendered. With
Statamic's **full measure** static caching, pages are served straight from disk
without booting PHP, so the middleware never runs and the cookie is never set —
as a result **every** submission is rejected as if it had no cookie.

If you use full static caching, exclude the pages containing protected forms
from the cache (`statamic.static_caching.exclude`) so the cookie can be set, or
set the `timing` rule's weight to `0`. The **half measure** strategy still
executes PHP on each request and works without changes.

## Live smoke test

`bin/live-test.php` submits a genuine, a too-fast and a cookie-less submission
against a running site and reports how each was handled.
