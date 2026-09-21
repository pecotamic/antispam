<?php

namespace Pecotamic\Antispam;

use Pecotamic\Antispam\Http\Middleware\IssueFormTimingCookie;
use Pecotamic\Antispam\Listeners\RejectSpamSubmission;
use Pecotamic\Antispam\Rules\Duplicate;
use Pecotamic\Antispam\Rules\Email;
use Pecotamic\Antispam\Rules\Gibberish;
use Pecotamic\Antispam\Rules\Links;
use Pecotamic\Antispam\Rules\Patterns;
use Pecotamic\Antispam\Rules\RateLimit;
use Pecotamic\Antispam\Rules\Rule;
use Pecotamic\Antispam\Rules\Script;
use Pecotamic\Antispam\Rules\Shouting;
use Pecotamic\Antispam\Rules\Timing;
use Statamic\Events\FormSubmitted;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    /**
     * The rules ship with the addon rather than being listed in each site's
     * config: sites tune weights and thresholds, they do not assemble rule
     * sets. A weight of 0 switches a rule off.
     *
     * @var array<class-string<Rule>>
     */
    private const RULES = [
        Timing::class,
        RateLimit::class,
        Duplicate::class,
        Patterns::class,
        Links::class,
        Script::class,
        Gibberish::class,
        Shouting::class,
        Email::class,
    ];

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

    public function register()
    {
        parent::register();

        $this->app->bind(Antispam::class, fn ($app) => new Antispam(
            array_map(fn (string $rule) => $app->make($rule), self::RULES),
            $app['request']
        ));
    }

    protected function bootConfig(): self
    {
        $this->mergeConfigFrom(__DIR__.'/../config/antispam.php', 'pecotamic.antispam');

        $this->publishes([
            __DIR__.'/../config/antispam.php' => config_path('pecotamic/antispam.php'),
        ], 'pecotamic-antispam-config');

        return $this;
    }
}
