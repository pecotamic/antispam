<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Pecotamic\Antispam\Antispam;
use Pecotamic\Antispam\TagPresence;
use Pecotamic\Antispam\TimingCookie;
use Tests\TestCase;

/**
 * The proof rules ask for evidence only the {{ antispam }} tag produces. On a
 * site that has not added the tag, that evidence can never arrive — so the
 * rules must stay quiet there rather than reject every genuine submission.
 *
 * Without this, a composer update alone would silently swallow every enquiry
 * on a site whose templates had not been touched yet: pixel (60) and
 * interaction (60) together clear the threshold of 100, and the visitor is
 * shown a success message either way.
 */
class TagPresenceTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Stock weights, as a site receives them.
        $app['config']->set('pecotamic.antispam.rules.pixel.weight', 60);
        $app['config']->set('pecotamic.antispam.rules.interaction.weight', 60);
    }

    public function test_a_genuine_enquiry_survives_on_a_site_without_the_tag(): void
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

    public function test_the_same_visitor_is_rejected_once_the_tag_is_in_use(): void
    {
        app(TagPresence::class)->record();

        $this->assertTrue($this->visitor()->rejects($this->submission([
            'name' => 'Anna Weber',
            'message' => 'Ich hätte gerne einen Termin.',
        ])));
    }

    public function test_the_tag_records_its_presence_as_it_renders(): void
    {
        view()->addNamespace('antispamtest', __DIR__.'/views');

        $this->assertFalse(app(TagPresence::class)->confirmed());

        view('antispamtest::tag')->render();

        $this->assertTrue(app(TagPresence::class)->confirmed());
    }

    /**
     * Under full static caching the page is served without PHP and the tag
     * never renders — but the pixel it left in the cached page is still
     * fetched, which is where the presence gets recorded instead.
     */
    public function test_the_pixel_endpoint_records_it_too(): void
    {
        $this->assertFalse(app(TagPresence::class)->confirmed());

        $this->get('/!/pecotamic-antispam/p.png')->assertOk();

        $this->assertTrue(app(TagPresence::class)->confirmed());
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
