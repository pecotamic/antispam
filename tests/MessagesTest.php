<?php

namespace Tests\Unit;

use Pecotamic\Antispam\Markup;
use Tests\TestCase;

/**
 * The field messages are rendered by the server, so a site translates them the
 * way it translates anything else — rather than the addon being fixed to the
 * one language its JavaScript defaults happen to be written in.
 */
class MessagesTest extends TestCase
{
    private function rendered(): array
    {
        preg_match('/data-pecotamic-antispam="([^"]*)"/', app(Markup::class)->html(), $match);

        return json_decode(html_entity_decode($match[1] ?? '{}'), true);
    }

    public function test_it_renders_the_messages_in_english_by_default(): void
    {
        app()->setLocale('en');

        $this->assertSame([
            'required' => 'Please fill this in.',
            'email' => 'Please enter a valid email address.',
            'confirm' => 'Please confirm.',
        ], $this->rendered()['messages']);
    }

    public function test_it_follows_the_application_locale(): void
    {
        app()->setLocale('de');

        $this->assertSame('Bitte ausfüllen.', $this->rendered()['messages']['required']);
    }

    /**
     * A site overrides them like any other package translation.
     */
    public function test_a_site_can_override_a_message(): void
    {
        app()->setLocale('en');
        app('translator')->addLines(
            ['messages.required' => 'Dieses Feld brauchen wir.'],
            'en',
            'pecotamic-antispam'
        );

        $this->assertSame('Dieses Feld brauchen wir.', $this->rendered()['messages']['required']);
    }

    public function test_the_translations_are_publishable(): void
    {
        $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(
            \Pecotamic\Antispam\ServiceProvider::class,
            'pecotamic-antispam-translations'
        );

        $this->assertNotEmpty($paths, 'translations should be publishable');
    }
}
