<?php

namespace Pecotamic\Antispam\Tags;

use Pecotamic\Antispam\Assets;
use Pecotamic\Antispam\PageState;
use Pecotamic\Antispam\TagPresence;
use Statamic\Tags\Tags;

/**
 * {{ antispam }} — everything a protected form page needs.
 *
 * Renders the tracking pixel, the frontend module, and the configuration the
 * frontend runs on. That configuration is derived from the same PHP config the
 * server checks against, which is the point of rendering it here at all: the
 * proof field name and endpoint exist once, not once per side.
 */
class AntispamTag extends Tags
{
    protected static $handle = 'antispam';

    /**
     * Options a site may override at the tag, for the rare form that differs.
     * Anything not listed is not the template's business.
     */
    private const OVERRIDABLE = [
        'selector' => 'selector',
        'error_selector' => 'errorSelector',
        'consent_field' => 'consentField',
        'event_name' => 'eventName',
    ];

    public function index(): string
    {
        // Several forms on a page must not each render the pixel and the
        // module; the first occurrence speaks for all of them.
        if (!app(PageState::class)->claimRendering()) {
            return '';
        }

        // Tells the proof rules they may expect evidence from now on.
        app(TagPresence::class)->record();

        return $this->pixel().$this->script();
    }

    private function pixel(): string
    {
        if (!$this->weighs('pixel')) {
            return '';
        }

        return sprintf(
            '<img src="%s" alt="" width="1" height="1" aria-hidden="true" style="position:absolute;width:1px;height:1px;opacity:0">',
            e(route('pecotamic.antispam.pixel'))
        );
    }

    private function script(): string
    {
        $assets = app(Assets::class);

        return sprintf(
            '<script type="module" src="%s" data-pecotamic-antispam="%s"></script>',
            e(route('pecotamic.antispam.asset', [
                'fingerprint' => $assets->fingerprint(),
                'file' => $assets->entry(),
            ])),
            e(json_encode($this->config(), JSON_THROW_ON_ERROR))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $config = [];

        foreach (self::OVERRIDABLE as $param => $key) {
            if ($this->params->has($param)) {
                $config[$key] = $this->params->get($param);
            }
        }

        // The frontend must ask for its proof under the name and at the address
        // the server reads it from — taken from the config, never restated.
        $config['proof'] = $this->weighs('interaction')
            ? [
                'endpoint' => route('pecotamic.antispam.proof'),
                'field' => config('pecotamic.antispam.rules.interaction.field', 'ptas_proof'),
            ]
            : false;

        return $config;
    }

    /**
     * A rule switched off needs nothing rendered for it.
     */
    private function weighs(string $rule): bool
    {
        return (int) config("pecotamic.antispam.rules.{$rule}.weight", 0) > 0;
    }
}
