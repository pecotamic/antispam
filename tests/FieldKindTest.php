<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * The content rules take their cue from the blueprint, so a telephone or URL
 * field is left alone because the form declares it as one — rather than
 * because someone remembered to list its handle in a config file.
 */
class FieldKindTest extends TestCase
{
    /**
     * People write notes into telephone fields, and they shout in them. Judged
     * as prose this would count as a shouted message.
     */
    public function test_a_note_shouted_into_a_telephone_field_is_left_alone(): void
    {
        $submission = $this->submission(
            ['telefon' => '030 1234567 — MO BIS FR AB SECHZEHN UHR ERREICHBAR'],
            fields: ['telefon' => ['type' => 'text', 'input_type' => 'tel']]
        );

        $this->assertFalse($this->antispam()->rejects($submission));
    }

    public function test_the_same_value_in_a_text_field_still_is(): void
    {
        $gibberish = ['feld' => 'qwrtzplkjhgfdsxcvbnm'];

        $this->assertTrue($this->antispam()->rejects(
            $this->submission($gibberish, fields: ['feld' => ['type' => 'text']])
        ));
    }

    public function test_a_url_field_may_hold_a_url(): void
    {
        $submission = $this->submission(
            ['website' => 'https://praxis-beispiel.de'],
            fields: ['website' => ['type' => 'text', 'input_type' => 'url']]
        );

        $this->assertFalse($this->antispam()->rejects($submission));
    }

    public function test_a_link_smuggled_into_a_message_is_still_caught(): void
    {
        $submission = $this->submission(
            ['message' => 'Mehr dazu auf https://spam.example'],
            fields: ['message' => ['type' => 'textarea']]
        );

        $this->assertTrue($this->antispam()->rejects($submission));
    }

    /**
     * An address is not written language, so the prose rules leave it alone —
     * but the email rule finds it regardless, by its shape.
     */
    public function test_an_email_field_escapes_the_prose_rules_but_not_the_email_rule(): void
    {
        $fields = ['email' => ['type' => 'text', 'input_type' => 'email']];

        // Written in capitals, as people do — prose rules would call it shouting.
        $this->assertFalse($this->antispam()->rejects(
            $this->submission(['email' => 'BESTELLUNG@GROSSHANDEL-NORD.DE'], fields: $fields)
        ));

        // Vowel-starved local parts are ordinary in addresses, not in prose.
        $this->assertFalse($this->antispam()->rejects(
            $this->submission(['email' => 'schmdtkrzwrthxy@gmx.de'], fields: $fields)
        ));

        // The email rule finds it regardless, by its shape.
        $this->assertTrue($this->antispam()->rejects(
            $this->submission(['email' => 'someone@mailinator.com'], fields: $fields)
        ));
    }

    public function test_fixed_options_are_never_examined(): void
    {
        $submission = $this->submission(
            ['auswahl' => 'ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ'],
            fields: ['auswahl' => ['type' => 'select']]
        );

        $this->assertFalse($this->antispam()->rejects($submission));
    }

    /**
     * Anything the blueprint does not describe is examined. Waving a field
     * through because it is unknown would be the wrong way round.
     */
    public function test_a_field_missing_from_the_blueprint_is_still_examined(): void
    {
        $submission = $this->submission(
            ['unbekannt' => 'qwrtzplkjhgfdsxcvbnm'],
            fields: ['anderes' => ['type' => 'text']]
        );

        $this->assertTrue($this->antispam()->rejects($submission));
    }

    /**
     * Foreign scripts are judged everywhere a person typed, addresses
     * included — a cyrillic character there is as telling as in a message.
     */
    public function test_foreign_script_is_caught_even_in_a_telephone_field(): void
    {
        $submission = $this->submission(
            ['telefon' => '030 123 Телефон'],
            fields: ['telefon' => ['type' => 'text', 'input_type' => 'tel']]
        );

        $this->assertTrue($this->antispam()->rejects($submission));
    }
}
