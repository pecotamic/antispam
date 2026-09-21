<?php

namespace Pecotamic\Antispam\Http\Controllers;

use Illuminate\Http\Response;
use Pecotamic\Antispam\Assets;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetController
{
    /**
     * Serves a frontend module.
     *
     * PHP handing out a static file is a fair trade for what it removes: no
     * publish step, no build artefact in the repository, nothing to copy into
     * a site's public directory on deploy. The fingerprint in the path makes
     * the response immutable, so each visitor fetches it once per release.
     */
    public function __invoke(Assets $assets, string $fingerprint, string $file): Response
    {
        if (!$path = $assets->path($file)) {
            throw new NotFoundHttpException;
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => $fingerprint === $assets->fingerprint()
                ? 'public, max-age=31536000, immutable'
                : 'public, max-age=60',
        ]);
    }
}
