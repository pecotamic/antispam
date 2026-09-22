<?php

namespace Tests\Unit;

use Pecotamic\Antispam\ProtectedForms;
use Tests\TestCase;

class ProtectedFormsTest extends TestCase
{
    private function forms(): ProtectedForms
    {
        return app(ProtectedForms::class);
    }

    /**
     * An article that writes about the form endpoint is not a form page, and
     * its markup must be left exactly as its author wrote it.
     */
    public function test_prose_mentioning_the_endpoint_is_not_a_form(): void
    {
        $article = '<article><p>Statamic posts forms to /!/forms/contact — handy.</p></article>';

        $this->assertFalse($this->forms()->appearIn($article));
    }

    public function test_a_real_form_is_recognised(): void
    {
        $html = '<form method="post" action="https://site.test/!/forms/contact">';

        $this->assertTrue($this->forms()->appearIn($html));
    }

    public function test_it_recognises_a_form_whatever_the_attribute_order(): void
    {
        $html = '<form id="f" class="c" method="post" action="/!/forms/contact">';

        $this->assertSame(['contact'], $this->forms()->handlesIn($html));
    }

    public function test_it_reports_only_the_protected_handles(): void
    {
        config()->set('pecotamic.antispam.forms', ['contact']);

        $html = '<form action="/!/forms/brustrechner"></form><form action="/!/forms/contact"></form>';

        $this->assertSame(['contact'], $this->forms()->handlesIn($html));
    }

    /**
     * The frontend attaches by form action, not by a class name the addon
     * could not know on a site it has never seen.
     */
    public function test_the_wildcard_selector_matches_any_statamic_form(): void
    {
        config()->set('pecotamic.antispam.forms', ['*']);

        $this->assertSame('form[action*="/!/forms/"]', $this->forms()->selector());
    }

    public function test_a_named_selector_matches_the_end_of_the_action(): void
    {
        config()->set('pecotamic.antispam.forms', ['contact', 'newsletter']);

        $this->assertSame(
            'form[action$="/!/forms/contact"], form[action$="/!/forms/newsletter"]',
            $this->forms()->selector()
        );
    }

    /**
     * Matching any part of the action would let "contact" also catch
     * "contact_compact", protecting a form the site left out.
     */
    public function test_the_selector_does_not_catch_a_longer_handle(): void
    {
        config()->set('pecotamic.antispam.forms', ['contact']);

        $this->assertStringNotContainsString('action*=', $this->forms()->selector());
    }
}
