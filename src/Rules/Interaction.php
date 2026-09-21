<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\Candidate;
use Pecotamic\Antispam\SignedTimestamp;

/**
 * Requires evidence that a human interacted with the form.
 *
 * The frontend fetches a proof on the first mouse move, touch, key press or
 * focus inside the form, and sends it along. Unlike a timing cookie — which
 * any bare GET collects — a proof costs an extra round trip that a blind POST
 * never makes.
 *
 * The proof is read from the request rather than the submission: Statamic
 * filters submitted values to the blueprint, so a field that is not part of
 * the form never reaches the submission.
 *
 * The default weight sits below the threshold on purpose. Forms still submit
 * without JavaScript, and a visitor who has it disabled should not be turned
 * away on that alone — but a missing proof combined with any second indicator
 * is enough.
 */
class Interaction extends Rule
{
    public function __construct(private readonly SignedTimestamp $timestamp)
    {
    }

    public function handle(): string
    {
        return 'interaction';
    }

    public function detects(Candidate $candidate): ?string
    {
        $field = (string) $this->option('field', 'ptas_proof');
        $age = $this->timestamp->age($candidate->request->input($field));

        if ($age === null) {
            return 'no interaction proof';
        }

        $minimum = (int) $this->option('minimum_fill_time', 3);
        $maximum = (int) $this->option('maximum_fill_time', 7200);

        if ($age < $minimum) {
            return "submitted {$age}s after first interaction, faster than the {$minimum}s minimum";
        }

        if ($age > $maximum) {
            return "interaction proof is {$age}s old, beyond the {$maximum}s maximum";
        }

        return null;
    }
}
