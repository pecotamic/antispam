<?php

namespace Pecotamic\Antispam;

use Illuminate\Http\Request;
use Pecotamic\Antispam\Http\Middleware\IssueFormTimingCookie;
use Statamic\Contracts\Forms\Submission;

class Antispam
{
    public function __construct(
        private IssueFormTimingCookie $timing,
        private Request $request,
    ) {
    }

    public function rejects(Submission $submission): bool
    {
        if (!$this->protects($submission->form()->handle())) {
            return false;
        }

        if (!$this->timing->isPlausible(
            $this->request->cookie($this->timing->cookieName())
        )) {
            return true;
        }

        $content = $submission->data()
            ->filter(fn($value) => is_scalar($value))
            ->implode("\n");

        return collect(config('pecotamic.antispam.patterns', []))
            ->contains(fn($pattern) => preg_match($pattern, $content) === 1);
    }

    private function protects(string $handle): bool
    {
        $forms = config('pecotamic.antispam.forms', ['*']);

        return in_array('*', $forms, true) || in_array($handle, $forms, true);
    }
}
