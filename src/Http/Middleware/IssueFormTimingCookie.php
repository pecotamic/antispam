<?php

namespace Pecotamic\Antispam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class IssueFormTimingCookie
{
    private const FORM_ACTION_FRAGMENT = '/!/forms/';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $this->containsStatamicForm($response)) {
            $response->headers->setCookie(
                cookie(
                    $this->cookieName(),
                    Crypt::encryptString((string)now()->timestamp),
                    $this->maximumAge() / 60,
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    config('pecotamic.antispam.cookie.same_site', 'Lax')
                )
            );
        }

        return $response;
    }

    private function containsStatamicForm(Response $response): bool
    {
        $content = $response->getContent();

        return is_string($content)
            && str_contains($content, self::FORM_ACTION_FRAGMENT);
    }

    public function cookieName(): string
    {
        return (string)config('pecotamic.antispam.cookie.name', 'statamic_form_started_at');
    }

    private function maximumAge(): int
    {
        return (int)config('pecotamic.antispam.maximum_fill_time', 7200);
    }

    public function isPlausible(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        try {
            $age = now()->timestamp - (int)Crypt::decryptString($token);
        } catch (\Throwable) {
            return false;
        }

        return $age >= (int)config('pecotamic.antispam.minimum_fill_time', 5)
            && $age <= $this->maximumAge();
    }
}
