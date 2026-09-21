<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\FieldKind;
/**
 * Flags characters from writing systems the site does not expect.
 *
 * Cyrillic or CJK text in a German-language form is spam with near certainty,
 * and a single foreign character among latin ones is more suspicious still —
 * that is the shape of a homoglyph attempt.
 *
 * "Common" covers digits, punctuation, whitespace, currency signs and emoji;
 * "Inherited" covers combining marks. Without those two, ordinary text would
 * not pass.
 */
class Script extends FieldRule
{
    public function handle(): string
    {
        return 'script';
    }

    /**
     * Applied to anything a person typed, addresses and URLs included: a
     * cyrillic character in an email address is as telling as one in a
     * message. Only fixed options are skipped, which no one typed.
     */
    protected function skips(): array
    {
        return [FieldKind::Other];
    }

    protected function inspect(string $field, string $value): ?string
    {
        $allowed = (array) $this->option('allowed', ['Latin', 'Common', 'Inherited']);

        $pattern = '/[^'.collect($allowed)
            ->map(fn (string $script) => '\p{'.$script.'}')
            ->implode('').']/u';

        if (!preg_match($pattern, $value, $match)) {
            return null;
        }

        return sprintf(
            "field '%s' contains '%s', outside the allowed scripts (%s)",
            $field, $match[0], implode(', ', $allowed)
        );
    }
}
