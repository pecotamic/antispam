# Changelog

## 0.3.0

The browser-side protection is now placed by the addon, not by a tag the site
has to add. Installing is `composer require` and nothing else.

### Why

The pixel is an `<img>`, which is valid in the body alone. Placed in the
`<head>` it ends the head as far as the HTML parser is concerned, and
everything after it — analytics, meta and link tags — is reparented into the
body. A tag placed by hand invites exactly that, particularly on sites that
collect their scripts in a stack rendered inside `<head>`, which is where the
tag would most naturally be put. Injecting before `</body>` is correct whatever
the templates look like, and it removes the integration step altogether.

### Changes

- The markup is injected into every page carrying a Statamic form, before
  `</body>`. Fragments, non-HTML responses and error responses are left alone.
- `{{ antispam }}` is no longer required. It now contributes per-page options
  (`selector`, `error_selector`, `consent_field`, `event_name`) and renders
  nothing, so its position no longer matters.
- New `inject` config option. Set it to `false` to place the markup yourself
  with `{{ antispam }}` — inside the body.
- The middleware `IssueFormTimingCookie` is now `ProtectForms`; it issues the
  cookie and places the markup. `TagPresence` is now `ProtectionReach`, since
  what matters is that the markup reaches visitors, not that a tag exists.
- Full measure static caching now works without intervention: the markup is
  part of what gets cached, and the pixel in a cached page still reaches PHP.

### Upgrading from 0.2.0

Remove `{{ antispam }}` from your templates — or leave it, it is harmless and
still useful for passing options. Nothing else to do.

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
