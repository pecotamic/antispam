import { buildRules, defaultMessages, Messages, Validator } from './validator'

export interface Options {
    /** Forms to attach to. */
    selector?: string
    /** Error container within a field's wrapper. */
    errorSelector?: string
    /** Name of the consent checkbox group, or null when the form has none. */
    consentField?: string | null
    /** Classes toggled on the form's first child to signal the outcome. */
    statusClasses?: { success: string; failure: string }
    /** Validation messages, for sites that are not German. */
    messages?: Partial<Messages>
    /** Interaction proof, or false to submit without one. */
    proof?: { endpoint: string; field: string } | false
    /**
     * Event dispatched on document after an AJAX submission, carrying
     * { action, id, name }. Conversion tracking listens for this, so the name
     * is part of the contract with the sites that embed the package.
     */
    eventName?: string
}

const defaults = {
    selector: 'form.contact-form',
    errorSelector: '.field-error',
    consentField: 'consent[]',
    statusClasses: { success: 'did-succeed', failure: 'did-fail' },
    proof: { endpoint: '/!/pecotamic-antispam/proof', field: 'ptas_proof' },
    eventName: 'submit-form',
} as const

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
 */
const attachProof = (form: HTMLFormElement, { endpoint, field }: { endpoint: string; field: string }) => {
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
    const events = ['mousemove', 'touchstart', 'keydown', 'focusin'] as const

    const fetchOnce = () => {
        events.forEach(event => form.removeEventListener(event, fetchOnce))
        void fetchProof()
    }

    events.forEach(event => form.addEventListener(event, fetchOnce, { passive: true }))
}

export const setupContactForms = (options: Options = {}) => {
    const config = { ...defaults, ...options }
    const messages: Messages = { ...defaultMessages, ...options.messages }

    document.querySelectorAll<HTMLFormElement>(config.selector).forEach(form => {
        const validator = new Validator(buildRules(form), messages)

        if (config.proof) attachProof(form, config.proof)

        const setStatus = (succeeded: boolean) => {
            const classes = form.querySelector(':scope > div')?.classList
            classes?.toggle(config.statusClasses.success, succeeded)
            classes?.toggle(config.statusClasses.failure, !succeeded)
            form.scrollIntoView()
        }

        const showErrors = (errors: Record<string, string[]>) => {
            // Iterating the containers rather than the errors also clears
            // messages from fields that have since been filled in.
            form.querySelectorAll(config.errorSelector).forEach(container => {
                const name = container.parentElement
                    ?.querySelector('[name]')
                    ?.getAttribute('name')

                container.textContent = name ? (errors[name]?.[0] ?? '') : ''
            })
        }

        const setSubmitting = (submitting: boolean) => {
            const button = form.querySelector<HTMLButtonElement>('[type="submit"]')
            if (button) button.disabled = submitting
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
