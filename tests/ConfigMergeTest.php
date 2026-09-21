<?php

namespace Tests\Unit;

use Pecotamic\Antispam\ServiceProvider;
use Statamic\Testing\AddonTestCase;

/**
 * A published config names only what a site wants to change. Laravel's
 * mergeConfigFrom merges the top level alone, so a config touching one rule
 * would replace the entire "rules" array and silently strip every other rule
 * of its settings.
 */
class ConfigMergeTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // What a site's published config looks like when it changes one value.
        $app['config']->set('pecotamic.antispam', [
            'rules' => [
                'links' => ['weight' => 0],
                'script' => ['allowed' => ['Latin']],
            ],
        ]);
    }

    public function test_an_overridden_value_wins(): void
    {
        $this->assertSame(0, config('pecotamic.antispam.rules.links.weight'));
    }

    public function test_untouched_values_of_the_same_rule_survive(): void
    {
        $this->assertSame(0, config('pecotamic.antispam.rules.links.maximum'));
    }

    public function test_untouched_rules_keep_their_settings(): void
    {
        $this->assertSame(100, config('pecotamic.antispam.rules.gibberish.weight'));
        $this->assertSame(12, config('pecotamic.antispam.rules.gibberish.minimum_length'));
        $this->assertSame(5, config('pecotamic.antispam.rules.timing.minimum_fill_time'));
    }

    public function test_untouched_top_level_keys_survive(): void
    {
        $this->assertSame(100, config('pecotamic.antispam.threshold'));
        $this->assertSame(['*'], config('pecotamic.antispam.forms'));
        $this->assertSame('_ptas', config('pecotamic.antispam.cookie.name'));
    }

    /**
     * Lists are replaced, not merged by index — otherwise narrowing the allowed
     * scripts would leave the dropped entries in place.
     */
    public function test_a_list_is_replaced_wholesale(): void
    {
        $this->assertSame(['Latin'], config('pecotamic.antispam.rules.script.allowed'));
    }
}
