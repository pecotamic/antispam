/**
 * Field validation for immediate feedback while filling the form in.
 *
 * This is user experience, not protection: the server validates independently
 * and decides on its own. Nothing here is relied upon to keep spam out, which
 * is why no spam heuristic lives on this side — a heuristic maintained in two
 * languages drifts apart, and a false positive here would block a real visitor
 * behind an error message they cannot argue with.
 */
export type RuleName = 'required' | 'email' | 'confirm'

export type Messages = Record<RuleName, string>

export const defaultMessages: Messages = {
    required: 'Bitte ausfüllen.',
    email: 'Bitte eine gültige E-Mail-Adresse eingeben.',
    confirm: 'Bitte bestätigen.',
}

/**
 * Deliberately permissive: the address only has to look plausible enough to
 * catch a typo. Whether it is deliverable is the server's business, and an
 * over-strict pattern here would reject valid addresses outright.
 */
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/

const checks: Record<RuleName, (value: string) => boolean> = {
    required: value => value.trim() !== '',
    email: value => EMAIL.test(value.trim()),
    confirm: value => value !== '',
}

export type Rules = Record<string, RuleName[]>

export class Validator {
    constructor(
        private readonly rules: Rules,
        private readonly messages: Messages = defaultMessages,
    ) {}

    /**
     * @returns field name => the messages it failed, empty when all passed
     */
    validate(form: HTMLFormElement, consentField: string): Record<string, string[]> {
        const data = new FormData(form)

        // Statamic renders an empty hidden input alongside a checkbox group, so
        // the group always appears in FormData even when nothing is ticked.
        // Reading the checked box directly is the only honest answer.
        if (form.querySelector(`[name="${consentField}"]`)) {
            const checked = form.querySelector<HTMLInputElement>(
                `[type="checkbox"][name="${consentField}"]:checked`
            )
            data.set(consentField, checked?.value ?? '')
        }

        const errors: Record<string, string[]> = {}

        for (const [field, ruleNames] of Object.entries(this.rules)) {
            const value = data.get(field)

            if (value === null || value instanceof File) continue

            const failed = ruleNames
                .filter(rule => !checks[rule](value))
                .map(rule => this.messages[rule])

            if (failed.length) errors[field] = failed
        }

        return errors
    }
}

/**
 * Derives the rules from the markup, so a field gains validation by being
 * marked up correctly rather than by being named in a configuration file.
 */
export const buildRules = (form: HTMLFormElement): Rules => {
    const rules: Rules = {}

    form.querySelectorAll<HTMLInputElement>('[name]').forEach(field => {
        const names: RuleName[] = []

        // A checkbox is confirmed rather than filled in, and deserves the
        // prompt that says so.
        if (field.hasAttribute('required')) {
            names.push(field.getAttribute('type') === 'checkbox' ? 'confirm' : 'required')
        }
        if (field.getAttribute('type') === 'email') names.push('email')

        // Accumulate rather than assign: Statamic renders a hidden input and a
        // checkbox under the same name, and only one of them carries the
        // required attribute.
        if (names.length) {
            rules[field.name] = [...new Set([...(rules[field.name] ?? []), ...names])]
        }
    })

    return rules
}
