<?php

namespace Tests\Unit;

use Tests\TestCase;

class ContactFormSpamFilterTest extends TestCase
{
    public function test_it_silently_rejects_a_submission_without_timing_cookie(): void
    {
        $this->assertTrue($this->antispam(null)->rejects($this->submission()));
    }

    public function test_it_accepts_a_normal_request_with_valid_timing_cookie(): void
    {
        $this->assertFalse($this->antispam()->rejects($this->submission([
            'betreff' => 'Beratung',
            'message' => 'Ich interessiere mich für einen Beratungstermin.',
        ])));
    }

    public function test_it_rejects_the_observed_radio_spam_campaign(): void
    {
        $this->assertTrue($this->antispam(10)->rejects($this->submission([
            'betreff' => 'Hello from Stehr, Hamill and Sauer',
            'message' => 'heard about this on instrumental country radio, decided to give it a try.',
        ])));
    }

    public function test_it_leaves_unprotected_forms_alone(): void
    {
        config()->set('pecotamic.antispam.forms', ['newsletter']);

        $this->assertFalse($this->antispam(null)->rejects($this->submission([], 'contact')));
    }

    public function test_a_rule_with_zero_weight_is_switched_off(): void
    {
        config()->set('pecotamic.antispam.rules.timing.weight', 0);

        $this->assertFalse($this->antispam(null)->rejects($this->submission([
            'message' => 'Ich hätte gerne einen Termin.',
        ])));
    }

    public function test_indications_below_the_threshold_only_reject_in_combination(): void
    {
        config()->set('pecotamic.antispam.rules.timing.weight', 60);
        config()->set('pecotamic.antispam.rules.gibberish.weight', 60);

        $gibberish = ['name' => 'qwrtzplkjhgfdsxcvbnm'];

        // Each on its own stays below the threshold of 100.
        $this->assertFalse($this->antispam(null)->rejects($this->submission()));
        $this->assertFalse($this->antispam()->rejects($this->submission($gibberish)));

        // Together they exceed it.
        $this->assertTrue($this->antispam(null)->rejects($this->submission($gibberish)));
    }

    public function test_the_assessment_reports_score_and_reasons(): void
    {
        $assessment = $this->antispam(null)->assess($this->submission());

        $this->assertSame(100, $assessment->score());
        $this->assertSame(100, $assessment->threshold());
        $this->assertArrayHasKey('timing', $assessment->reasons());
        $this->assertStringContainsString('no valid timing cookie', $assessment->summary());
    }
}
