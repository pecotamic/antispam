<?php

namespace Pecotamic\Antispam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Pecotamic\Antispam\SignedTimestamp;

class ProofController
{
    /**
     * Hands out an interaction proof.
     *
     * The frontend requests one the moment a visitor first moves, taps, types
     * or focuses inside a form, and submits it along with the form. A client
     * that never interacts never has one to send.
     *
     * The endpoint is deliberately unguarded — a bot can fetch a proof as
     * easily as a browser. What it cannot do is fetch one and submit
     * immediately, because the proof carries the moment it was issued and the
     * rule requires a plausible interval. The value is in demanding the extra
     * round trip at all: the vast majority of form spam is a blind POST.
     *
     * Nor is a proof single-use, which is a decision rather than an oversight.
     * Since anyone may fetch one, expiring it on use would force a bot to
     * fetch one proof per submission instead of one per campaign — while
     * costing a visitor whose first attempt failed validation their second.
     * Volume is what the rate limit and duplicate rules are for.
     */
    public function __invoke(SignedTimestamp $timestamp): JsonResponse
    {
        return response()->json(['proof' => $timestamp->issue()])
            ->header('Cache-Control', 'no-store, private');
    }
}
