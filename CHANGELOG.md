# Changelog

## 0.2.0

A rewrite of how submissions are judged, and the arrival of a frontend.

**This release needs one change to your templates.** See *Upgrading* below.

### Weighted rules instead of a single verdict

Every rule now contributes a configurable weight to a score, and a submission
is discarded once the score reaches the threshold. A rule can therefore be a
mere indication that only rejects in combination with another, rather than an
all-or-nothing switch.

### New rules

`pixel`, `interaction`, `rate_limit`, `duplicate`, `links`, `script`,
`gibberish`, `shouting` and `email` join the existing `timing` and `patterns`.

`gibberish` and `shouting` are the server-side successors to a frontend text
heuristic. Both were rewritten rather than translated: they judge words
individually, so an abbreviation-heavy practice name is not mistaken for a
keyboard mash, and they transliterate before counting vowels, so German names
with umlauts are not rejected.

### Which fields a rule examines follows from the blueprint

A field declared as a telephone number, an email address or a URL is skipped by
the rules that judge written language. No list of field handles to maintain.

### A frontend, served by the addon

The form JavaScript now ships and is served by the package itself, from a
fingerprinted route. No Vite alias, no npm dependency, no build step. Client
and server are released as one version, so the two cannot drift apart.

The tag renders the configuration the frontend runs on, which means the proof
field name exists once — in the config the server reads it from — rather than
once per side.

### Logging

`log` accepts `rejected` (default), `scored` or `none`. `scored` also records
accepted submissions that a rule objected to; those near misses are what a
threshold can be tuned against.

### Upgrading from 0.1

**1. Add the tag to your form templates**

``` antlers
{{ antispam }}
```

It renders the tracking pixel, the frontend module and its configuration. Until
it is there, the `pixel` and `interaction` rules stay quiet — nothing is
wrongly rejected, but that part of the protection is not running. The addon
says so after `composer install` or `composer update`.

**2. Move your published config**

The per-rule settings moved under a `rules` key. Old keys are no longer read,
and `patterns` in particular would stop matching without a word:

| before | now |
|---|---|
| `patterns` | `rules.patterns.expressions` |
| `minimum_fill_time` | `rules.timing.minimum_fill_time` |
| `maximum_fill_time` | `rules.timing.maximum_fill_time` |

The addon reports outdated keys after an install or update. Configs are now
merged recursively, so you only need to name what you want to change — the rest
is inherited.

**3. Remove any client-side honeypot stripping**

If your frontend deletes the honeypot field before submitting, the server has
nothing left to detect and the honeypot protection is silently disabled. The
bundled frontend leaves it in the payload, and there is a test for it.

**4. Check the new rules against real traffic first**

The new content rules arrive at full weight. Before relying on them, set
`log` to `scored` for a while: it records what would have been caught,
with reasons, so the weights can be set from evidence rather than guessed.

### Static caching

The pixel request reaches PHP even when the page was served from a full static
cache, and seeds the timing cookie there. With the tag in place, full measure
static caching no longer breaks the timing rule.

## 0.1.2

Document the static caching caveat, add an interactive live smoke test.

## 0.1.1

Fail on invalid spam patterns instead of ignoring them.

## 0.1.0

Silent server-side timing and content based spam protection for Statamic forms.
