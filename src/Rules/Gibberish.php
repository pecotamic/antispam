<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\FieldKind;
use Illuminate\Support\Str;

/**
 * Flags single words too long to be an abbreviation yet too short of vowels to
 * be a word — the keyboard mash bots produce as names.
 *
 * Two decisions keep real submissions out of this:
 *
 * Words are judged one by one, not the value as a whole. "MVZ Dr. Schmidt
 * GmbH" averages barely a vowel per four letters because abbreviations have
 * none, and would look like gibberish measured in one piece. Its individual
 * words are all well below the minimum length, so none is judged at all.
 *
 * Words are transliterated to ASCII before counting, so "Schröder" is measured
 * as "Schroder" and its umlaut counts as the vowel it is. Against a plain
 * [aeiou] class a good share of German names would be rejected.
 */
class Gibberish extends FieldRule
{
    public function handle(): string
    {
        return 'gibberish';
    }

    /**
     * Only prose is worth judging: an address, a URL or a telephone number is
     * not written language and would be measured against the wrong yardstick.
     */
    protected function skips(): array
    {
        return [FieldKind::Email, FieldKind::Url, FieldKind::Numeric, FieldKind::Other];
    }

    protected function inspect(string $field, string $value): ?string
    {
        $minimumLength = (int) $this->option('minimum_length', 12);
        $minimum = (float) $this->option('minimum_vowel_ratio', 0.15);
        $vowels = '/['.preg_quote((string) $this->option('vowels', 'aeiouy'), '/').']/';

        foreach ($this->words($value) as $word) {
            $word = Str::lower(Str::ascii($word));
            $length = strlen($word);

            if ($length < $minimumLength) {
                continue;
            }

            $ratio = preg_match_all($vowels, $word) / $length;

            if ($ratio < $minimum) {
                return sprintf(
                    "field '%s' contains '%s' with a vowel ratio of %.2f, below the %.2f minimum",
                    $field, $word, $ratio, $minimum
                );
            }
        }

        return null;
    }
}
