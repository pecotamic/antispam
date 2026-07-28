<?php

namespace Pecotamic\Antispam;

use Pecotamic\Antispam\Http\Middleware\IssueFormTimingCookie;
use Pecotamic\Antispam\Listeners\RejectSpamSubmission;
use Statamic\Events\FormSubmitted;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $listen = [
        FormSubmitted::class => [
            RejectSpamSubmission::class,
        ],
    ];

    protected $middlewareGroups = [
        'web' => [
            IssueFormTimingCookie::class,
        ],
    ];

    protected function bootConfig(): self
    {
        $this->mergeConfigFrom(__DIR__.'/../config/antispam.php', 'pecotamic.antispam');

        $this->publishes([
            __DIR__.'/../config/antispam.php' => config_path('pecotamic/antispam.php'),
        ], 'pecotamic-antispam-config');

        return $this;
    }
}
