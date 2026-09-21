<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScriptRuleTest extends TestCase
{
    public function test_it_rejects_cyrillic_text(): void
    {
        $this->assertTrue($this->antispam()->rejects($this->submission([
            'message' => 'Предлагаем услуги продвижения сайта',
        ])));
    }

    /**
     * A single foreign character among latin ones is the shape of a homoglyph
     * attempt, so one is enough.
     */
    public function test_it_rejects_a_single_homoglyph(): void
    {
        $this->assertTrue($this->antispam()->rejects($this->submission([
            'name' => 'Аndreas Müller', // leading A is Cyrillic
        ])));
    }

    #[DataProvider('ordinaryText')]
    public function test_it_accepts_ordinary_text(string $value): void
    {
        $this->assertFalse(
            $this->antispam()->rejects($this->submission(['message' => $value])),
            "'{$value}' was wrongly rejected"
        );
    }

    public static function ordinaryText(): array
    {
        return [
            'umlauts' => ['Grüße aus München, Jörg Schröder'],
            'punctuation and digits' => ['Termin am 24.12. um 10:30 Uhr — geht das?'],
            'currency' => ['Kostet das 500 € oder mehr?'],
            'emoji' => ['Vielen Dank für die gute Beratung 😊'],
            'french accents' => ['Je voudrais un rendez-vous, ça va?'],
        ];
    }

    public function test_additional_scripts_can_be_allowed(): void
    {
        config()->set('pecotamic.antispam.rules.script.allowed', ['Latin', 'Common', 'Inherited', 'Greek']);

        $this->assertFalse($this->antispam()->rejects($this->submission([
            'name' => 'Γιώργος Παπαδόπουλος',
        ])));
    }
}
