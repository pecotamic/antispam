<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Pecotamic\Antispam\Antispam;
use Pecotamic\Antispam\ProtectionReach;
use Pecotamic\Antispam\TimingCookie;
use Tests\TestCase;

/**
 * The proof rules ask for evidence only the protection markup produces. Where
 * that markup is not reaching pages, the evidence can never arrive — so the
 * rules must stay quiet rather than reject every genuine submission.
 *
 * Pixel (60) and interaction (60) together clear the threshold of 100, so
 * without this a site upgrading while pages sit in a static cache built before
 * the markup existed would silently swallow every enquiry, the visitor being
 * shown a success message either way.
 */
class ProtectionReachTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Stock weights, as a site receives them.
        $app['config']->set('pecotamic.antispam.rules.pixel.weight', 60);
        $app['config']->set('pecotamic.antispam.rules.interaction.weight', 60);
    }

    public function test_a_genuine_enquiry_survives_before_the_markup_has_reached_anyone(): void
    {
        $assessment = $this->visitor()->assess($this->submission([
            'name' => 'Anna Weber',
            'email' => 'anna.weber@gmx.de',
            'message' => 'Ich hätte gerne einen Termin zur Beratung.',
        ]));

        $this->assertFalse(
            $assessment->rejected(),
            'A genuine enquiry was rejected: '.$assessment->summary()
        );
    }

    public function test_the_same_visitor_is_rejected_once_the_markup_is_reaching_pages(): void
    {
        app(ProtectionReach::class)->record();

        $this->assertTrue($this->visitor()->rejects($this->submission([
            'name' => 'Anna Weber',
            'message' => 'Ich hätte gerne einen Termin.',
        ])));
    }

    /**
     * Only a real fetch counts as reach. That the markup was rendered says
     * nothing about pages served from a cache built before it existed.
     */
    public function test_a_pixel_fetch_records_reach(): void
    {
        $this->assertFalse(app(ProtectionReach::class)->confirmed());

        $this->get('/!/pecotamic-antispam/p.png')->assertOk();

        $this->assertTrue(app(ProtectionReach::class)->confirmed());
    }

    /**
     * A visitor with a timing cookie but neither proof — which is every
     * visitor on a site without the tag.
     */
    private function visitor(): Antispam
    {
        $request = Request::create('/', 'POST');
        $request->cookies->set(
            app(TimingCookie::class)->name(),
            Crypt::encryptString((string) now()->subSeconds(30)->timestamp)
        );

        $this->app->instance('request', $request);

        return $this->app->make(Antispam::class);
    }
}
