<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Pecotamic\Antispam\Antispam;
use Pecotamic\Antispam\Http\Middleware\IssueFormTimingCookie;
use Statamic\Contracts\Forms\Form;
use Statamic\Contracts\Forms\Submission;
use Tests\TestCase;

class InvalidPatternCrashTest extends TestCase
{
    public function test_an_invalid_pattern_crashes_instead_of_being_ignored(): void
    {
        config()->set('pecotamic.antispam.patterns', ['no-delimiters']);

        $request = Request::create('/');
        $request->cookies->set(
            config('pecotamic.antispam.cookie.name'),
            Crypt::encryptString((string) now()->subSeconds(5)->timestamp)
        );

        $form = Mockery::mock(Form::class);
        $form->shouldReceive('handle')->andReturn('contact');
        $submission = Mockery::mock(Submission::class);
        $submission->shouldReceive('form')->andReturn($form);
        $submission->shouldReceive('data')->andReturn(collect(['message' => 'hi']));

        $this->expectException(\Throwable::class);

        (new Antispam(app(IssueFormTimingCookie::class), $request))->rejects($submission);
    }
}