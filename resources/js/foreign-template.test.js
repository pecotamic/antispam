import { beforeEach, expect, it, vi } from 'vitest'
import { setupContactForms } from './contact-form.js'

/**
 * A template the addon has never seen: no error containers, no status wrapper,
 * no class names it could rely on. This is the common case on a site that
 * simply installed the addon.
 *
 * Validating here without anywhere to show the result would block the submit
 * silently — the visitor clicks Send and nothing happens at all. The browser's
 * own validation takes over instead.
 */
const FOREIGN = `
    <form action="https://x.test/!/forms/contact" method="post">
        <label>Name <input type="text" name="name" required></label>
        <label>Mail <input type="email" name="email" required></label>
        <button type="submit">Senden</button>
    </form>
`

const form = () => document.querySelector('form')
const submit = async () => {
    const event = new Event('submit', { cancelable: true, bubbles: true })
    form().dispatchEvent(event)
    await new Promise(r => setTimeout(r))
    return event
}

let fetchMock
let reported

beforeEach(() => {
    document.body.innerHTML = FOREIGN
    fetchMock = vi.fn(async () => ({ ok: true, json: async () => ({}) }))
    vi.stubGlobal('fetch', fetchMock)

    // Spying on the browser's own reporting is what distinguishes handing over
    // from blocking in silence.
    reported = vi.fn(function () { return this.checkValidity() })
    HTMLFormElement.prototype.reportValidity = reported
})

it('does not block a submit it cannot explain', async () => {
    setupContactForms({ proof: false })

    form().querySelector('[name="name"]').value = 'Anna Weber'
    form().querySelector('[name="email"]').value = 'anna@example.de'

    const event = await submit()

    expect(event.defaultPrevented).toBe(true)  // taken over for the AJAX post
    expect(fetchMock).toHaveBeenCalled()       // and actually sent
})

it('leaves an invalid form to the browser to report', async () => {
    setupContactForms({ proof: false })

    // name left empty — the browser must refuse *and say so*
    const event = await submit()

    expect(reported).toHaveBeenCalled()
    expect(event.defaultPrevented).toBe(true)
    expect(fetchMock).not.toHaveBeenCalled()
})

it('attaches by form action, needing no class name', async () => {
    setupContactForms({ proof: { endpoint: '/proof', field: 'ptas_proof' } })

    form().dispatchEvent(new Event('mousemove'))
    await new Promise(r => setTimeout(r))

    expect(form().querySelector('[name="ptas_proof"]')).not.toBeNull()
})

it('overriding one status class keeps the other', async () => {
    document.body.innerHTML = `
        <form action="https://x.test/!/forms/contact" method="post">
            <div><button type="submit">Senden</button></div>
        </form>`

    setupContactForms({ proof: false, statusClasses: { failure: 'my-error' } })

    const form = document.querySelector('form')
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
    await new Promise(r => setTimeout(r, 10))

    // The untouched default must still be applied on success.
    expect(form.querySelector(':scope > div').className).toContain('did-succeed')
})
