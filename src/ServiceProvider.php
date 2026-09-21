<?php

namespace Pecotamic\Antispam;

use Pecotamic\Antispam\Http\Middleware\IssueFormTimingCookie;
use Pecotamic\Antispam\Listeners\RejectSpamSubmission;
use Pecotamic\Antispam\Rules\Duplicate;
use Pecotamic\Antispam\Rules\Email;
use Pecotamic\Antispam\Rules\Gibberish;
use Pecotamic\Antispam\Rules\Interaction;
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
        Interaction::class,
        RateLimit::class,
        Duplicate::class,
        Patterns::class,
        Links::class,
        Script::class,
        Gibberish::class,
        Shouting::class,
        Email::class,
    ];

    protected $routes = [
        'web' => __DIR__.'/../routes/web.php',
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
        $this->mergeConfigDeeply(__DIR__.'/../config/antispam.php', 'pecotamic.antispam');

        $this->publishes([
            __DIR__.'/../config/antispam.php' => config_path('pecotamic/antispam.php'),
        ], 'pecotamic-antispam-config');

        return $this;
    }

    /**
     * Laravel's mergeConfigFrom only merges the top level, so a published
     * config that names a single rule would replace the whole "rules" array
     * and strip every other rule of its settings. Merging deeply lets a site
     * override one value and inherit the rest.
     */
    private function mergeConfigDeeply(string $path, string $key): void
    {
        $config = $this->app['config'];

        $config->set($key, $this->mergedWith(require $path, (array) $config->get($key, [])));
    }

    private function mergedWith(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            // Lists are replaced wholesale rather than merged by index:
            // overriding ['Latin', 'Common', 'Inherited'] with ['Latin'] must
            // yield one entry, not leave the other two in place.
            $defaults[$key] = is_array($value) && is_array($defaults[$key] ?? null) && !array_is_list($value)
                ? $this->mergedWith($defaults[$key], $value)
                : $value;
        }

        return $defaults;
    }
}
