<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\Candidate;

/**
 * Matches configured regular expressions against the submitted text, for
 * campaigns specific enough to name outright.
 */
class Patterns extends Rule
{
    public function handle(): string
    {
        return 'patterns';
    }

    public function detects(Candidate $candidate): ?string
    {
        $text = $candidate->text();

        foreach ((array) $this->option('expressions', []) as $pattern) {
            // An invalid pattern raises rather than silently never matching, so
            // a typo surfaces instead of quietly disabling protection.
            if (preg_match($pattern, $text) === 1) {
                return "matches pattern {$pattern}";
            }
        }

        return null;
    }
}
