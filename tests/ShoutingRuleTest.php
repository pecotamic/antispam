<?php

namespace Tests\Unit;

use Tests\TestCase;

class ShoutingRuleTest extends TestCase
{
    public function test_it_rejects_a_shouted_message(): void
    {
        $this->assertTrue($this->antispam()->rejects($this->submission([
            'message' => 'BUY CHEAP VIAGRA NOW CLICK HERE',
        ])));
    }

    /**
     * Short acronym-heavy values reach a high capital ratio without being
     * shouted, which is why the rule ignores anything below prose length.
     */
    public function test_it_accepts_acronym_heavy_practice_names(): void
    {
        $this->assertFalse($this->antispam()->rejects($this->submission([
            'name' => 'MVZ Dr. Schmidt GmbH',
            'betreff' => 'OP-Termin',
        ])));
    }

    public function test_it_accepts_a_normal_long_message(): void
    {
        $this->assertFalse($this->antispam()->rejects($this->submission([
            'message' => 'Ich möchte gerne einen Termin zur Beratung vereinbaren. '
                .'Bitte melden Sie sich telefonisch bei mir.',
        ])));
    }
}
