<?php

namespace Pecotamic\Antispam;

/**
 * The markup a protected form page needs: the tracking pixel and the frontend
 * module, with the configuration the frontend runs on.
 *
 * Kept apart from the middleware that places it so that what is rendered and
 * where it goes can be reasoned about — and tested — separately.
 */
class Markup
{
    public function __construct(
        private readonly Assets $assets,
        private readonly PageState $state,
    ) {
    }

    public function html(): string
    {
        return $this->pixel().$this->script();
    }

    private function pixel(): string
    {
        if (!$this->weighs('pixel')) {
            return '';
        }

        // Inline styles rather than a class: the addon cannot know a site's
        // stylesheets, and the image must not disturb the layout it lands in.
        return sprintf(
            '<img src="%s" alt="" width="1" height="1" aria-hidden="true" '
            .'style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none">',
            e(route('pecotamic.antispam.pixel'))
        );
    }

    private function script(): string
    {
        return sprintf(
            '<script type="module" src="%s" data-pecotamic-antispam="%s"></script>',
            e(route('pecotamic.antispam.asset', [
                'fingerprint' => $this->assets->fingerprint(),
                'file' => $this->assets->entry(),
            ])),
            e(json_encode($this->config(), JSON_THROW_ON_ERROR))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        // The frontend must ask for its proof under the name and at the address
        // the server reads it from, so both come from the config rather than
        // being restated on the other side.
        return array_merge($this->state->options(), [
            'proof' => $this->weighs('interaction')
                ? [
                    'endpoint' => route('pecotamic.antispam.proof'),
                    'field' => config('pecotamic.antispam.rules.interaction.field', 'ptas_proof'),
                ]
                : false,
        ]);
    }

    private function weighs(string $rule): bool
    {
        return (int) config("pecotamic.antispam.rules.{$rule}.weight", 0) > 0;
    }
}
