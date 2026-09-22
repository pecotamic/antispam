<?php

namespace Tests\Unit;

use Pecotamic\Antispam\PageState;
use Tests\TestCase;

/**
 * The tag no longer carries the markup. With injection on it contributes
 * configuration only, which is what makes its placement a non-issue: wherever
 * it sits, the middleware still puts the markup before </body>.
 */
class AntispamTagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        view()->addNamespace('antispamtest', __DIR__.'/views');
        config()->set('pecotamic.antispam.rules.pixel.weight', 60);
    }

    private function render(string $view = 'tag'): string
    {
        return (string) view("antispamtest::{$view}")->render();
    }

    public function test_it_renders_nothing_while_the_middleware_places_the_markup(): void
    {
        $this->assertSame('', trim($this->render()));
    }

    public function test_it_passes_parameters_on_to_the_frontend(): void
    {
        $this->render('params');

        $this->assertSame([
            'selector' => 'form.enquiry',
            'eventName' => 'sent',
        ], app(PageState::class)->options());
    }

    /**
     * A page may carry the tag more than once; the options accumulate rather
     * than the last occurrence winning outright.
     */
    public function test_options_from_several_occurrences_accumulate(): void
    {
        $this->render('params');
        $this->render('tag');

        $this->assertArrayHasKey('selector', app(PageState::class)->options());
    }

    public function test_with_injection_off_the_tag_renders_the_markup_itself(): void
    {
        config()->set('pecotamic.antispam.inject', false);

        $output = $this->render();

        $this->assertStringContainsString('/!/pecotamic-antispam/p.png', $output);
        $this->assertStringContainsString('data-pecotamic-antispam', $output);
    }

    public function test_with_injection_off_it_still_renders_only_once(): void
    {
        config()->set('pecotamic.antispam.inject', false);

        $output = $this->render('thrice');

        $this->assertSame(1, substr_count($output, '<script'));
        $this->assertSame(1, substr_count($output, '<img'));
    }

    /**
     * The proof field name exists once, in the config the server reads it
     * from. A second copy in the frontend would drift, and a proof sent under
     * a stale name is indistinguishable from no proof at all.
     */
    public function test_the_frontend_is_told_the_configured_proof_field(): void
    {
        config()->set('pecotamic.antispam.inject', false);
        config()->set('pecotamic.antispam.rules.interaction.weight', 60);
        config()->set('pecotamic.antispam.rules.interaction.field', 'nachweis');

        preg_match('/data-pecotamic-antispam="([^"]*)"/', $this->render(), $match);
        $config = json_decode(html_entity_decode($match[1] ?? '{}'), true);

        $this->assertSame('nachweis', $config['proof']['field']);
    }

    public function test_the_frontend_is_told_to_skip_a_proof_when_the_rule_is_off(): void
    {
        config()->set('pecotamic.antispam.inject', false);
        config()->set('pecotamic.antispam.rules.interaction.weight', 0);

        preg_match('/data-pecotamic-antispam="([^"]*)"/', $this->render(), $match);

        $this->assertFalse(json_decode(html_entity_decode($match[1] ?? '{}'), true)['proof']);
    }
}
