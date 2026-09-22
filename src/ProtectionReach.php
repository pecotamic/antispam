<?php

namespace Pecotamic\Antispam;

use Illuminate\Support\Facades\Cache;

/**
 * Whether the protection markup is actually reaching visitors.
 *
 * The pixel and interaction rules ask for evidence only that markup produces.
 * Where it is not reaching pages, the evidence can never arrive, and demanding
 * it would reject every genuine submission — silently, since the visitor is
 * shown a success message either way. So those rules stay quiet until the
 * markup has been seen doing its work.
 *
 * Normally that is immediate: the middleware injects the markup into the first
 * form page served, and its pixel is fetched moments later. It matters in two
 * cases — a site upgrading while pages sit in a full static cache built before
 * the markup existed, and a site that has turned injection off without placing
 * the tag.
 *
 * Only a real fetch counts. That the markup was rendered says nothing about a
 * page served from a cache built earlier.
 */
class ProtectionReach
{
    private const KEY = 'pecotamic-antispam:reach';

    public function record(): void
    {
        Cache::put(self::KEY, true, $this->lifetime());
    }

    public function confirmed(): bool
    {
        return (bool) Cache::get(self::KEY, false);
    }

    /**
     * Generous, because letting it lapse quietly costs protection, while
     * re-recording costs one page view.
     */
    private function lifetime(): int
    {
        return (int) config('pecotamic.antispam.reach_remembered_for', 604800);
    }
}
