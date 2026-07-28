<?php

namespace Pecotamic\Antispam\Listeners;

use Pecotamic\Antispam\Antispam;
use Statamic\Events\FormSubmitted;

class RejectSpamSubmission
{
    public function __construct(private Antispam $antispam)
    {
    }

    public function handle(FormSubmitted $event): ?bool
    {
        return $this->antispam->rejects($event->submission) ? false : null;
    }
}
