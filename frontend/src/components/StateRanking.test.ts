import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StateRanking from './StateRanking.vue'
import type { RankingEntry } from '../api/types'

function entry(overrides: Partial<RankingEntry> & Pick<RankingEntry, 'posicao' | 'cd_mun'>): RankingEntry {
  return {
    nm_mun: 'Município',
    populacao: null,
    area_km2: null,
    densidade_hab_km2: null,
    ...overrides,
  }
}

describe('StateRanking.vue', () => {
  it('renderiza as seis colunas com unidades no cabeçalho', () => {
    const wrapper = mount(StateRanking, {
      props: {
        entries: [
          entry({
            posicao: 1,
            cd_mun: '4300001',
            nm_mun: 'Município Válido',
            populacao: 1234567,
            area_km2: 1234.567,
            densidade_hab_km2: 999.999,
          }),
        ],
      },
    })

    const headers = wrapper.findAll('thead th').map((th) => th.text())
    expect(headers).toEqual([
      'Posição',
      'Código',
      'Município',
      'População (hab)',
      'Área (km²)',
      'Densidade (hab/km²)',
    ])
  })

  it('preserva a ordem recebida sem ordenar nem paginar o array', () => {
    const wrapper = mount(StateRanking, {
      props: {
        entries: [
          entry({ posicao: 1, cd_mun: '4300002', nm_mun: 'Densidade Baixa', densidade_hab_km2: 1 }),
          entry({ posicao: 2, cd_mun: '4300001', nm_mun: 'Densidade Alta', densidade_hab_km2: 900 }),
        ],
      },
    })

    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(2)
    expect(rows[0]!.text()).toContain('Densidade Baixa')
    expect(rows[1]!.text()).toContain('Densidade Alta')
  })

  it('usa a posição global informada e não o índice do array', () => {
    const wrapper = mount(StateRanking, {
      props: {
        entries: [
          entry({ posicao: 26, cd_mun: '4300001' }),
          entry({ posicao: 27, cd_mun: '4300002' }),
        ],
      },
    })

    const positions = wrapper.findAll('tbody th[scope="row"]').map((cell) => cell.text())
    expect(positions).toEqual(['26', '27'])
  })

  it('formata população, área e densidade em pt-BR com duas casas', () => {
    const wrapper = mount(StateRanking, {
      props: {
        entries: [
          entry({
            posicao: 1,
            cd_mun: '4300001',
            populacao: 1234567,
            area_km2: 1234.567,
            densidade_hab_km2: 999.995,
          }),
        ],
      },
    })

    const cells = wrapper.findAll('tbody td').map((cell) => cell.text())
    expect(cells).toEqual(['4300001', 'Município', '1.234.567', '1.234,57', '1.000,00'])
  })

  it('exibe "Não disponível" para valores nulos sem acrescentar unidade', () => {
    const wrapper = mount(StateRanking, {
      props: { entries: [entry({ posicao: 1, cd_mun: '4300001' })] },
    })

    const cells = wrapper.findAll('tbody td').map((cell) => cell.text())
    expect(cells).toEqual(['4300001', 'Município', 'Não disponível', 'Não disponível', 'Não disponível'])

    const text = wrapper.text()
    expect(text).not.toContain('Não disponível hab')
    expect(text).not.toContain('Não disponível km²')
    expect(text).not.toContain('Não disponível hab/km²')
  })

  it('mantém a semântica de tabela: caption, thead/tbody e escopos', () => {
    const wrapper = mount(StateRanking, {
      props: { entries: [entry({ posicao: 1, cd_mun: '4300001', nm_mun: 'Acrelândia' })] },
    })

    const caption = wrapper.find('caption')
    expect(caption.exists()).toBe(true)
    expect(caption.text()).toContain('Ranking municipal por densidade demográfica')

    expect(wrapper.find('thead').exists()).toBe(true)
    expect(wrapper.find('tbody').exists()).toBe(true)

    const columnHeaders = wrapper.findAll('thead th')
    expect(columnHeaders).toHaveLength(6)
    for (const th of columnHeaders) {
      expect(th.attributes('scope')).toBe('col')
    }

    const rowHeaders = wrapper.findAll('tbody th')
    expect(rowHeaders).toHaveLength(1)
    expect(rowHeaders[0]!.attributes('scope')).toBe('row')
    expect(rowHeaders[0]!.text()).toBe('1')
  })

  it('exibe a região rolável de forma identificável e navegável por teclado', () => {
    const wrapper = mount(StateRanking, {
      props: { entries: [entry({ posicao: 1, cd_mun: '4300001' })] },
    })

    const region = wrapper.find('[role="region"]')
    expect(region.exists()).toBe(true)
    expect(region.attributes('aria-label')).toBe('Ranking municipal por densidade demográfica')
    expect(region.attributes('tabindex')).toBe('0')
    expect(region.classes()).toContain('overflow-x-auto')
  })

  it('renderiza uma linha por município recebido, identificada pelo código', () => {
    const wrapper = mount(StateRanking, {
      props: {
        entries: [
          entry({ posicao: 1, cd_mun: '4300001' }),
          entry({ posicao: 2, cd_mun: '4300002' }),
          entry({ posicao: 3, cd_mun: '4300003' }),
        ],
      },
    })

    const codes = wrapper.findAll('tbody td:first-of-type').map((cell) => cell.text())
    expect(codes).toEqual(['4300001', '4300002', '4300003'])
  })

  it('renderiza apenas o cabeçalho quando não há municípios', () => {
    const wrapper = mount(StateRanking, { props: { entries: [] } })

    expect(wrapper.findAll('tbody tr')).toHaveLength(0)
    expect(wrapper.findAll('thead th')).toHaveLength(6)
  })
})
