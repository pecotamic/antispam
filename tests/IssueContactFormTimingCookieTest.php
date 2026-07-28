<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Pecotamic\Antispam\Http\Middleware\IssueFormTimingCookie;
use Tests\TestCase;

class IssueContactFormTimingCookieTest extends TestCase
{
    public function test_it_only_issues_a_cookie_on_pages_with_a_statamic_form(): void
    {
        $middleware = app(IssueFormTimingCookie::class);
        $request = Request::create('/');

        $withoutForm = $middleware->handle($request, fn () => response('<main>Inhalt</main>'));
        $withForm = $middleware->handle(
            $request,
            fn () => response('<form action="https://example.test/!/forms/any-form"></form>')
        );

        $this->assertNull($withoutForm->headers->getCookies()[0] ?? null);
        $this->assertSame(
            config('pecotamic.antispam.cookie.name'),
            $withForm->headers->getCookies()[0]->getName()
        );
    }

    public function test_it_rejects_missing_invalid_and_too_fresh_tokens(): void
    {
        $timing = app(IssueFormTimingCookie::class);
        $this->assertFalse($timing->isPlausible(null));
        $this->assertFalse($timing->isPlausible('invalid'));
        $this->assertFalse($timing->isPlausible(Crypt::encryptString((string) now()->timestamp)));
    }

    public function test_it_accepts_a_token_after_five_seconds(): void
    {
        $token = Crypt::encryptString((string) now()->subSeconds(5)->timestamp);
        $this->assertTrue(app(IssueFormTimingCookie::class)->isPlausible($token));
    }

    public function test_it_rejects_an_expired_token(): void
    {
        $token = Crypt::encryptString((string) now()->subHours(3)->timestamp);
        $this->assertFalse(app(IssueFormTimingCookie::class)->isPlausible($token));
    }
}
