<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\Candidate;
use Pecotamic\Antispam\ProtectionReach;

/**
 * Base for rules that demand evidence the {{ antispam }} tag produces.
 *
 * Such a rule must never object on a site that does not use the tag: the
 * evidence could not arrive there, and every genuine submission would be
 * discarded without anyone noticing.
 */
abstract class ProofRule extends Rule
{
    public function __construct(private readonly ProtectionReach $presence)
    {
    }

    abstract protected function examine(Candidate $candidate): ?string;

    public function detects(Candidate $candidate): ?string
    {
        if (!$this->presence->confirmed()) {
            return null;
        }

        return $this->examine($candidate);
    }
}
