<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LinksRuleTest extends TestCase
{
    #[DataProvider('linked')]
    public function test_it_rejects_values_containing_links(string $value): void
    {
        $this->assertTrue($this->antispam()->rejects($this->submission(['message' => $value])));
    }

    public static function linked(): array
    {
        return [
            ['Check out https://example.com for details'],
            ['visit www.example.com'],
            ['[url=http://example.com]click[/url]'],
            ['<a href="http://example.com">click</a>'],
        ];
    }

    /**
     * An email address carries a domain but is not a link. Treating bare
     * "domain.tld" as one would reject every address passed through as a value.
     */
    public function test_it_accepts_an_email_address_in_the_message(): void
    {
        $this->assertFalse($this->antispam()->rejects($this->submission([
            'message' => 'Bitte antworten Sie an praxis@example.de, danke.',
        ])));
    }

    public function test_a_website_field_can_be_excepted(): void
    {
        config()->set('pecotamic.antispam.rules.links.except', ['website']);

        $this->assertFalse($this->antispam()->rejects($this->submission([
            'website' => 'https://example.com',
            'message' => 'Hier ist meine Praxisseite.',
        ])));
    }
}
