<?php

namespace Pecotamic\Antispam;

/**
 * The outcome of examining one submission.
 *
 * Carries the reasons alongside the verdict so a borderline decision can be
 * logged and tuned rather than guessed at.
 */
class Assessment
{
    /**
     * @param array<string, string> $reasons rule handle => why it objected
     */
    public function __construct(
        private readonly array $reasons,
        private readonly int $score,
        private readonly int $threshold,
    ) {
    }

    public function rejected(): bool
    {
        return $this->score >= $this->threshold;
    }

    public function score(): int
    {
        return $this->score;
    }

    public function threshold(): int
    {
        return $this->threshold;
    }

    /**
     * @return array<string, string>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }

    public function summary(): string
    {
        if ($this->reasons === []) {
            return 'no rule objected';
        }

        return collect($this->reasons)
            ->map(fn ($reason, $handle) => "{$handle}: {$reason}")
            ->implode('; ');
    }
}
