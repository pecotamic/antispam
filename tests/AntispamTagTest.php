<?php

namespace Tests\Unit;

use Pecotamic\Antispam\Assets;
use Tests\TestCase;

class AntispamTagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        view()->addNamespace('antispamtest', __DIR__.'/views');
    }

    /**
     * Rendered through a real Antlers view: the bare parser does not resolve
     * addon tags, so parsing a string would test something the application
     * never does.
     */
    private function render(string $view = 'tag'): string
    {
        return (string) view("antispamtest::{$view}")->render();
    }

    public function test_it_renders_the_pixel_and_the_module(): void
    {
        config()->set('pecotamic.antispam.rules.pixel.weight', 60);

        $output = $this->render();

        $this->assertStringContainsString('/!/pecotamic-antispam/p.png', $output);
        $this->assertStringContainsString('/!/pecotamic-antispam/js/', $output);
        $this->assertStringContainsString(app(Assets::class)->fingerprint(), $output);
        $this->assertStringContainsString('type="module"', $output);
    }

    /**
     * The whole reason the configuration is rendered server-side: the proof
     * field name exists once, in the config the server reads it from. A second
     * copy in the frontend would drift, and a proof under the wrong name is
     * indistinguishable from no proof at all.
     */
    public function test_it_passes_the_configured_proof_field_to_the_frontend(): void
    {
        config()->set('pecotamic.antispam.rules.interaction.weight', 60);
        config()->set('pecotamic.antispam.rules.interaction.field', 'nachweis');

        $config = $this->renderedConfig();

        $this->assertSame('nachweis', $config['proof']['field']);
        $this->assertStringContainsString('/!/pecotamic-antispam/proof', $config['proof']['endpoint']);
    }

    public function test_it_tells_the_frontend_to_skip_a_proof_when_the_rule_is_off(): void
    {
        config()->set('pecotamic.antispam.rules.interaction.weight', 0);

        $this->assertFalse($this->renderedConfig()['proof']);
    }

    public function test_it_renders_no_pixel_when_the_rule_is_off(): void
    {
        config()->set('pecotamic.antispam.rules.pixel.weight', 0);

        $this->assertStringNotContainsString('p.png', $this->render());
    }

    public function test_a_site_can_override_options_at_the_tag(): void
    {
        $config = $this->renderedConfig('params');

        $this->assertSame('form.enquiry', $config['selector']);
        $this->assertSame('sent', $config['eventName']);
    }

    /**
     * Several forms on a page must not each pull in the module and the pixel.
     */
    public function test_it_renders_once_however_often_it_appears(): void
    {
        config()->set('pecotamic.antispam.rules.pixel.weight', 60);

        $output = $this->render('thrice');

        $this->assertSame(1, substr_count($output, '<script'));
        $this->assertSame(1, substr_count($output, '<img'));
    }

    /**
     * @return array<string, mixed>
     */
    private function renderedConfig(string $view = 'tag'): array
    {
        preg_match('/data-pecotamic-antispam="([^"]*)"/', $this->render($view), $match);

        return json_decode(html_entity_decode($match[1] ?? '{}'), true);
    }
}
