<?php

namespace Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Pecotamic\Antispam\Antispam;
use Pecotamic\Antispam\ServiceProvider;
use Pecotamic\Antispam\TimingCookie;
use Statamic\Contracts\Forms\Form;
use Statamic\Contracts\Forms\Submission;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('pecotamic.antispam.rules.patterns.expressions', [
            '/heard about (?:this|us) on .*\bradio\b/i',
        ]);

        // Rules that would otherwise colour every score: the stateful ones
        // remember across evaluations and would make each test depend on what
        // ran before it, and the interaction rule objects to every request
        // that carries no proof, which is all of them. Their own tests switch
        // them back on deliberately.
        $app['config']->set('pecotamic.antispam.rules.rate_limit.weight', 0);
        $app['config']->set('pecotamic.antispam.rules.duplicate.weight', 0);
        $app['config']->set('pecotamic.antispam.rules.interaction.weight', 0);
    }

    /**
     * An Antispam instance whose request carries a timing cookie of the given
     * age. Passing null omits the cookie entirely.
     */
    protected function antispam(?int $secondsAgo = 5): Antispam
    {
        $request = Request::create('/');

        if ($secondsAgo !== null) {
            $request->cookies->set(
                app(TimingCookie::class)->name(),
                Crypt::encryptString((string) now()->subSeconds($secondsAgo)->timestamp)
            );
        }

        // Resolving through the container exercises the same wiring the
        // application uses, rules included.
        $this->app->instance('request', $request);

        return $this->app->make(Antispam::class);
    }

    protected function submission(array $data = [], string $form = 'contact'): Submission
    {
        $handle = Mockery::mock(Form::class);
        $handle->shouldReceive('handle')->andReturn($form);

        $submission = Mockery::mock(Submission::class);
        $submission->shouldReceive('form')->andReturn($handle);
        $submission->shouldReceive('data')->andReturn(collect($data));

        return $submission;
    }
}
