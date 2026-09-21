<?php

namespace Pecotamic\Antispam\Rules;

use Illuminate\Support\Facades\Cache;
use Pecotamic\Antispam\Candidate;

/**
 * Flags submissions whose content was seen before within the window.
 *
 * Bots replay the same payload across forms and sites; visitors rarely send
 * the identical text twice. Where they do — an impatient second click — the
 * first submission has already arrived, so nothing is lost by discarding the
 * repeat.
 *
 * Note this rule remembers as a side effect of being evaluated.
 */
class Duplicate extends Rule
{
    public function handle(): string
    {
        return 'duplicate';
    }

    public function detects(Candidate $candidate): ?string
    {
        $text = trim($candidate->text());

        if ($text === '') {
            return null;
        }

        $window = (int) $this->option('window', 86400);
        $key = 'pecotamic-antispam:seen:'.sha1($text);

        // add() returns false when the key is already there, making the check
        // and the remembering one atomic step.
        if (Cache::add($key, true, $window)) {
            return null;
        }

        return sprintf('identical content was already submitted within the last %ds', $window);
    }
}
