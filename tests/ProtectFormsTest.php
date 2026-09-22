<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Pecotamic\Antispam\Http\Middleware\ProtectForms;
use Pecotamic\Antispam\PageState;
use Pecotamic\Antispam\TimingCookie;
use Tests\TestCase;

/**
 * The middleware places the protection on form pages itself, rather than
 * leaving it to a tag the site has to add in the right place.
 *
 * The pixel is an <img>, valid only in the body. Placed in the head it ends
 * the head for the HTML parser and everything after it — analytics, meta and
 * link tags — is reparented into the body. Injecting before </body> is correct
 * by construction, whatever the site's templates look like.
 */
class ProtectFormsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('pecotamic.antispam.rules.pixel.weight', 60);
    }

    public function test_it_places_the_markup_before_the_closing_body_tag(): void
    {
        $html = $this->through($this->page());

        $this->assertStringContainsString('/!/pecotamic-antispam/p.png', $html);
        $this->assertStringContainsString('data-pecotamic-antispam', $html);

        $this->assertLessThan(
            strpos($html, '</body>'),
            strpos($html, 'p.png'),
            'the markup must sit inside the body'
        );
    }

    /**
     * The failure this placement exists to prevent.
     */
    public function test_it_never_puts_the_image_in_the_head(): void
    {
        $html = $this->through($this->page());

        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringNotContainsString('<img', $head);
    }

    public function test_it_issues_the_timing_cookie(): void
    {
        $response = $this->respond($this->page());

        $names = collect($response->headers->getCookies())->map->getName();

        $this->assertTrue($names->contains(app(TimingCookie::class)->name()));
    }

    public function test_it_leaves_pages_without_a_form_alone(): void
    {
        $html = $this->through('<html><body><p>Über uns</p></body></html>');

        $this->assertStringNotContainsString('pecotamic-antispam', $html);
    }

    /**
     * A fragment — a partial reload, an AJAX-rendered section — has no body to
     * close, and guessing where to append would be worse than doing nothing.
     */
    public function test_it_leaves_a_fragment_alone(): void
    {
        $fragment = '<div><form action="/!/forms/contact"></form></div>';

        $this->assertSame($fragment, $this->through($fragment));
    }

    public function test_it_leaves_non_html_responses_alone(): void
    {
        $json = '{"action":"/!/forms/contact"}';

        $this->assertSame($json, $this->through($json, contentType: 'application/json'));
    }

    public function test_it_leaves_error_responses_alone(): void
    {
        $this->assertStringNotContainsString(
            'pecotamic-antispam',
            $this->through($this->page(), status: 500)
        );
    }

    public function test_it_leaves_post_responses_alone(): void
    {
        $this->assertStringNotContainsString(
            'pecotamic-antispam',
            $this->through($this->page(), method: 'POST')
        );
    }

    public function test_injection_can_be_switched_off(): void
    {
        config()->set('pecotamic.antispam.inject', false);

        $this->assertStringNotContainsString('pecotamic-antispam', $this->through($this->page()));
    }

    /**
     * A tag that placed the markup deliberately must not be doubled up on.
     */
    public function test_it_defers_to_markup_a_tag_already_placed(): void
    {
        app(PageState::class)->claimMarkup();

        $this->assertStringNotContainsString('pecotamic-antispam', $this->through($this->page()));
    }

    public function test_it_places_the_markup_once_however_many_forms(): void
    {
        $twoForms = '<html><head></head><body>'
            .'<form action="/!/forms/contact"></form>'
            .'<form action="/!/forms/newsletter"></form>'
            .'</body></html>';

        $this->assertSame(1, substr_count($this->through($twoForms), 'data-pecotamic-antispam'));
    }

    private function page(): string
    {
        return '<html><head><title>Kontakt</title></head><body>'
            .'<form action="https://example.test/!/forms/contact"></form>'
            .'</body></html>';
    }

    private function through(string $content, string $contentType = 'text/html', int $status = 200, string $method = 'GET'): string
    {
        return (string) $this->respond($content, $contentType, $status, $method)->getContent();
    }

    private function respond(string $content, string $contentType = 'text/html', int $status = 200, string $method = 'GET')
    {
        $request = Request::create('/kontakt', $method);
        $request->cookies->set(
            app(TimingCookie::class)->name(),
            Crypt::encryptString((string) now()->subSeconds(30)->timestamp)
        );

        return app(ProtectForms::class)->handle(
            $request,
            fn () => response($content, $status, ['Content-Type' => $contentType])
        );
    }
}
