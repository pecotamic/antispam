<?php

namespace Pecotamic\Antispam;

use Illuminate\Support\Str;
use Statamic\Facades\Form;

/**
 * The decoy field Statamic checks.
 *
 * Statamic names the field and discards any submission that fills it, but it
 * does not render it — it only exposes the name as {{ honeypot }}, leaving each
 * template to build the input. So every site builds it again, and a mistake
 * there is invisible: a honeypot that is never rendered silently protects
 * nothing, and one the browser autofills silently discards real enquiries.
 *
 * This renders it instead, hidden properly and named by the form.
 */
class Honeypot
{
    /**
     * Names browsers recognise and autofill. A honeypot called any of these
     * gets filled in for the visitor, and their enquiry is thrown away.
     */
    private const AUTOFILLED = [
        'name', 'firstname', 'first_name', 'first-name', 'fname', 'givenname', 'given_name',
        'lastname', 'last_name', 'last-name', 'lname', 'surname', 'familyname', 'family_name',
        'email', 'e_mail', 'mail', 'username', 'user', 'tel', 'telephone', 'phone', 'mobile',
        'company', 'organization', 'organisation', 'address', 'street', 'city', 'town',
        'zip', 'zipcode', 'postcode', 'postal_code', 'country', 'state', 'password',
    ];

    public function nameFor(string $handle): ?string
    {
        return Form::find($handle)?->honeypot();
    }

    /**
     * The input, hidden from sight, from the tab order and from assistive
     * technology — a decoy nobody should be able to reach by accident.
     *
     * Moved off-screen rather than display:none, which is the trade-off this
     * field lives on. A bot that checks whether a field is displayed will skip
     * a display:none one, and the whole point is that it fills this in; but an
     * off-screen field is likelier to catch the browser's own autofill. The
     * answer to that is the field's name, not its hiding place — hence the
     * install-time warning about names browsers recognise, and
     * autocomplete="off" as a second line of defence that browsers disregard
     * often enough not to rely on.
     */
    public function field(string $name): string
    {
        return sprintf(
            '<div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">'
            .'<input type="text" name="%s" value="" tabindex="-1" autocomplete="off">'
            .'</div>',
            e($name)
        );
    }

    /**
     * Whether the given markup already carries the field.
     *
     * Checked per form, so that a site which renders its own honeypot keeps it
     * and gets no second one — which is what lets this be on by default.
     */
    public function presentIn(string $html, string $name): bool
    {
        return (bool) preg_match(
            '/\bname\s*=\s*["\']?'.preg_quote($name, '/').'["\'\s\/>]/i',
            $html
        );
    }

    /**
     * Whether a name is one browsers fill in by themselves.
     */
    public function isAutofilled(string $name): bool
    {
        $normalised = Str::lower(preg_replace('/[^a-z_-]/i', '', $name));

        return in_array($normalised, self::AUTOFILLED, true);
    }

    public function enabled(): bool
    {
        return (bool) config('pecotamic.antispam.honeypot', true);
    }
}
