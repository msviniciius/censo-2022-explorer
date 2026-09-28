import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SexDistribution from './SexDistribution.vue'

describe('SexDistribution.vue', () => {
  const completeMetric = (val: number) => ({
    valor: val,
    percentual: 50,
    setores_com_valor: 10,
    total_setores: 10,
    estado: 'completo' as const
  })

  const partialMetric = (val: number) => ({
    valor: val,
    percentual: 50,
    setores_com_valor: 5,
    total_setores: 10,
    estado: 'parcial' as const
  })

  it('renders complete distribution', () => {
    const wrapper = mount(SexDistribution, {
      props: {
        homens: completeMetric(1000),
        mulheres: completeMetric(1000)
      }
    })

    expect(wrapper.text()).toContain('Homens')
    expect(wrapper.text()).toContain('1.000 residentes')
    expect(wrapper.text()).toContain('50,0%')
    expect(wrapper.find('.text-amber-700').exists()).toBe(false)
    expect(wrapper.find('.text-red-700').exists()).toBe(false)
  })

  it('shows warning for partial coverage', () => {
    const wrapper = mount(SexDistribution, {
      props: {
        homens: partialMetric(500),
        mulheres: completeMetric(1000)
      }
    })

    expect(wrapper.find('.text-amber-700').exists()).toBe(true)
    expect(wrapper.text()).toContain('Atenção: A cobertura demográfica é parcial para homens ou mulheres.')
  })

  it('shows warning for unavailable coverage', () => {
    const wrapper = mount(SexDistribution, {
      props: {
        homens: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 10, estado: 'indisponivel' as const },
        mulheres: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 10, estado: 'indisponivel' as const }
      }
    })

    expect(wrapper.find('.text-red-700').exists()).toBe(true)
    expect(wrapper.text()).toContain('Atenção: Dados demográficos não disponíveis para este território.')
  })

  it('renders "Não disponível" for null values without attaching a unit', () => {
    const wrapper = mount(SexDistribution, {
      props: {
        homens: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 10, estado: 'indisponivel' as const },
        mulheres: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 10, estado: 'indisponivel' as const }
      }
    })

    expect(wrapper.text()).toContain('Não disponível')
    expect(wrapper.text()).not.toContain('Não disponível residentes')
  })

  it('renders 0 residentes when known value is 0 and preserves zero', () => {
    const wrapper = mount(SexDistribution, {
      props: {
        homens: { valor: 0, percentual: 0, setores_com_valor: 5, total_setores: 10, estado: 'parcial' as const },
        mulheres: { valor: 0, percentual: 0, setores_com_valor: 5, total_setores: 10, estado: 'parcial' as const }
      }
    })

    expect(wrapper.text()).toContain('0 residentes')
    expect(wrapper.text()).not.toContain('Não disponível residentes')
  })
})
