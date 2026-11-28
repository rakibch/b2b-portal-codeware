import { describe, expect, it } from 'vitest'
import en from '@/i18n/locales/en.json'
import bn from '@/i18n/locales/bn.json'

function keys(obj, prefix = '') {
  return Object.entries(obj).flatMap(([k, v]) => (typeof v === 'object' ? keys(v, `${prefix}${k}.`) : [`${prefix}${k}`]))
}

function placeholders(str) {
  return [...new Set([...str.matchAll(/\{(\w+)\}/g)].map((m) => m[1]))].sort()
}

describe('locale dictionaries', () => {
  it('have identical key sets', () => {
    expect(keys(bn).sort()).toEqual(keys(en).sort())
  })

  it('use the same placeholders in both languages', () => {
    const get = (obj, path) => path.split('.').reduce((o, k) => o[k], obj)
    for (const key of keys(en)) {
      expect(placeholders(get(bn, key)), key).toEqual(placeholders(get(en, key)))
    }
  })

  it('contain no empty strings', () => {
    const get = (obj, path) => path.split('.').reduce((o, k) => o[k], obj)
    for (const key of keys(en)) {
      expect(get(en, key).trim(), key).not.toBe('')
      expect(get(bn, key).trim(), key).not.toBe('')
    }
  })
})
