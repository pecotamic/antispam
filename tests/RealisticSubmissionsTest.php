<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A corpus of submissions of the kind the protected sites actually receive.
 *
 * The content rules run on every field of every submission across many sites,
 * and a false positive is invisible — the visitor is shown a success message
 * either way. This guards the shape of real enquiries against future tightening
 * of the heuristics.
 */
class RealisticSubmissionsTest extends TestCase
{
    #[DataProvider('genuineEnquiries')]
    public function test_it_accepts_genuine_enquiries(array $submission): void
    {
        $assessment = $this->antispam()->assess($this->submission($submission));

        $this->assertFalse(
            $assessment->rejected(),
            'Wrongly rejected: '.$assessment->summary()
        );
    }

    public static function genuineEnquiries(): array
    {
        return [
            'appointment request' => [[
                'name' => 'Katharina Müller-Lüdenscheidt',
                'email' => 'k.mueller@example.de',
                'telefon' => '+49 170 1234567',
                'betreff' => 'Terminanfrage Erstberatung',
                'message' => 'Guten Tag, ich möchte einen Termin zur Erstberatung vereinbaren. '
                    .'Erreichbar bin ich werktags ab 16:00 Uhr. Mit freundlichen Grüßen',
            ]],
            'short message' => [[
                'name' => 'Jörg Schröder',
                'email' => 'joerg@example.de',
                'message' => 'Was kostet eine Beratung?',
            ]],
            'clinical terms and abbreviations' => [[
                'name' => 'Dr. med. B. Kühn, MVZ Nord GmbH',
                'betreff' => 'OP-Termin MRT/CT',
                'message' => 'Anbei die Befunde aus dem MVZ. Die OP ist für KW 47 geplant, '
                    .'CT und MRT liegen vor. Bitte um Rückruf unter 089/1234567.',
            ]],
            'address block' => [[
                'name' => 'Familie Schmitz',
                'adresse' => 'Hauptstr. 12b, 80331 München',
                'message' => 'Wir hätten gerne Informationen zu Ihren Sprechzeiten.',
            ]],
            'all caps subject only' => [[
                'name' => 'Peter Hoffmann',
                'betreff' => 'DRINGEND',
                'message' => 'Bitte melden Sie sich zeitnah bei mir, es ist eilig.',
            ]],
        ];
    }

    #[DataProvider('spam')]
    public function test_it_rejects_spam(array $submission): void
    {
        $this->assertTrue(
            $this->antispam()->rejects($this->submission($submission)),
            'Wrongly accepted'
        );
    }

    public static function spam(): array
    {
        return [
            'keyboard mash name' => [[
                'name' => 'xkcdjfhsldkfjhs',
                'message' => 'Please contact me.',
            ]],
            'shouted advert' => [[
                'name' => 'Mike',
                'message' => 'EARN MONEY FAST WITH OUR PROVEN SYSTEM CLICK THE LINK NOW',
            ]],
            'known campaign' => [[
                'message' => 'heard about this on instrumental country radio, thought I would ask.',
            ]],
        ];
    }
}
