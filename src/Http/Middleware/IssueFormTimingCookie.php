<?php

namespace Pecotamic\Antispam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pecotamic\Antispam\TimingCookie;
use Symfony\Component\HttpFoundation\Response;

class IssueFormTimingCookie
{
    private const FORM_ACTION_FRAGMENT = '/!/forms/';

    public function __construct(private TimingCookie $cookie)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $this->containsStatamicForm($response)) {
            $response->headers->setCookie($this->cookie->issue($request));
        }

        return $response;
    }

    private function containsStatamicForm(Response $response): bool
    {
        $content = $response->getContent();

        return is_string($content)
            && str_contains($content, self::FORM_ACTION_FRAGMENT);
    }
}
