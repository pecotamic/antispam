<?php

namespace Pecotamic\Antispam\Rules;

use Illuminate\Support\Facades\Cache;
use Pecotamic\Antispam\Candidate;

/**
 * Limits how many submissions one address may send within a window.
 *
 * The allowance is deliberately generous: mobile networks put many subscribers
 * behind one address, so a tight limit would catch unrelated visitors. It is
 * aimed at the bot that submits dozens of times, not at the visitor who sends
 * two enquiries.
 *
 * Note this rule counts as a side effect of being evaluated, and that it needs
 * the application's trusted proxy configuration to see real client addresses
 * when the site sits behind a proxy or CDN.
 */
class RateLimit extends Rule
{
    public function handle(): string
    {
        return 'rate_limit';
    }

    public function detects(Candidate $candidate): ?string
    {
        if (!$address = $candidate->request->ip()) {
            return null;
        }

        $window = (int) $this->option('window', 3600);
        $maximum = (int) $this->option('maximum', 5);
        $key = 'pecotamic-antispam:rate:'.sha1($address);

        // Seeding before incrementing fixes the window to the first submission,
        // so a steady stream of requests cannot keep pushing the expiry out.
        Cache::add($key, 0, $window);
        $count = Cache::increment($key);

        if ($count <= $maximum) {
            return null;
        }

        return sprintf(
            '%d submissions from this address within %ds, above the %d allowed',
            $count, $window, $maximum
        );
    }
}
