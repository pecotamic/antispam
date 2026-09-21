import { buildRules, defaultMessages, Validator } from './validator.js'

/**
 * @typedef {object} Options
 * @property {string} [selector] Forms to attach to.
 * @property {string} [errorSelector] Error container within a field's wrapper.
 * @property {string|null} [consentField] Consent checkbox group, null when none.
 * @property {{success: string, failure: string}} [statusClasses]
 * @property {Partial<import('./validator.js').Messages>} [messages]
 * @property {{endpoint: string, field: string}|false} [proof]
 * @property {string} [eventName] Event conversion tracking listens for.
 */

/** @type {Required<Options>} */
const defaults = {
    selector: 'form.contact-form',
    errorSelector: '.field-error',
    consentField: 'consent[]',
    statusClasses: { success: 'did-succeed', failure: 'did-fail' },
    messages: defaultMessages,
    proof: { endpoint: '/!/pecotamic-antispam/proof', field: 'ptas_proof' },
    eventName: 'submit-form',
}

/**
 * Fetches an interaction proof once, on the first sign of a human, and carries
 * it in a hidden field.
 *
 * The field is created here rather than rendered by the template, so the proof
 * costs no markup change in the sites that adopt it.
 *
 * A failed request is swallowed: the proof is one indicator among several and
 * weighted below the rejection threshold, so a hiccup costs a little spam
 * protection rather than the visitor's enquiry.
 *
 * @param {HTMLFormElement} form
 * @param {{endpoint: string, field: string}} config
 */
const attachProof = (form, { endpoint, field }) => {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = field
    form.append(input)

    const fetchProof = async () => {
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } })
            if (response.ok) input.value = (await response.json()).proof ?? ''
        } catch {
            // see above
        }
    }

    // { once: true } would only be once *per event type*, and a visitor who
    // moves, focuses and types would fetch a proof three times over.
    const events = ['mousemove', 'touchstart', 'keydown', 'focusin']

    const fetchOnce = () => {
        events.forEach(event => form.removeEventListener(event, fetchOnce))
        void fetchProof()
    }

    events.forEach(event => form.addEventListener(event, fetchOnce, { passive: true }))
}

/**
 * @param {Options} [options]
 */
export const setupContactForms = (options = {}) => {
    const config = { ...defaults, ...options }
    const messages = { ...defaultMessages, ...options.messages }

    document.querySelectorAll(config.selector).forEach(form => {
        if (!(form instanceof HTMLFormElement)) return

        const validator = new Validator(buildRules(form), messages)

        if (config.proof) attachProof(form, config.proof)

        /** @param {boolean} succeeded */
        const setStatus = succeeded => {
            const classes = form.querySelector(':scope > div')?.classList
            classes?.toggle(config.statusClasses.success, succeeded)
            classes?.toggle(config.statusClasses.failure, !succeeded)
            form.scrollIntoView()
        }

        /** @param {Record<string, string[]>} errors */
        const showErrors = errors => {
            // Iterating the containers rather than the errors also clears
            // messages from fields that have since been filled in.
            form.querySelectorAll(config.errorSelector).forEach(container => {
                const name = container.parentElement
                    ?.querySelector('[name]')
                    ?.getAttribute('name')

                container.textContent = name ? (errors[name]?.[0] ?? '') : ''
            })
        }

        /** @param {boolean} submitting */
        const setSubmitting = submitting => {
            const button = form.querySelector('[type="submit"]')
            if (button instanceof HTMLButtonElement) button.disabled = submitting
        }

        form.addEventListener('submit', async event => {
            const errors = validator.validate(form, config.consentField ?? '')
            const hasErrors = Object.keys(errors).length > 0

            // The honeypot travels with the payload untouched. Stripping it
            // client-side would leave the server nothing to detect and silently
            // disable the protection.
            const data = new FormData(form)

            // Without a redirect target the form is ours to send; with one, the
            // browser's own submission does the work.
            if (!hasErrors && data.get('_redirect')) {
                setSubmitting(true)
                return
            }

            event.preventDefault()
            showErrors(errors)
            setStatus(!hasErrors)

            if (hasErrors) return

            setSubmitting(true)

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: data,
                })
                setStatus(response.ok)
            } catch {
                setStatus(false)
            } finally {
                setSubmitting(false)
            }

            document.dispatchEvent(new CustomEvent(config.eventName, {
                detail: { action: form.action, id: form.id, name: form.getAttribute('name') },
            }))
        })
    })
}

/**
 * Configuration is rendered by the Antlers tag, so the field name and endpoint
 * come from the same PHP config the server checks against — there is no second
 * copy of them here to drift out of step.
 */
const script = document.querySelector('script[data-pecotamic-antispam]')

if (script instanceof HTMLElement) {
    setupContactForms(JSON.parse(script.dataset.pecotamicAntispam || '{}'))
}
