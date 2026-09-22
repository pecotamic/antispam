<?php

namespace Pecotamic\Antispam\Tags;

use Pecotamic\Antispam\Markup;
use Pecotamic\Antispam\PageState;
use Statamic\Tags\Tags;

/**
 * {{ antispam }} — optional, for two purposes.
 *
 * Passing parameters configures the frontend for this page without moving the
 * markup: {{ antispam selector="form.enquiry" }} anywhere on the page is
 * enough, and the middleware still places the markup where it belongs.
 *
 * Placing the tag with no parameters, on a site that has turned injection off,
 * renders the markup at that point instead. That is the escape hatch for
 * layouts the automatic placement does not suit — with the caveat that the
 * pixel is an <img> and therefore belongs in the body, never in a stack that
 * is rendered inside <head>.
 */
class AntispamTag extends Tags
{
    protected static $handle = 'antispam';

    /**
     * Tag parameters, mapped to the frontend options they set.
     */
    private const OVERRIDABLE = [
        'selector' => 'selector',
        'error_selector' => 'errorSelector',
        'consent_field' => 'consentField',
        'event_name' => 'eventName',
        'success_class' => 'successClass',
        'failure_class' => 'failureClass',
    ];

    public function index(): string
    {
        $state = app(PageState::class);

        $state->configure($this->options());

        // With injection on, the middleware places the markup; the tag only
        // had configuration to contribute. Rendering here as well would emit
        // it twice.
        if (config('pecotamic.antispam.inject', true)) {
            return '';
        }

        return $state->claimMarkup() ? app(Markup::class)->html() : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $options = [];

        foreach (self::OVERRIDABLE as $param => $key) {
            if ($this->params->has($param)) {
                $options[$key] = $this->params->get($param);
            }
        }

        // The frontend takes the two status classes as one object; the tag
        // takes them separately, because a tag parameter cannot be one.
        foreach (['successClass' => 'success', 'failureClass' => 'failure'] as $from => $to) {
            if (isset($options[$from])) {
                $options['statusClasses'][$to] = $options[$from];
                unset($options[$from]);
            }
        }

        return $options;
    }
}
