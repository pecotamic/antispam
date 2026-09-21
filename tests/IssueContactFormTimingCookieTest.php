<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Pecotamic\Antispam\Http\Middleware\IssueFormTimingCookie;
use Pecotamic\Antispam\TimingCookie;
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
            app(TimingCookie::class)->name(),
            $withForm->headers->getCookies()[0]->getName()
        );
    }

    public function test_it_reads_back_the_age_of_a_token_it_issued(): void
    {
        $cookie = app(TimingCookie::class);

        $this->assertNull($cookie->age(null));
        $this->assertNull($cookie->age('invalid'));
        $this->assertSame(0, $cookie->age(Crypt::encryptString((string) now()->timestamp)));
        $this->assertSame(5, $cookie->age(Crypt::encryptString((string) now()->subSeconds(5)->timestamp)));
    }

    public function test_the_timing_rule_rejects_too_fresh_and_expired_tokens(): void
    {
        $this->assertTrue($this->antispam(0)->rejects($this->submission()));
        $this->assertTrue($this->antispam(3 * 3600)->rejects($this->submission()));
        $this->assertFalse($this->antispam(5)->rejects($this->submission()));
    }
}
