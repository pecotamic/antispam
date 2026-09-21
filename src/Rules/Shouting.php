<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\FieldKind;
/**
 * Flags values written predominantly in capitals.
 *
 * The minimum length is deliberately well above a name's: acronym-heavy
 * practice names such as "MVZ Dr. Schmidt GmbH" run to a high capital ratio
 * without being shouted, and only prose-length values can be judged this way.
 */
class Shouting extends FieldRule
{
    public function handle(): string
    {
        return 'shouting';
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
        $letters = $this->letters($value);
        $length = mb_strlen($letters);

        if ($length < (int) $this->option('minimum_length', 25)) {
            return null;
        }

        $ratio = preg_match_all('/\p{Lu}/u', $letters) / $length;
        $maximum = (float) $this->option('maximum_capital_ratio', 0.5);

        if ($ratio <= $maximum) {
            return null;
        }

        return sprintf(
            "field '%s' is %.0f%% capitals, above the %.0f%% maximum",
            $field, $ratio * 100, $maximum * 100
        );
    }
}
