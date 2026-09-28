import { describe, it, expect } from 'vitest'
import { formatInt, formatDecimal, formatArea, formatPercent, formatDensity } from './formatters'

describe('formatters', () => {
  describe('formatInt', () => {
    it('formats integers correctly in pt-BR', () => {
      expect(formatInt(1234567)).toBe('1.234.567')
    })
    it('returns Não disponível for null', () => {
      expect(formatInt(null)).toBe('Não disponível')
    })
    it('returns 0 for zero', () => {
      expect(formatInt(0)).toBe('0')
    })
  })

  describe('formatDecimal', () => {
    it('formats decimals with 2 places in pt-BR', () => {
      expect(formatDecimal(1234.567)).toBe('1.234,57')
    })
    it('returns Não disponível for null', () => {
      expect(formatDecimal(null)).toBe('Não disponível')
    })
  })

  describe('formatArea', () => {
    it('formats area with 2 decimal places', () => {
      expect(formatArea(7067.1267819)).toBe('7.067,13')
    })
    it('returns Não disponível for null', () => {
      expect(formatArea(null)).toBe('Não disponível')
    })
  })

  describe('formatPercent', () => {
    it('formats percentages with 1 place and % sign', () => {
      expect(formatPercent(50.3349)).toBe('50,3%')
    })
    it('returns Não disponível for null', () => {
      expect(formatPercent(null)).toBe('Não disponível')
    })
    it('returns 0,0% for zero', () => {
      expect(formatPercent(0)).toBe('0,0%')
    })
  })

  describe('formatDensity', () => {
    it('formats density with unit', () => {
      expect(formatDensity(3.0414)).toBe('3,04 hab/km²')
    })
    it('returns Não disponível for null', () => {
      expect(formatDensity(null)).toBe('Não disponível')
    })
  })
})
