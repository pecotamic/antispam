<?php

namespace Pecotamic\Antispam;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Form;

/**
 * What to tell the site's maintainer after composer install or update.
 *
 * Statamic's install command runs on every composer install and update via the
 * post-autoload-dump script, and hands us its console, so this is the one
 * moment the addon can reach a human directly.
 *
 * It says nothing when there is nothing to say. A notice printed on every
 * update is a notice nobody reads by the third time.
 */
class InstallNotice
{
    public function __construct(
        private readonly Honeypot $honeypot,
        private readonly ProtectedForms $forms,
    ) {
    }

    public function printTo(Command $command): void
    {
        $problems = array_filter([
            $this->autofilledHoneypots(),
            $this->missingTag(),
            $this->outdatedConfig(),
        ]);

        if ($problems === []) {
            return;
        }

        $command->newLine();
        $command->getOutput()->writeln('  <bg=yellow;fg=black> Pecotamic Antispam </>');

        foreach ($problems as $problem) {
            $command->newLine();
            $command->getOutput()->writeln($problem);
        }

        $command->newLine();
    }

    /**
     * With automatic placement off, the markup is the site's responsibility —
     * and without it the pixel and interaction rules have nothing to judge.
     * Nothing is wrongly rejected, but that part of the protection is idle.
     */
    /**
     * A honeypot named after a real field is filled in by the browser, not by
     * a bot — and the visitor's enquiry is discarded without a trace. Worth
     * saying out loud, because nothing else about it is visible.
     */
    private function autofilledHoneypots(): ?string
    {
        $risky = collect(Form::all())
            ->filter(fn ($form) => $this->forms->includes($form->handle()))
            ->mapWithKeys(fn ($form) => [$form->handle() => $form->honeypot()])
            ->filter(fn (string $name) => $this->honeypot->isAutofilled($name));

        if ($risky->isEmpty()) {
            return null;
        }

        return implode(PHP_EOL, [
            '  <fg=yellow>These forms have a honeypot that browsers autofill:</>',
            '',
            ...$risky->map(fn (string $name, string $handle) => "      {$handle}: <fg=red>{$name}</>")->values()->all(),
            '',
            '  A visitor whose browser fills it in has their enquiry discarded,',
            '  silently — they are shown a success message either way. Rename it',
            '  in resources/forms/<handle>.yaml to something no browser knows,',
            '  for instance:',
            '',
            '      <fg=green>honeypot: contact_ref</>',
            '',
            '  Remember to rename the input in your template too, unless the',
            '  addon renders it for you.',
        ]);
    }

    private function missingTag(): ?string
    {
        if (config('pecotamic.antispam.inject', true)) {
            return null;
        }

        if (!$this->weighs('pixel') && !$this->weighs('interaction')) {
            return null;
        }

        if ($this->viewsContainTag()) {
            return null;
        }

        return implode(PHP_EOL, [
            '  <fg=yellow>Automatic placement is off and no template uses the tag,</>',
            '  <fg=yellow>so the pixel and interaction checks have nothing to judge.</>',
            '',
            '  Either set "inject" back to true, or place the tag inside the',
            '  body of your form template — never in a stack rendered in <head>,',
            '  as the pixel is an <img>:',
            '',
            '      <fg=green>{{ antispam }}</>',
        ]);
    }

    /**
     * Version 0.2 moved the per-rule settings under a "rules" key. A published
     * config from before that keeps its old keys, which are now read by
     * nothing — patterns in particular would stop matching without a word.
     */
    private function outdatedConfig(): ?string
    {
        $path = config_path('pecotamic/antispam.php');

        if (!File::exists($path)) {
            return null;
        }

        $published = array_keys((array) require $path);
        $outdated = array_intersect($published, ['patterns', 'minimum_fill_time', 'maximum_fill_time']);

        if ($outdated === []) {
            return null;
        }

        return implode(PHP_EOL, [
            '  <fg=yellow>Your published config uses keys from before 0.2:</> '.implode(', ', $outdated),
            '  They are no longer read. The settings now live under "rules",',
            '  e.g. rules.patterns.expressions and rules.timing.minimum_fill_time.',
            '',
            '  Compare against the current config and move your values over:',
            '      <fg=green>php artisan vendor:publish --tag=pecotamic-antispam-config --force</>',
        ]);
    }

    private function viewsContainTag(): bool
    {
        $path = resource_path('views');

        if (!File::isDirectory($path)) {
            return false;
        }

        foreach (File::allFiles($path) as $file) {
            if (str_contains($file->getContents(), '{{ antispam')) {
                return true;
            }
        }

        return false;
    }

    private function weighs(string $rule): bool
    {
        return (int) config("pecotamic.antispam.rules.{$rule}.weight", 0) > 0;
    }
}
