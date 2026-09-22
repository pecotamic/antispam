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
        private readonly ProtectedForms $forms,
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
        //
        // No loading="lazy" — it sits at the end of the document, and lazy
        // loading would defer it out of the viewport, possibly past the moment
        // the visitor submits. fetchpriority="low" instead: the fetch has
        // seconds to happen and has no business competing with real images.
        return sprintf(
            '<img src="%s" alt="" width="1" height="1" aria-hidden="true" '
            .'fetchpriority="low" decoding="async" '
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
        // The selector is derived from the protected form handles, so the
        // frontend attaches to exactly the forms the server judges — and does
        // so on any Statamic site, without assuming a class name the addon has
        // never seen. A site may still override it at the tag.
        //
        // The proof field name and endpoint come from the config the server
        // reads them from, rather than being restated on the other side.
        return array_merge([
            'selector' => $this->forms->selector(),
            'messages' => $this->messages(),
        ], $this->state->options(), [
            'proof' => $this->weighs('interaction')
                ? [
                    'endpoint' => route('pecotamic.antispam.proof'),
                    'field' => config('pecotamic.antispam.rules.interaction.field', 'ptas_proof'),
                ]
                : false,
        ]);
    }

    /**
     * The field messages in the site's language.
     *
     * Rendered by the server rather than carried in the module, so a site
     * translates them the way it translates anything else — and so the addon
     * is not tied to the one language its defaults happen to be written in.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return collect(['required', 'email', 'confirm'])
            ->mapWithKeys(fn (string $key) => [
                $key => (string) __("pecotamic-antispam::messages.{$key}"),
            ])
            ->all();
    }

    private function weighs(string $rule): bool
    {
        return (int) config("pecotamic.antispam.rules.{$rule}.weight", 0) > 0;
    }
}
