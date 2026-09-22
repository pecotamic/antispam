<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Pecotamic\Antispam\Antispam;
use Pecotamic\Antispam\TimingCookie;
use Tests\TestCase;

class InteractionRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('pecotamic.antispam.rules.interaction.weight', 100);

        // These tests are about a site that has the tag in its templates.
        // Without that, the proof rules stay quiet by design.
        app(\Pecotamic\Antispam\ProtectionReach::class)->record();
    }

    public function test_the_endpoint_hands_out_a_readable_proof(): void
    {
        $response = $this->get('/!/pecotamic-antispam/proof');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(0, app(\Pecotamic\Antispam\SignedTimestamp::class)->age(
            $response->json('proof')
        ));
    }

    public function test_it_rejects_a_submission_without_a_proof(): void
    {
        $this->assertTrue($this->withProof(null)->rejects($this->submission()));
    }

    public function test_it_rejects_a_forged_proof(): void
    {
        $this->assertTrue($this->withProof('made-up')->rejects($this->submission()));
    }

    public function test_it_accepts_a_proof_of_plausible_age(): void
    {
        $this->assertFalse($this->withProof($this->proof(10))->rejects($this->submission()));
    }

    /**
     * Fetching a proof and submitting in the same breath is the shape of a bot
     * that follows the extra round trip but not the pause.
     */
    public function test_it_rejects_a_proof_used_immediately(): void
    {
        $this->assertTrue($this->withProof($this->proof(0))->rejects($this->submission()));
    }

    public function test_it_rejects_an_expired_proof(): void
    {
        $this->assertTrue($this->withProof($this->proof(3 * 3600))->rejects($this->submission()));
    }

    public function test_the_field_name_is_configurable(): void
    {
        config()->set('pecotamic.antispam.rules.interaction.field', 'nachweis');

        $request = Request::create('/', 'POST', ['nachweis' => $this->proof(10)]);
        $request->cookies->set(app(TimingCookie::class)->name(), $this->proof(10));
        $this->app->instance('request', $request);

        $this->assertFalse($this->app->make(Antispam::class)->rejects($this->submission()));
    }

    private function proof(int $secondsAgo): string
    {
        return Crypt::encryptString((string) now()->subSeconds($secondsAgo)->timestamp);
    }

    private function withProof(?string $proof): Antispam
    {
        $field = config('pecotamic.antispam.rules.interaction.field');

        $request = Request::create('/', 'POST', $proof === null ? [] : [$field => $proof]);
        $request->cookies->set(app(TimingCookie::class)->name(), $this->proof(10));

        $this->app->instance('request', $request);

        return $this->app->make(Antispam::class);
    }
}
