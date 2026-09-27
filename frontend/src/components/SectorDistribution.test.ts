import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SectorDistribution from './SectorDistribution.vue'

describe('SectorDistribution.vue', () => {
  const mockDistribution = {
    total: 85,
    urban: 26,
    rural: 59,
    unclassified: 0,
    urban_pct: 30.588235,
    rural_pct: 69.411765,
    unclassified_pct: 0
  }

  it('renders all categories with formatted values', () => {
    const wrapper = mount(SectorDistribution, {
      props: { distribution: mockDistribution }
    })

    const text = wrapper.text()
    expect(text).toContain('Urbano')
    expect(text).toContain('26 setores')
    expect(text).toContain('30,59%')
    
    expect(text).toContain('Rural')
    expect(text).toContain('59 setores')
    expect(text).toContain('69,41%')
    
    expect(text).toContain('Não informada')
    expect(text).toContain('0 setores')
    expect(text).toContain('0,00%')
  })

  it('renders Indisponível when percentages are null', () => {
    const wrapper = mount(SectorDistribution, {
      props: { 
        distribution: {
          ...mockDistribution,
          urban_pct: null,
          rural_pct: null,
          unclassified_pct: null
        }
      }
    })

    expect(wrapper.text()).toContain('Indisponível')
  })
})
