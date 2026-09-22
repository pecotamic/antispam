<?php

namespace Pecotamic\Antispam;

/**
 * What the current page has said about its protection.
 *
 * The {{ antispam }} tag renders while the page is built; the middleware acts
 * on the finished response. This carries what the tag said across that gap.
 */
class PageState
{
    private bool $placed = false;

    /** @var array<string, mixed> */
    private array $options = [];

    /**
     * True the first time it is asked, false every time after — so a page with
     * several forms places the markup once.
     */
    public function claimMarkup(): bool
    {
        return $this->placed ? false : $this->placed = true;
    }

    public function markupPlaced(): bool
    {
        return $this->placed;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function configure(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }
}
