<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\Candidate;
use Pecotamic\Antispam\TimingCookie;

/**
 * Rejects submissions that arrive implausibly fast, long after the page was
 * rendered, or without the timing cookie at all.
 */
class Timing extends Rule
{
    public function __construct(private TimingCookie $cookie)
    {
    }

    public function handle(): string
    {
        return 'timing';
    }

    public function detects(Candidate $candidate): ?string
    {
        $age = $this->cookie->age($candidate->request->cookie($this->cookie->name()));

        if ($age === null) {
            return 'no valid timing cookie';
        }

        $minimum = (int) $this->option('minimum_fill_time', 5);
        $maximum = (int) $this->option('maximum_fill_time', 7200);

        if ($age < $minimum) {
            return "submitted after {$age}s, faster than the {$minimum}s minimum";
        }

        if ($age > $maximum) {
            return "submitted after {$age}s, beyond the {$maximum}s maximum";
        }

        return null;
    }
}
