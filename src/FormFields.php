<?php

namespace Pecotamic\Antispam;

use Statamic\Contracts\Forms\Form;

/**
 * Classifies a form's fields by what they hold.
 */
class FormFields
{
    /**
     * Blueprint field types that never carry prose.
     */
    private const OTHER_TYPES = [
        'checkboxes', 'select', 'radio', 'toggle', 'button_group',
        'assets', 'files', 'range', 'time',
    ];

    private const NUMERIC_TYPES = ['integer', 'float', 'date', 'range'];

    private const INPUT_TYPES = [
        'email' => FieldKind::Email,
        'url' => FieldKind::Url,
        'tel' => FieldKind::Numeric,
        'number' => FieldKind::Numeric,
        'date' => FieldKind::Numeric,
    ];

    /**
     * @param array<string, array<string, mixed>> $fields handle => field config
     */
    public function __construct(private readonly array $fields)
    {
    }

    public static function of(Form $form): self
    {
        try {
            $fields = $form->blueprint()->fields()->all()
                ->mapWithKeys(fn ($field) => [$field->handle() => $field->config()])
                ->all();
        } catch (\Throwable) {
            // A form without a readable blueprint tells us nothing, and the
            // rules should fall back to judging everything rather than
            // silently judging nothing.
            $fields = [];
        }

        return new self($fields);
    }

    public function kindOf(string $handle): FieldKind
    {
        $config = $this->fields[$handle] ?? null;

        // A field the blueprint does not describe is treated as prose: the
        // conservative choice is to examine it, not to wave it through.
        if ($config === null) {
            return FieldKind::Prose;
        }

        $type = $config['type'] ?? 'text';

        if (in_array($type, self::OTHER_TYPES, true)) {
            return FieldKind::Other;
        }

        if (in_array($type, self::NUMERIC_TYPES, true)) {
            return FieldKind::Numeric;
        }

        return self::INPUT_TYPES[$config['input_type'] ?? ''] ?? FieldKind::Prose;
    }
}
