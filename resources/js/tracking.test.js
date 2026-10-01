import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { setupContactForms } from './contact-form.js'

/**
 * Conversion tracking listens for this event. It has to fire on both submit
 * paths — the one the module sends itself, and the one it leaves to the
 * browser when the form has a redirect target. A tracker that only heard about
 * the first would miss every submission on such a form, and miss it silently.
 */
const withRedirect = (redirect) => `
    <form id="contact-form" action="https://x.test/!/forms/contact" method="post">
        <div>
            ${redirect ? '<input type="hidden" name="_redirect" value="#contact-form">' : ''}
            <div class="field">
                <label><input type="text" name="name" required></label>
                <div class="field-error"></div>
            </div>
            <button type="submit">Senden</button>
        </div>
    </form>
`

let events
let fetchMock
let listener

beforeEach(() => {
    events = []
    listener = e => events.push(e.detail)
    document.addEventListener('submit-form', listener)
    fetchMock = vi.fn(async () => ({ ok: true, json: async () => ({}) }))
    vi.stubGlobal('fetch', fetchMock)
})

// Left in place, the listeners accumulate and every later test counts the same
// event once per test that ran before it.
afterEach(() => {
    document.removeEventListener('submit-form', listener)
})

const fillAndSubmit = async () => {
    const form = document.querySelector('form')
    form.querySelector('[name="name"]').value = 'Anna Weber'
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
    await new Promise(r => setTimeout(r, 10))
    return form
}

it('fires on the path it sends itself', async () => {
    document.body.innerHTML = withRedirect(false)
    setupContactForms({ proof: false })

    await fillAndSubmit()

    expect(fetchMock).toHaveBeenCalled()
    expect(events).toHaveLength(1)
})

it('fires on the path the browser sends', async () => {
    document.body.innerHTML = withRedirect(true)
    setupContactForms({ proof: false })

    const form = await fillAndSubmit()

    // Handed to the browser: not cancelled, nothing sent by us.
    expect(fetchMock).not.toHaveBeenCalled()
    expect(events).toHaveLength(1)
    expect(events[0]).toMatchObject({ id: 'contact-form' })
})

it('does not fire when the form was never sent', async () => {
    document.body.innerHTML = withRedirect(true)
    setupContactForms({ proof: false })

    // name left empty
    document.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
    await new Promise(r => setTimeout(r, 10))

    expect(events).toHaveLength(0)
})

it('carries the configured event name', async () => {
    document.body.innerHTML = withRedirect(true)
    const renamed = []
    const onRenamed = e => renamed.push(e.detail)
    document.addEventListener('sent', onRenamed)

    setupContactForms({ proof: false, eventName: 'sent' })
    await fillAndSubmit()

    document.removeEventListener('sent', onRenamed)

    expect(renamed).toHaveLength(1)
    expect(events).toHaveLength(0)
})
