<?php

namespace Pecotamic\Antispam\Listeners;

use Illuminate\Support\Facades\Log;
use Pecotamic\Antispam\Antispam;
use Pecotamic\Antispam\Assessment;
use Statamic\Events\FormSubmitted;

class RejectSpamSubmission
{
    public function __construct(private readonly Antispam $antispam)
    {
    }

    public function handle(FormSubmitted $event): ?bool
    {
        $assessment = $this->antispam->assess($event->submission);

        $this->log($event, $assessment);

        // Returning false discards the submission while the bot still receives
        // the regular success response.
        return $assessment->rejected() ? false : null;
    }

    private function log(FormSubmitted $event, Assessment $assessment): void
    {
        $mode = config('pecotamic.antispam.log', 'rejected');

        // 'scored' also logs accepted submissions that any rule objected to.
        // Those near misses are what a threshold can be tuned against.
        $shouldLog = match ($mode) {
            'rejected' => $assessment->rejected(),
            'scored' => $assessment->reasons() !== [],
            default => false,
        };

        if (!$shouldLog) {
            return;
        }

        Log::channel(config('pecotamic.antispam.log_channel') ?: config('logging.default'))->info(
            sprintf(
                'Antispam %s submission on form "%s" (score %d/%d): %s',
                $assessment->rejected() ? 'rejected' : 'accepted',
                $event->submission->form()->handle(),
                $assessment->score(),
                $assessment->threshold(),
                $assessment->summary()
            )
        );
    }
}
