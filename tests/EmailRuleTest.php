<?php

namespace Tests\Unit;

use Tests\TestCase;

class EmailRuleTest extends TestCase
{
    public function test_it_rejects_a_bundled_throwaway_domain(): void
    {
        $this->assertTrue($this->antispam()->rejects($this->submission([
            'email' => 'someone@mailinator.com',
        ])));
    }

    public function test_it_matches_the_domain_case_insensitively(): void
    {
        $this->assertTrue($this->antispam()->rejects($this->submission([
            'email' => 'Someone@MailInator.COM',
        ])));
    }

    /**
     * Addresses are found by shape, not by field name, so one pasted into a
     * message body is checked as well.
     */
    public function test_it_finds_an_address_regardless_of_field_name(): void
    {
        $this->assertTrue($this->antispam()->rejects($this->submission([
            'irgendwas' => 'kontakt@yopmail.com',
        ])));
    }

    public function test_sites_can_add_their_own_domains(): void
    {
        config()->set('pecotamic.antispam.rules.email.disposable_domains', ['wegwerf.example']);

        $this->assertTrue($this->antispam()->rejects($this->submission([
            'email' => 'a@wegwerf.example',
        ])));
    }

    public function test_it_accepts_ordinary_addresses(): void
    {
        $this->assertFalse($this->antispam()->rejects($this->submission([
            'name' => 'Anna Weber',
            'email' => 'anna.weber@gmx.de',
            'message' => 'Ich hätte gerne einen Termin.',
        ])));
    }

    /**
     * Values that are not addresses must pass untouched — the rule would
     * otherwise judge every field in the submission.
     */
    public function test_it_ignores_values_that_are_not_addresses(): void
    {
        $this->assertFalse($this->antispam()->rejects($this->submission([
            'message' => 'Erreichbar unter 089 1234567, kein Mail bitte.',
        ])));
    }
}
