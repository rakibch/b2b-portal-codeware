import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { i18n } from '@/i18n'
import LocaleSwitcher from '@/components/layout/LocaleSwitcher.vue'

describe('LocaleSwitcher', () => {
  it('toggles EN | বাং instantly and updates translations', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    i18n.global.locale.value = 'en'

    const wrapper = mount(LocaleSwitcher, { global: { plugins: [pinia, i18n] } })
    const [en, bn] = wrapper.findAll('button')

    expect(en.attributes('aria-pressed')).toBe('true')
    await bn.trigger('click')

    expect(i18n.global.locale.value).toBe('bn')
    expect(document.documentElement.lang).toBe('bn')
    expect(i18n.global.t('nav.dashboard')).toBe('ড্যাশবোর্ড')
    expect(bn.attributes('aria-pressed')).toBe('true')
  })
})
