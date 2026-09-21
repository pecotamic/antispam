<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\Candidate;

/**
 * Base for rules that judge each submitted value on its own rather than the
 * submission as a whole.
 */
abstract class FieldRule extends Rule
{
    /**
     * A reason when this value is objectionable, null when it is not.
     */
    abstract protected function inspect(string $field, string $value): ?string;

    public function detects(Candidate $candidate): ?string
    {
        $except = (array) $this->option('except', []);

        foreach ($candidate->values() as $field => $value) {
            if (in_array($field, $except, true)) {
                continue;
            }

            if ($reason = $this->inspect((string) $field, (string) $value)) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * The value reduced to its letters.
     *
     * Judging letters rather than raw characters keeps punctuation and spacing
     * out of the ratios, and lets values that carry no prose at all — phone
     * numbers, postcodes, dates — fall below the minimum length and be skipped
     * without needing a rule of their own.
     */
    protected function letters(string $value): string
    {
        return preg_replace('/\P{L}+/u', '', $value) ?? '';
    }

    /**
     * The value split into runs of letters.
     *
     * @return array<int, string>
     */
    protected function words(string $value): array
    {
        return preg_split('/\P{L}+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
