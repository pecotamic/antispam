<?php

namespace Pecotamic\Antispam;

use Illuminate\Http\Request;
use Pecotamic\Antispam\Rules\Rule;
use Statamic\Contracts\Forms\Submission;

class Antispam
{
    /**
     * @param iterable<Rule> $rules
     */
    public function __construct(
        private readonly iterable $rules,
        private readonly Request $request,
        private readonly ProtectedForms $forms,
    ) {
    }

    public function rejects(Submission $submission): bool
    {
        return $this->assess($submission)->rejected();
    }

    public function assess(Submission $submission): Assessment
    {
        $candidate = new Candidate($submission, $this->request);
        $threshold = (int) config('pecotamic.antispam.threshold', 100);

        if (!$this->forms->includes($candidate->formHandle())) {
            return new Assessment([], 0, $threshold);
        }

        $reasons = [];
        $score = 0;

        foreach ($this->rules as $rule) {
            if ($rule->weight() === 0) {
                continue;
            }

            if ($reason = $rule->detects($candidate)) {
                $reasons[$rule->handle()] = $reason;
                $score += $rule->weight();
            }
        }

        return new Assessment($reasons, $score, $threshold);
    }
}
