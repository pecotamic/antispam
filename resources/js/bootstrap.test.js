import { beforeEach, expect, it, vi } from 'vitest'

/**
 * The module configures itself from the attribute the Antlers tag renders.
 *
 * This is what keeps the proof field name in one place: the server puts the
 * name it checks against into the page, and the frontend asks for its proof
 * under exactly that name. A second copy in the JavaScript would drift, and a
 * proof under the wrong name looks to the server like no proof at all.
 */
const FIXTURE = `
    <form class="enquiry" action="https://example.test/!/forms/contact" method="post">
        <div><button type="submit">Absenden</button></div>
    </form>
`

const withConfig = async config => {
    document.body.innerHTML = FIXTURE

    const script = document.createElement('script')
    script.setAttribute('data-pecotamic-antispam', JSON.stringify(config))
    document.body.append(script)

    vi.resetModules()
    await import('./contact-form.js')
}

let fetchMock

beforeEach(() => {
    fetchMock = vi.fn(async () => ({ ok: true, json: async () => ({ proof: 'signed-proof' }) }))
    vi.stubGlobal('fetch', fetchMock)
})

it('attaches to the selector the tag rendered', async () => {
    await withConfig({ selector: 'form.enquiry', proof: false })

    const form = document.querySelector('form.enquiry')
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
    await new Promise(resolve => setTimeout(resolve))

    expect(fetchMock).toHaveBeenCalledWith('https://example.test/!/forms/contact', expect.anything())
})

it('asks for its proof under the name the server configured', async () => {
    await withConfig({
        selector: 'form.enquiry',
        proof: { endpoint: '/!/pecotamic-antispam/proof', field: 'nachweis' },
    })

    const form = document.querySelector('form.enquiry')
    form.dispatchEvent(new Event('mousemove'))
    await new Promise(resolve => setTimeout(resolve))

    expect(form.querySelector('[name="nachweis"]').value).toBe('signed-proof')
})

it('skips the proof when the tag says the rule is off', async () => {
    await withConfig({ selector: 'form.enquiry', proof: false })

    const form = document.querySelector('form.enquiry')
    form.dispatchEvent(new Event('mousemove'))
    await new Promise(resolve => setTimeout(resolve))

    expect(fetchMock).not.toHaveBeenCalled()
    expect(form.querySelector('input[type="hidden"]')).toBeNull()
})

it('does nothing when no tag rendered a configuration', async () => {
    document.body.innerHTML = FIXTURE
    vi.resetModules()
    await import('./contact-form.js')

    const form = document.querySelector('form.enquiry')
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
    await new Promise(resolve => setTimeout(resolve))

    expect(fetchMock).not.toHaveBeenCalled()
})
