<?php

namespace Tests;

use Pecotamic\Antispam\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('pecotamic.antispam.patterns', [
            '/heard about (?:this|us) on .*\bradio\b/i',
        ]);
    }
}