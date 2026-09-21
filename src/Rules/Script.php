<?php

namespace Pecotamic\Antispam\Rules;

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
