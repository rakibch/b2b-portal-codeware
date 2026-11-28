import { describe, expect, it } from 'vitest'
import { formatCurrency, formatNumber } from '@/utils/format'
import { safeRedirect } from '@/utils/safeRedirect'

describe('formatCurrency', () => {
  it('uses lakh grouping in English', () => {
    expect(formatCurrency('1234567.5', 'en')).toBe('৳ 12,34,567.50')
  })

  it('uses Bangla numerals in Bangla', () => {
    expect(formatCurrency('1234567.5', 'bn')).toBe('৳ ১২,৩৪,৫৬৭.৫০')
  })

  it('handles negatives, nulls and garbage safely', () => {
    expect(formatCurrency('-500', 'en')).toBe('-৳ 500.00')
    expect(formatCurrency(null, 'en')).toBe('৳ 0.00')
    expect(formatCurrency('abc', 'en')).toBe('৳ 0.00')
  })

  it('formats integers', () => {
    expect(formatNumber(2400, 'bn')).toBe('২,৪০০')
  })
})

describe('safeRedirect', () => {
  it.each([
    ['/orders/ORD-1', '/orders/ORD-1'],
    ['//evil.example.com', '/'],
    ['https://evil.example.com', '/'],
    ['/\\evil.example.com', '/'],
    [undefined, '/'],
  ])('%s → %s', (input, expected) => {
    expect(safeRedirect(input, '/')).toBe(expected)
  })
})
