<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GibberishRuleTest extends TestCase
{
    /**
     * German names are the reason this rule transliterates before counting:
     * measured against a plain [aeiou] class, every one of these would be
     * rejected because its umlauts do not count as vowels.
     *
     */
    #[DataProvider('realNames')]
    public function test_it_accepts_real_german_names(string $name): void
    {
        $this->assertFalse(
            $this->antispam()->rejects($this->submission(['name' => $name])),
            "'{$name}' was wrongly rejected as gibberish"
        );
    }

    public static function realNames(): array
    {
        return [
            ['Jörg Schröder'],
            ['Grüße, Jörg Schröder'],
            ['Dr. Björn Kühn'],
            ['Günther Löw-Schütz'],
            ['Christoph Schmidt'],
            ['Sylvia Krystyn'],
            ['Sehr geehrte Damen und Herren'],
            ['MVZ Dr. Schmidt GmbH'],
        ];
    }

    #[DataProvider('nonProse')]
    public function test_it_ignores_values_that_are_not_prose(string $value): void
    {
        $this->assertFalse(
            $this->antispam()->rejects($this->submission(['feld' => $value])),
            "'{$value}' was wrongly rejected as gibberish"
        );
    }

    public static function nonProse(): array
    {
        return [
            'phone number' => ['+49 170 1234567'],
            'postcode and city' => ['80331 München'],
            'date' => ['24.12.2025'],
            'short handle' => ['kfo-berlin'],
        ];
    }

    public function test_it_rejects_a_keyboard_mash(): void
    {
        $this->assertTrue($this->antispam()->rejects($this->submission([
            'name' => 'qwrtzplkjhgfdsxcvbnm',
        ])));
    }

    public function test_it_skips_fields_listed_as_exceptions(): void
    {
        config()->set('pecotamic.antispam.rules.gibberish.except', ['referenz']);

        $this->assertFalse($this->antispam()->rejects($this->submission([
            'referenz' => 'qwrtzplkjhgfdsxcvbnm',
        ])));
    }
}
