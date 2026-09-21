<?php

namespace Pecotamic\Antispam\Rules;

/**
 * Counts links in the submitted values.
 *
 * On a contact form that exists to arrange appointments, a link is close to a
 * sure sign of spam — few enquiries have reason to carry one. Forms that do
 * ask for a website should list that field under "except" rather than raise
 * the allowance, so the rule keeps its bite everywhere else.
 *
 * Only unambiguous markers count. A bare "domain.tld" is deliberately not
 * treated as a link: it would match every email address that is passed through
 * as a value.
 */
class Links extends FieldRule
{
    private const MARKERS = '~(?:https?://|www\.|\[url[\]=]|<a\s)~i';

    public function handle(): string
    {
        return 'links';
    }

    protected function inspect(string $field, string $value): ?string
    {
        $count = preg_match_all(self::MARKERS, $value);
        $maximum = (int) $this->option('maximum', 0);

        if ($count <= $maximum) {
            return null;
        }

        return sprintf(
            "field '%s' contains %d link(s), above the %d allowed",
            $field, $count, $maximum
        );
    }
}
