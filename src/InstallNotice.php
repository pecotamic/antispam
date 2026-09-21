<?php

namespace Pecotamic\Antispam;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

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
    public function printTo(Command $command): void
    {
        $problems = array_filter([
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
     * The tag is what produces the evidence the proof rules ask for. Without
     * it those rules stay quiet — nothing breaks, but a good part of the
     * protection is simply not running.
     */
    private function missingTag(): ?string
    {
        if ($this->weighs('pixel') === false && $this->weighs('interaction') === false) {
            return null;
        }

        if ($this->viewsContainTag()) {
            return null;
        }

        return implode(PHP_EOL, [
            '  <fg=yellow>The pixel and interaction checks are not running.</>',
            '  They need the tag in your form template:',
            '',
            '      <fg=green>{{ antispam }}</>',
            '',
            '  Until it is there, those two rules stay quiet. The remaining',
            '  rules are unaffected, so nothing is rejected that should not be.',
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
