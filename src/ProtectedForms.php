<?php

namespace Pecotamic\Antispam;

/**
 * Which forms the addon is responsible for.
 *
 * Shared by the rules and by the middleware that places the markup, so a form
 * left out of the configuration is left alone in both senses: its submissions
 * are not judged, and its pages are not modified.
 */
class ProtectedForms
{
    /**
     * Matches the handle in a real form action, e.g.
     * <form method="post" action="https://site.test/!/forms/contact">.
     *
     * Anchored on the form tag rather than the path alone: an article that
     * merely writes about /!/forms/contact is not a form page, and its markup
     * should be left as its author wrote it.
     */
    private const FORM_ACTION = '#<form\b[^>]*\baction\s*=\s*["\']?[^"\'>\s]*/!/forms/([A-Za-z0-9_-]+)#i';

    public function includes(string $handle): bool
    {
        $forms = (array) config('pecotamic.antispam.forms', ['*']);

        return in_array('*', $forms, true) || in_array($handle, $forms, true);
    }

    /**
     * Whether the given HTML carries a form this addon is responsible for.
     */
    public function appearIn(string $html): bool
    {
        return $this->handlesIn($html) !== [];
    }

    /**
     * The protected form handles the given HTML carries.
     *
     * @return array<int, string>
     */
    public function handlesIn(string $html): array
    {
        if (!preg_match_all(self::FORM_ACTION, $html, $matches)) {
            return [];
        }

        return array_values(array_filter(
            array_unique($matches[1]),
            fn (string $handle) => $this->includes($handle)
        ));
    }

    /**
     * A CSS selector matching the protected forms, for the frontend.
     *
     * Derived from the action every Statamic form carries rather than from a
     * class name, which would be an assumption about templates the addon has
     * never seen.
     */
    public function selector(): string
    {
        $forms = (array) config('pecotamic.antispam.forms', ['*']);

        if (in_array('*', $forms, true)) {
            return 'form[action*="/!/forms/"]';
        }

        return collect($forms)
            // Matching the end of the action, not any part of it: "contact"
            // as a substring would also catch "contact_compact".
            ->map(fn ($handle) => sprintf('form[action$="/!/forms/%s"]', $handle))
            ->implode(', ');
    }
}
