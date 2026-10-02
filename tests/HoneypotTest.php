<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Pecotamic\Antispam\Honeypot;
use Pecotamic\Antispam\Http\Middleware\ProtectForms;
use Statamic\Facades\Form;
use Tests\TestCase;

/**
 * Statamic names the honeypot and discards submissions that fill it, but it
 * does not render the field — each template has to build it. A template that
 * never did protects nothing, and nobody notices. The addon renders it instead.
 */
class HoneypotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Form::make('contact')->honeypot('contact_ref')->save();
    }

    /**
     * Form::save() writes a file into the testbench application, where it
     * would outlive this class and colour every test that ran after it.
     */
    protected function tearDown(): void
    {
        Form::find('contact')?->delete();

        parent::tearDown();
    }

    private function through(string $html): string
    {
        return (string) app(ProtectForms::class)->handle(
            Request::create('/', 'GET'),
            fn () => response($html, 200, ['Content-Type' => 'text/html'])
        )->getContent();
    }

    private function page(string $inner = ''): string
    {
        return '<html><head></head><body>'
            .'<form method="post" action="/!/forms/contact">'.$inner.'</form>'
            .'</body></html>';
    }

    public function test_it_renders_the_field_into_a_form_that_lacks_one(): void
    {
        $html = $this->through($this->page());

        $this->assertStringContainsString('name="contact_ref"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('autocomplete="off"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_it_places_the_field_inside_the_form(): void
    {
        $html = $this->through($this->page());

        $formStart = strpos($html, '<form');
        $formEnd = strpos($html, '</form>');

        $this->assertGreaterThan($formStart, strpos($html, 'contact_ref'));
        $this->assertLessThan($formEnd, strpos($html, 'contact_ref'));
    }

    /**
     * What makes rendering safe to have on by default.
     */
    public function test_it_leaves_a_form_that_renders_its_own_alone(): void
    {
        $own = '<div class="hidden"><input type="text" name="contact_ref"></div>';

        $html = $this->through($this->page($own));

        $this->assertSame(1, substr_count($html, 'name="contact_ref"'));
    }

    public function test_it_judges_each_form_separately(): void
    {
        $page = '<html><head></head><body>'
            .'<form action="/!/forms/contact"><input type="text" name="contact_ref"></form>'
            .'<form action="/!/forms/contact"></form>'
            .'</body></html>';

        // One brought its own, the other is given one: two in total.
        $this->assertSame(2, substr_count($this->through($page), 'name="contact_ref"'));
    }

    /**
     * A fragment gets the field even though it gets no script: the honeypot is
     * checked on the server and needs no JavaScript, and a form loaded into a
     * page that already has the script is then fully covered.
     */
    public function test_it_adds_the_field_to_a_fragment_too(): void
    {
        $fragment = '<div><form action="/!/forms/contact"></form></div>';

        $this->assertStringContainsString('contact_ref', $this->through($fragment));
    }

    public function test_it_leaves_unprotected_forms_alone(): void
    {
        config()->set('pecotamic.antispam.forms', ['newsletter']);

        $this->assertStringNotContainsString('contact_ref', $this->through($this->page()));
    }

    public function test_rendering_can_be_switched_off(): void
    {
        config()->set('pecotamic.antispam.honeypot', false);

        $this->assertStringNotContainsString('contact_ref', $this->through($this->page()));
    }

    /**
     */
    #[\PHPUnit\Framework\Attributes\DataProvider("autofilled")]
    /**
     */
    public function test_it_recognises_names_browsers_fill_in(string $name): void
    {
        $this->assertTrue(app(Honeypot::class)->isAutofilled($name), "{$name} should count as risky");
    }

    public static function autofilled(): array
    {
        return [['firstname'], ['first_name'], ['First-Name'], ['name'], ['email'], ['phone'], ['zip']];
    }

    /**
     */
    #[\PHPUnit\Framework\Attributes\DataProvider("safeNames")]
    /**
     */
    public function test_it_accepts_names_browsers_do_not_know(string $name): void
    {
        $this->assertFalse(app(Honeypot::class)->isAutofilled($name), "{$name} should count as safe");
    }

    public static function safeNames(): array
    {
        return [['honeypot'], ['contact_ref'], ['form_ref'], ['ref_code']];
    }
}
