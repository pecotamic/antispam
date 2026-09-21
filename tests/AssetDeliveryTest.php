<?php

namespace Tests\Unit;

use Pecotamic\Antispam\Assets;
use Tests\TestCase;

class AssetDeliveryTest extends TestCase
{
    public function test_it_serves_the_frontend_module(): void
    {
        $assets = app(Assets::class);

        $response = $this->get("/!/pecotamic-antispam/js/{$assets->fingerprint()}/{$assets->entry()}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
        $this->assertStringContainsString('setupContactForms', $response->getContent());
    }

    public function test_the_current_fingerprint_is_cached_forever(): void
    {
        $assets = app(Assets::class);

        $this->get("/!/pecotamic-antispam/js/{$assets->fingerprint()}/{$assets->entry()}")
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    }

    /**
     * A stale URL still has to work — a page cached in a visitor's browser
     * references the previous fingerprint — but must not be cached for long.
     */
    public function test_a_stale_fingerprint_still_serves_but_briefly(): void
    {
        $this->get('/!/pecotamic-antispam/js/deadbeef/'.app(Assets::class)->entry())
            ->assertHeader('Cache-Control', 'max-age=60, public');
    }

    public function test_the_fingerprint_changes_with_the_content(): void
    {
        $assets = app(Assets::class);
        $before = $assets->fingerprint();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $before);

        // Same instance, same answer — the fingerprint is stable within a
        // request rather than recomputed per reference.
        $this->assertSame($before, $assets->fingerprint());
    }

    public function test_it_refuses_a_file_it_does_not_ship(): void
    {
        $fingerprint = app(Assets::class)->fingerprint();

        $this->get("/!/pecotamic-antispam/js/{$fingerprint}/secrets.js")->assertNotFound();
    }

    public function test_it_refuses_a_traversal_attempt(): void
    {
        $fingerprint = app(Assets::class)->fingerprint();

        $this->get("/!/pecotamic-antispam/js/{$fingerprint}/..%2F..%2Fcomposer.json")->assertNotFound();
    }
}
