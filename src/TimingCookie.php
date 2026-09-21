<?php

namespace Pecotamic\Antispam;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The encrypted cookie that records when a form page was rendered.
 *
 * Issuing and reading are deliberately kept in one place so the middleware and
 * the timing rule cannot drift apart over the cookie's name, encryption or
 * lifetime.
 */
class TimingCookie
{
    public function name(): string
    {
        return (string) config('pecotamic.antispam.cookie.name', '_ptas');
    }

    public function issue(Request $request): Cookie
    {
        return cookie(
            $this->name(),
            Crypt::encryptString((string) now()->timestamp),
            $this->lifetime() / 60,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            config('pecotamic.antispam.cookie.same_site', 'Lax')
        );
    }

    /**
     * Seconds elapsed since the form page was rendered, or null when the cookie
     * is missing or cannot be decrypted.
     */
    public function age(?string $token): ?int
    {
        if (!$token) {
            return null;
        }

        try {
            return now()->timestamp - (int) Crypt::decryptString($token);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The cookie must outlive the window the timing rule accepts, otherwise a
     * submission would expire before the rule can judge it.
     */
    public function lifetime(): int
    {
        return (int) config('pecotamic.antispam.rules.timing.maximum_fill_time', 7200);
    }
}
