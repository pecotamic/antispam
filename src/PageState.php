<?php

namespace Pecotamic\Antispam;

/**
 * Tracks what the tag has already put on the current page.
 *
 * The Antlers context cannot carry this: each tag occurrence gets its own, so
 * a page with a footer form as well as a page form would render the pixel and
 * the module once per form. Browsers would deduplicate an identical module URL
 * on their own, but emitting the markup three times is still wrong.
 */
class PageState
{
    private bool $rendered = false;

    /**
     * True the first time it is asked, false every time after.
     */
    public function claimRendering(): bool
    {
        return $this->rendered ? false : $this->rendered = true;
    }
}
