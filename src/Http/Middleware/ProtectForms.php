<?php

namespace Pecotamic\Antispam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pecotamic\Antispam\Markup;
use Pecotamic\Antispam\PageState;
use Pecotamic\Antispam\ProtectedForms;
use Pecotamic\Antispam\TimingCookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the protection on every page that carries a Statamic form: the timing
 * cookie, and the markup the pixel and interaction rules depend on.
 *
 * The markup is placed here rather than by a tag the site has to add, for two
 * reasons.
 *
 * It removes an integration step, and with it the chance of getting it wrong:
 * the addon protects a site the moment it is installed, with no template to
 * edit. Correctness should not depend on every user of the addon reading the
 * documentation.
 *
 * And it removes a real hazard. The pixel is an <img>, which is only valid in
 * the body — placed in the head it ends the head for the HTML parser, and
 * everything after it (analytics, meta and link tags) is reparented into the
 * body. A tag placed by hand invites exactly that, especially where a site
 * already collects its scripts in a stack rendered inside <head>. Injecting
 * before </body> is correct by construction.
 *
 * Only pages carrying a form named in the "forms" configuration are touched;
 * everything else is passed through untouched. Sites that want the markup
 * elsewhere can turn injection off and use the {{ antispam }} tag instead.
 */
class ProtectForms
{
    public function __construct(
        private readonly TimingCookie $cookie,
        private readonly Markup $markup,
        private readonly PageState $state,
        private readonly ProtectedForms $forms,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$request->isMethod('GET') || !$this->carriesProtectedForm($response)) {
            return $response;
        }

        $response->headers->setCookie($this->cookie->issue($request));

        $this->inject($response);

        return $response;
    }

    private function inject(Response $response): void
    {
        // A tag on the page has already placed the markup deliberately.
        if ($this->state->markupPlaced() || !config('pecotamic.antispam.inject', true)) {
            return;
        }

        $html = (string) $response->getContent();

        // Only a complete document is safe to extend. A fragment — a partial
        // reload, an AJAX-rendered section — has no body to close.
        if (($at = strripos($html, '</body>')) === false) {
            return;
        }

        $response->setContent(
            substr($html, 0, $at).$this->markup->html().substr($html, $at)
        );
    }

    /**
     * Only pages carrying a form the addon is responsible for are touched.
     *
     * A page whose only form is left out of the "forms" configuration is none
     * of the addon's business, and its markup is left exactly as the site
     * wrote it.
     */
    private function carriesProtectedForm(Response $response): bool
    {
        if (!$response->isSuccessful()) {
            return false;
        }

        // Scanning a non-HTML body would be both pointless and costly; a JSON
        // API response can be large and will never carry a form action.
        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        $content = $response->getContent();

        return is_string($content) && $this->forms->appearIn($content);
    }
}
