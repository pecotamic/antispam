<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Pecotamic\Antispam\Antispam;
use Pecotamic\Antispam\PixelCookie;
use Pecotamic\Antispam\TimingCookie;
use Tests\TestCase;

class PixelRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These tests are about a site that has the tag in its templates.
        // Without that, the proof rules stay quiet by design.
        app(\Pecotamic\Antispam\ProtectionReach::class)->record();
    }

    public function test_the_endpoint_returns_an_image_and_leaves_a_cookie(): void
    {
        $response = $this->get('/!/pecotamic-antispam/p.png');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/gif');
        $response->assertCookie(app(PixelCookie::class)->name());
    }

    /**
     * The pixel request reaches PHP even when the page came from a full static
     * cache, which is the one case the timing middleware never sees.
     */
    public function test_the_endpoint_seeds_the_timing_cookie_when_there_is_none(): void
    {
        $this->get('/!/pecotamic-antispam/p.png')
            ->assertCookie(app(TimingCookie::class)->name());
    }

    /**
     * A page load already timed keeps its earlier, more accurate moment.
     *
     * Driven through the controller rather than the HTTP stack because
     * Laravel's cookie encryption sits in between and would discard a cookie
     * planted by hand.
     */
    public function test_it_leaves_an_existing_timing_cookie_alone(): void
    {
        $timing = app(TimingCookie::class);

        $request = Request::create('/');
        $request->cookies->set(
            $timing->name(),
            Crypt::encryptString((string) now()->subMinutes(10)->timestamp)
        );

        $response = app(\Pecotamic\Antispam\Http\Controllers\PixelController::class)(
            $request, app(PixelCookie::class), $timing, app(\Pecotamic\Antispam\ProtectionReach::class)
        );

        $names = collect($response->headers->getCookies())->map->getName();

        $this->assertTrue($names->contains(app(PixelCookie::class)->name()));
        $this->assertFalse($names->contains($timing->name()));
    }

    public function test_it_rejects_a_submission_without_the_pixel_cookie(): void
    {
        config()->set('pecotamic.antispam.rules.pixel.weight', 100);

        $this->assertTrue($this->withPixel(null)->rejects($this->submission()));
    }

    public function test_it_accepts_a_submission_carrying_the_pixel_cookie(): void
    {
        config()->set('pecotamic.antispam.rules.pixel.weight', 100);

        $this->assertFalse($this->withPixel(10)->rejects($this->submission()));
    }

    public function test_it_rejects_a_stale_pixel_cookie(): void
    {
        config()->set('pecotamic.antispam.rules.pixel.weight', 100);

        $this->assertTrue($this->withPixel(3 * 3600)->rejects($this->submission()));
    }

    /**
     * The two proofs cover each other: a visitor needs only one of them, but a
     * blind POST has neither and exceeds the threshold.
     */
    public function test_either_proof_alone_lets_a_visitor_through(): void
    {
        config()->set('pecotamic.antispam.rules.pixel.weight', 60);
        config()->set('pecotamic.antispam.rules.interaction.weight', 60);

        // Pixel only — a visitor without JavaScript.
        $this->assertFalse($this->request(pixelAge: 10, proofAge: null)->rejects($this->submission()));

        // Interaction proof only — an ad blocker swallowed the pixel.
        $this->assertFalse($this->request(pixelAge: null, proofAge: 10)->rejects($this->submission()));

        // Neither — a blind POST.
        $this->assertTrue($this->request(pixelAge: null, proofAge: null)->rejects($this->submission()));
    }

    private function withPixel(?int $secondsAgo): Antispam
    {
        return $this->request(pixelAge: $secondsAgo, proofAge: null);
    }

    private function request(?int $pixelAge, ?int $proofAge): Antispam
    {
        $stamp = fn (int $age) => Crypt::encryptString((string) now()->subSeconds($age)->timestamp);

        $request = Request::create('/', 'POST', $proofAge === null
            ? []
            : [config('pecotamic.antispam.rules.interaction.field') => $stamp($proofAge)]);

        $request->cookies->set(app(TimingCookie::class)->name(), $stamp(10));

        if ($pixelAge !== null) {
            $request->cookies->set(app(PixelCookie::class)->name(), $stamp($pixelAge));
        }

        $this->app->instance('request', $request);

        return $this->app->make(Antispam::class);
    }
}
