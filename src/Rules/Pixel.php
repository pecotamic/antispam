<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\Candidate;
use Pecotamic\Antispam\PixelCookie;
use Pecotamic\Antispam\ProtectionReach;

/**
 * Requires that the form page was actually loaded, subresources and all.
 *
 * The Antlers tag renders a 1×1 image whose request leaves this cookie. Most
 * form spam is a blind POST to the form action that never fetches anything,
 * so its absence is a strong signal — and unlike the JavaScript proof it
 * covers every visitor.
 *
 * Weighted below the threshold on purpose. Ad blockers occasionally swallow
 * pixel-shaped requests, and a visitor must not be turned away over one
 * missing image. Together with a missing interaction proof it is enough, and
 * either signal on its own lets a genuine visitor through.
 */
class Pixel extends ProofRule
{
    public function __construct(ProtectionReach $presence, private readonly PixelCookie $cookie)
    {
        parent::__construct($presence);
    }

    public function handle(): string
    {
        return 'pixel';
    }

    protected function examine(Candidate $candidate): ?string
    {
        $age = $this->cookie->age($candidate->request->cookie($this->cookie->name()));

        if ($age === null) {
            return 'the form page was never loaded with its subresources';
        }

        $maximum = (int) $this->option('maximum_age', 7200);

        if ($age > $maximum) {
            return "page was loaded {$age}s ago, beyond the {$maximum}s maximum";
        }

        return null;
    }
}
