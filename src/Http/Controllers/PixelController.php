<?php

namespace Pecotamic\Antispam\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pecotamic\Antispam\PixelCookie;
use Pecotamic\Antispam\ProtectionReach;
use Pecotamic\Antispam\TimingCookie;

class PixelController
{
    /**
     * A 1×1 image the form page references, whose request leaves a cookie
     * behind.
     *
     * The point is not the picture but the second request. Most form spam is a
     * blind POST to the form action, made without ever loading the page or
     * anything on it. Demanding a subresource fetch costs a real browser
     * nothing and a blind POST everything — and unlike the JavaScript proof it
     * covers every visitor, JavaScript or not.
     */
    public function __invoke(
        Request $request,
        PixelCookie $pixel,
        TimingCookie $timing,
        ProtectionReach $presence,
    ): Response {
        // Proof that the markup is reaching visitors. Only a real fetch tells
        // us that; rendering the markup says nothing about pages served from a
        // cache built earlier.
        $presence->record();

        // 1×1 transparent GIF.
        $response = response(
            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
            200,
            [
                'Content-Type' => 'image/gif',
                'Cache-Control' => 'no-store, private',
            ]
        );

        $response->headers->setCookie($pixel->issue($request));

        // This request reaches PHP even when the page itself was served from a
        // full static cache, which is the one case the timing middleware never
        // sees. Seeding the timing cookie here closes that hole — but only when
        // there is none yet, so a page load already timed keeps its earlier,
        // more accurate moment.
        if (!$timing->age($request->cookie($timing->name()))) {
            $response->headers->setCookie($timing->issue($request));
        }

        return $response;
    }
}
