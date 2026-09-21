<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Pecotamic\Antispam\Antispam;
use Pecotamic\Antispam\TimingCookie;
use Tests\TestCase;

/**
 * The rate limit and duplicate rules remember across submissions, so they are
 * switched off for the rest of the suite and exercised here.
 */
class StatefulRulesTest extends TestCase
{
    public function test_the_rate_limit_lets_the_allowance_through_and_stops_the_rest(): void
    {
        config()->set('pecotamic.antispam.rules.rate_limit.weight', 100);
        config()->set('pecotamic.antispam.rules.rate_limit.maximum', 3);

        for ($i = 1; $i <= 3; $i++) {
            $this->assertFalse(
                $this->fromAddress('203.0.113.7')->rejects($this->submission(['message' => "Anfrage {$i}"])),
                "submission {$i} should still be within the allowance"
            );
        }

        $this->assertTrue(
            $this->fromAddress('203.0.113.7')->rejects($this->submission(['message' => 'Anfrage 4']))
        );
    }

    public function test_the_rate_limit_counts_per_address(): void
    {
        config()->set('pecotamic.antispam.rules.rate_limit.weight', 100);
        config()->set('pecotamic.antispam.rules.rate_limit.maximum', 1);

        $this->assertFalse($this->fromAddress('203.0.113.7')->rejects($this->submission(['message' => 'A'])));
        $this->assertFalse($this->fromAddress('203.0.113.8')->rejects($this->submission(['message' => 'B'])));
        $this->assertTrue($this->fromAddress('203.0.113.7')->rejects($this->submission(['message' => 'C'])));
    }

    public function test_it_rejects_identical_content_on_the_second_attempt(): void
    {
        config()->set('pecotamic.antispam.rules.duplicate.weight', 100);

        $payload = ['name' => 'Anna Weber', 'message' => 'Ich hätte gerne einen Termin.'];

        $this->assertFalse($this->antispam()->rejects($this->submission($payload)));
        $this->assertTrue($this->antispam()->rejects($this->submission($payload)));
    }

    public function test_differing_content_is_not_a_duplicate(): void
    {
        config()->set('pecotamic.antispam.rules.duplicate.weight', 100);

        $this->assertFalse($this->antispam()->rejects($this->submission(['message' => 'Erste Anfrage'])));
        $this->assertFalse($this->antispam()->rejects($this->submission(['message' => 'Zweite Anfrage'])));
    }

    /**
     * An empty submission carries no content to compare, and must not make
     * every other empty one look like a repeat.
     */
    public function test_empty_content_is_never_a_duplicate(): void
    {
        config()->set('pecotamic.antispam.rules.duplicate.weight', 100);
        config()->set('pecotamic.antispam.rules.timing.weight', 0);

        $this->assertFalse($this->antispam()->rejects($this->submission([])));
        $this->assertFalse($this->antispam()->rejects($this->submission([])));
    }

    private function fromAddress(string $address): Antispam
    {
        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => $address]);
        $request->cookies->set(
            app(TimingCookie::class)->name(),
            Crypt::encryptString((string) now()->subSeconds(5)->timestamp)
        );

        $this->app->instance('request', $request);

        return $this->app->make(Antispam::class);
    }
}
