/**
 * Field validation for immediate feedback while filling the form in.
 *
 * This is user experience, not protection: the server validates independently
 * and decides on its own. No spam heuristic lives on this side — one
 * maintained in two languages drifts apart, and a false positive here would
 * block a real visitor behind an error message they cannot argue with.
 */

/** @typedef {'required' | 'email' | 'confirm'} RuleName */
/** @typedef {Record<RuleName, string>} Messages */
/** @typedef {Record<string, RuleName[]>} Rules */

/**
 * Fallback only. The server renders the translated messages into the page, so
 * these are what a direct import of the module gets.
 *
 * @type {Messages}
 */
export const defaultMessages = {
    required: 'Please fill this in.',
    email: 'Please enter a valid email address.',
    confirm: 'Please confirm.',
}

/**
 * Deliberately permissive: the address only has to look plausible enough to
 * catch a typo. Whether it is deliverable is the server's business, and an
 * over-strict pattern here would reject valid addresses outright.
 */
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/

/** @type {Record<RuleName, (value: string) => boolean>} */
const checks = {
    required: value => value.trim() !== '',
    email: value => EMAIL.test(value.trim()),
    confirm: value => value !== '',
}

export class Validator {
    /**
     * @param {Rules} rules
     * @param {Messages} messages
     */
    constructor(rules, messages = defaultMessages) {
        this.rules = rules
        this.messages = messages
    }

    /**
     * @param {HTMLFormElement} form
     * @param {string} consentField
     * @returns {Record<string, string[]>} field name => messages it failed
     */
    validate(form, consentField) {
        const data = new FormData(form)

        // Statamic renders an empty hidden input alongside a checkbox group, so
        // the group always appears in FormData even when nothing is ticked.
        // Reading the checked box directly is the only honest answer.
        if (consentField && form.querySelector(`[name="${consentField}"]`)) {
            const checked = form.querySelector(`[type="checkbox"][name="${consentField}"]:checked`)
            data.set(consentField, checked instanceof HTMLInputElement ? checked.value : '')
        }

        /** @type {Record<string, string[]>} */
        const errors = {}

        for (const [field, ruleNames] of Object.entries(this.rules)) {
            const value = data.get(field)

            if (value === null || typeof value !== 'string') continue

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
 *
 * @param {HTMLFormElement} form
 * @returns {Rules}
 */
export const buildRules = form => {
    /** @type {Rules} */
    const rules = {}

    form.querySelectorAll('[name]').forEach(field => {
        if (!(field instanceof HTMLElement)) return

        /** @type {RuleName[]} */
        const names = []

        // A checkbox is confirmed rather than filled in, and deserves the
        // prompt that says so.
        if (field.hasAttribute('required')) {
            names.push(field.getAttribute('type') === 'checkbox' ? 'confirm' : 'required')
        }

        if (field.getAttribute('type') === 'email') names.push('email')

        // Accumulate rather than assign: Statamic renders a hidden input and a
        // checkbox under the same name, and only one carries the attribute.
        if (names.length) {
            const name = field.getAttribute('name') ?? ''
            rules[name] = [...new Set([...(rules[name] ?? []), ...names])]
        }
    })

    return rules
}
