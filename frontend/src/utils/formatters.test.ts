import { describe, it, expect } from 'vitest'
import { formatInt, formatDecimal, formatArea, formatPercent, formatDensity } from './formatters'

describe('formatters', () => {
  describe('formatInt', () => {
    it('formats integers correctly in pt-BR', () => {
      expect(formatInt(1234567)).toBe('1.234.567')
    })
    it('returns Indisponível for null', () => {
      expect(formatInt(null)).toBe('Indisponível')
    })
    it('returns 0 for zero', () => {
      expect(formatInt(0)).toBe('0')
    })
  })

  describe('formatDecimal', () => {
    it('formats decimals with 2 places in pt-BR', () => {
      expect(formatDecimal(1234.567)).toBe('1.234,57')
    })
    it('returns Indisponível for null', () => {
      expect(formatDecimal(null)).toBe('Indisponível')
    })
  })

  describe('formatArea', () => {
    it('formats area with 4 decimal places', () => {
      expect(formatArea(7067.1267819)).toBe('7.067,1268')
    })
    it('returns Indisponível for null', () => {
      expect(formatArea(null)).toBe('Indisponível')
    })
  })

  describe('formatPercent', () => {
    it('formats percentages with 2 places and % sign', () => {
      expect(formatPercent(50.3349)).toBe('50,33%')
    })
    it('returns Indisponível for null', () => {
      expect(formatPercent(null)).toBe('Indisponível')
    })
    it('returns 0,00% for zero', () => {
      expect(formatPercent(0)).toBe('0,00%')
    })
  })

  describe('formatDensity', () => {
    it('formats density with unit', () => {
      expect(formatDensity(3.0414)).toBe('3,04 hab/km²')
    })
    it('returns Indisponível for null', () => {
      expect(formatDensity(null)).toBe('Indisponível')
    })
  })
})
