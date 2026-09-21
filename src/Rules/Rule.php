<?php

namespace Pecotamic\Antispam\Rules;

use Pecotamic\Antispam\Candidate;

/**
 * A single spam indicator.
 *
 * A rule only decides whether it objects and why; how much that objection
 * counts is configuration, not the rule's business. This keeps sites free of
 * project-specific rule code — they tune weights instead.
 */
abstract class Rule
{
    /**
     * The config key this rule reads its options from.
     */
    abstract public function handle(): string;

    /**
     * A human-readable reason when the rule objects, null when it does not.
     */
    abstract public function detects(Candidate $candidate): ?string;

    /**
     * How much an objection contributes to the total score. A weight of 0
     * disables the rule.
     */
    public function weight(): int
    {
        return (int) $this->option('weight', 100);
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return config("pecotamic.antispam.rules.{$this->handle()}.{$key}", $default);
    }
}
