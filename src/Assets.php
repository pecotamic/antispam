<?php

namespace Pecotamic\Antispam;

use Illuminate\Support\Collection;

/**
 * The frontend files the addon serves.
 *
 * They are shipped as plain JavaScript with JSDoc types rather than compiled
 * from TypeScript: there is no build step, so the file that is served is the
 * file in the repository and the two cannot fall out of step.
 */
class Assets
{
    private const DIRECTORY = __DIR__.'/../resources/js';

    /**
     * A fingerprint over the served files.
     *
     * It sits in the URL path rather than a query string so the relative
     * imports inside the modules inherit it: bumping the fingerprint busts the
     * whole module graph at once, with no import left pointing at a stale copy.
     */
    public function fingerprint(): string
    {
        static $fingerprint = null;

        return $fingerprint ??= substr(md5($this->files()->implode('')), 0, 12);
    }

    public function path(string $file): ?string
    {
        // basename() alone would still let "..%2Ffoo" through on some setups;
        // matching against the known files leaves nothing to reason about.
        return $this->names()->contains($file)
            ? self::DIRECTORY.'/'.$file
            : null;
    }

    public function entry(): string
    {
        return 'contact-form.js';
    }

    /**
     * @return Collection<int, string>
     */
    private function names(): Collection
    {
        return collect(glob(self::DIRECTORY.'/*.js') ?: [])
            ->map(fn (string $path) => basename($path))
            ->reject(fn (string $name) => str_ends_with($name, '.test.js'))
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function files(): Collection
    {
        return $this->names()->map(fn (string $name) => (string) file_get_contents(self::DIRECTORY.'/'.$name));
    }
}
