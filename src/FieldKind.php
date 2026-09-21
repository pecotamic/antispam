<?php

namespace Pecotamic\Antispam;

/**
 * What a form field is for, as far as the content rules are concerned.
 *
 * Derived from the blueprint rather than configured per site: a field that is
 * declared a telephone number says so itself, and no list of field names has
 * to be kept in step with it across projects.
 */
enum FieldKind
{
    /** Free text a person wrote: the material the content rules judge. */
    case Prose;

    case Email;
    case Url;

    /** Telephone, number, date — no prose to find here. */
    case Numeric;

    /** Fixed options, uploads and the like: never free text. */
    case Other;
}
