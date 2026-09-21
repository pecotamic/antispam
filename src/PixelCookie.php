<?php

namespace Pecotamic\Antispam;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The cookie left behind by the tracking pixel on the form page.
 */
class PixelCookie
{
    public function __construct(private readonly SignedTimestamp $timestamp)
    {
    }

    public function name(): string
    {
        return (string) config('pecotamic.antispam.rules.pixel.cookie', '_ptap');
    }

    public function issue(Request $request): Cookie
    {
        return cookie(
            $this->name(),
            $this->timestamp->issue(),
            $this->lifetime() / 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            config('pecotamic.antispam.cookie.same_site', 'Lax')
        );
    }

    public function age(?string $token): ?int
    {
        return $this->timestamp->age($token);
    }

    public function lifetime(): int
    {
        return (int) config('pecotamic.antispam.rules.pixel.maximum_age', 7200);
    }
}
