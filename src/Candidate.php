<?php

namespace Pecotamic\Antispam;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\Contracts\Forms\Submission;

/**
 * A submission under examination, together with the request that carried it.
 */
class Candidate
{
    public function __construct(
        public readonly Submission $submission,
        public readonly Request $request,
    ) {
    }

    public function formHandle(): string
    {
        return $this->submission->form()->handle();
    }

    /**
     * Submitted scalar values keyed by field handle. Arrays (checkbox groups,
     * uploads) carry no free text and are of no interest to content rules.
     *
     * @return Collection<string, scalar>
     */
    public function values(): Collection
    {
        return $this->submission->data()->filter(fn ($value) => is_scalar($value));
    }

    /**
     * All scalar values as one block of text.
     */
    public function text(): string
    {
        return $this->values()->implode("\n");
    }
}
