import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setupContactForms } from './contact-form.js'

/**
 * Mirrors the real Statamic render: inputs nested in a label, the error
 * container a sibling of that wrapper, the honeypot inside a hidden div, and
 * the consent checkbox preceded by Statamic's empty hidden input.
 */
const FIXTURE = `
    <form class="contact-form" id="contact-form-1" name="contact" action="https://example.test/!/forms/contact" method="post">
        <div>
            <div class="field hidden">
                <label><input type="text" name="firstname"></label>
                <div class="field-error"></div>
            </div>
            <div class="field">
                <label><input type="text" name="name"><span>Name</span></label>
                <div class="field-error"></div>
            </div>
            <div class="field">
                <label><input type="email" name="email" required><span>E-Mail</span></label>
                <div class="field-error"></div>
            </div>
            <div class="field">
                <label><textarea name="message" required></textarea><span>Nachricht</span></label>
                <div class="field-error"></div>
            </div>
            <div class="field">
                <fieldset>
                    <input type="hidden" name="consent[]">
                    <label><input type="checkbox" name="consent[]" value="dsgvo" required>Einwilligung</label>
                </fieldset>
                <div class="field-error"></div>
            </div>
            <button type="submit">Absenden</button>
        </div>
    </form>
`

const form = () => document.querySelector('form.contact-form')
const field = name => form().querySelector(`[name="${name}"]`)
const errorOf = name => field(name).closest('.field').querySelector('.field-error').textContent

const fillInValidly = () => {
    field('email').value = 'anna@example.de'
    field('message').value = 'Ich hätte gerne einen Termin.'
    form().querySelector('[type="checkbox"]').checked = true
}

const submit = async () => {
    form().dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
    await new Promise(resolve => setTimeout(resolve))
}

const sentBody = () => fetchMock.mock.calls.at(-1)[1].body

let fetchMock

beforeEach(() => {
    document.body.innerHTML = FIXTURE

    fetchMock = vi.fn(async url =>
        url.includes('/proof')
            ? { ok: true, json: async () => ({ proof: 'signed-proof' }) }
            : { ok: true, json: async () => ({ success: true }) }
    )
    vi.stubGlobal('fetch', fetchMock)
})

describe('honeypot', () => {
    /**
     * The regression this package exists to prevent: a client-side
     * formData.delete('firstname') once disabled honeypot protection across
     * every site without anyone noticing, because a stripped honeypot looks
     * exactly like an empty one to the server.
     */
    it('sends the honeypot field along untouched', async () => {
        setupContactForms({ proof: false })
        fillInValidly()
        await submit()

        expect(sentBody().has('firstname')).toBe(true)
    })

    it('sends the value a bot filled in, so the server can detect it', async () => {
        setupContactForms({ proof: false })
        fillInValidly()
        field('firstname').value = 'Bot McBotface'
        await submit()

        expect(sentBody().get('firstname')).toBe('Bot McBotface')
    })
})

describe('validation', () => {
    it('does not submit while required fields are empty', async () => {
        setupContactForms({ proof: false })
        await submit()

        expect(fetchMock).not.toHaveBeenCalled()
        // An empty required field prompts to fill it in; the address format
        // is only reported once there is something to judge.
        expect(errorOf('email')).toBe('Bitte ausfüllen.')
        expect(errorOf('message')).toBe('Bitte ausfüllen.')
    })

    it('reports an unticked consent box despite the hidden input', async () => {
        setupContactForms({ proof: false })
        fillInValidly()
        form().querySelector('[type="checkbox"]').checked = false
        await submit()

        expect(fetchMock).not.toHaveBeenCalled()
        expect(errorOf('consent[]')).toBe('Bitte bestätigen.')
    })

    it('reports a malformed address once one is entered', async () => {
        setupContactForms({ proof: false })
        fillInValidly()
        field('email').value = 'anna@example'
        await submit()

        expect(fetchMock).not.toHaveBeenCalled()
        expect(errorOf('email')).toBe('Bitte eine gültige E-Mail-Adresse eingeben.')
    })

    it('clears a message once the field is filled in', async () => {
        setupContactForms({ proof: false })
        await submit()
        expect(errorOf('email')).not.toBe('')

        fillInValidly()
        await submit()
        expect(errorOf('email')).toBe('')
    })

    it('accepts messages in another language', async () => {
        setupContactForms({ proof: false, messages: { required: 'Please fill in.' } })
        await submit()

        expect(errorOf('message')).toBe('Please fill in.')
    })

    /**
     * No spam heuristic runs on this side, so a name with umlauts must pass
     * without comment — the very case the old client-side check rejected.
     */
    it('does not object to a name with umlauts', async () => {
        setupContactForms({ proof: false })
        fillInValidly()
        field('name').value = 'Jörg Schröder'
        await submit()

        expect(fetchMock).toHaveBeenCalled()
        expect(errorOf('name')).toBe('')
    })
})

describe('interaction proof', () => {
    it('fetches no proof before the visitor does anything', () => {
        setupContactForms()

        expect(fetchMock).not.toHaveBeenCalled()
        expect(field('ptas_proof').value).toBe('')
    })

    it('fetches one on the first sign of a human and sends it along', async () => {
        setupContactForms()

        form().dispatchEvent(new Event('mousemove'))
        await new Promise(resolve => setTimeout(resolve))

        expect(field('ptas_proof').value).toBe('signed-proof')

        fillInValidly()
        await submit()

        expect(sentBody().get('ptas_proof')).toBe('signed-proof')
    })

    it('fetches only once however much the visitor moves', async () => {
        setupContactForms()

        form().dispatchEvent(new Event('mousemove'))
        form().dispatchEvent(new Event('keydown'))
        form().dispatchEvent(new Event('focusin'))
        await new Promise(resolve => setTimeout(resolve))

        expect(fetchMock.mock.calls.filter(([url]) => `${url}`.includes('/proof'))).toHaveLength(1)
    })

    /**
     * The proof is one weighted indicator, not a gate. A failed request must
     * cost a little protection, never the visitor's enquiry.
     */
    it('still submits when the proof request fails', async () => {
        fetchMock.mockImplementation(async url => {
            if (`${url}`.includes('/proof')) throw new Error('offline')
            return { ok: true, json: async () => ({ success: true }) }
        })

        setupContactForms()
        form().dispatchEvent(new Event('mousemove'))
        await new Promise(resolve => setTimeout(resolve))

        fillInValidly()
        await submit()

        expect(sentBody().get('ptas_proof')).toBe('')
    })
})

describe('outcome', () => {
    it('dispatches the tracking event conversion tracking listens for', async () => {
        const listener = vi.fn()
        document.addEventListener('submit-form', listener)

        setupContactForms({ proof: false })
        fillInValidly()
        await submit()

        expect(listener).toHaveBeenCalledOnce()
        expect(listener.mock.calls[0][0].detail).toMatchObject({
            id: 'contact-form-1',
            name: 'contact',
        })
    })

    it('marks the form as failed when the request fails', async () => {
        fetchMock.mockResolvedValue({ ok: false, json: async () => ({}) })

        setupContactForms({ proof: false })
        fillInValidly()
        await submit()

        expect(form().querySelector(':scope > div').classList.contains('did-fail')).toBe(true)
    })
})
