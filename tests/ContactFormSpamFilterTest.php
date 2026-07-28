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

class ContactFormSpamFilterTest extends TestCase
{
    public function test_it_silently_rejects_a_submission_without_timing_cookie(): void
    {
        $this->assertTrue($this->filter(null)->rejects($this->submission()));
    }

    public function test_it_accepts_a_normal_request_with_valid_timing_cookie(): void
    {
        $encrypted = Crypt::encryptString((string) now()->subSeconds(5)->timestamp);
        $this->assertFalse($this->filter($encrypted)->rejects($this->submission([
            'betreff' => 'Beratung',
            'message' => 'Ich interessiere mich für einen Beratungstermin.',
        ])));
    }

    public function test_it_rejects_the_observed_radio_spam_campaign(): void
    {
        $encrypted = Crypt::encryptString((string) now()->subSeconds(10)->timestamp);
        $this->assertTrue($this->filter($encrypted)->rejects($this->submission([
            'betreff' => 'Hello from Stehr, Hamill and Sauer',
            'message' => 'heard about this on instrumental country radio, decided to give it a try.',
        ])));
    }

    private function filter(?string $cookie): Antispam
    {
        $request = Request::create('/');
        $request->cookies->set(config('pecotamic.antispam.cookie.name'), $cookie);

        return new Antispam(app(IssueFormTimingCookie::class), $request);
    }

    private function submission(array $data = []): Submission
    {
        $form = Mockery::mock(Form::class);
        $form->shouldReceive('handle')->andReturn('contact');
        $submission = Mockery::mock(Submission::class);
        $submission->shouldReceive('form')->andReturn($form);
        $submission->shouldReceive('data')->andReturn(collect($data));

        return $submission;
    }
}
