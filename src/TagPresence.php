<?php

namespace Pecotamic\Antispam;

use Illuminate\Support\Facades\Cache;

/**
 * Whether the {{ antispam }} tag is actually in use on this site.
 *
 * The proof rules ask for evidence the tag is responsible for producing. Where
 * the tag has not been added to the templates, that evidence can never arrive,
 * and demanding it would reject every genuine submission — silently, since the
 * visitor is shown a success message either way. So the rules stay quiet until
 * the tag has been seen.
 *
 * Seen means rendered or fetched from, not merely present in a file: what
 * matters is that it reaches visitors, which a template scan could not tell us
 * (a partial may exist and never be included).
 *
 * Two things record it. The tag does so as it renders, which covers the
 * ordinary case. The pixel endpoint does so too, which covers full static
 * caching — there the page is served without PHP and the tag never renders,
 * but the pixel it put in the page is still fetched.
 */
class TagPresence
{
    private const KEY = 'pecotamic-antispam:tag-seen';

    public function record(): void
    {
        Cache::put(self::KEY, true, $this->lifetime());
    }

    public function confirmed(): bool
    {
        return (bool) Cache::get(self::KEY, false);
    }

    /**
     * Generous, because the consequence of it lapsing is a quiet loss of
     * protection, while re-recording costs one page view.
     */
    private function lifetime(): int
    {
        return (int) config('pecotamic.antispam.tag_remembered_for', 604800);
    }
}
