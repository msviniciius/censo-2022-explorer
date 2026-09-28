import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import StateView from './StateView.vue'
import { censusApi } from '../api/census'
import type {
  PageSizeOption,
  StateDetailsResponse,
  StateRankingResponse,
} from '../api/types'

const states = [
  { cd_uf: '43', nm_uf: 'Rio Grande do Sul' },
  { cd_uf: '53', nm_uf: 'Distrito Federal' },
]

function details(
  cdUf: string,
  name: string,
  unidentifiedSectors = 0,
): StateDetailsResponse {
  return {
    data: {
      cd_uf: cdUf,
      nm_uf: name,
      agregados: {
        populacao: 10882965,
        area_km2: 281707.1504883004,
        densidade_hab_km2: 38.6321929746401,
        total_setores: 25569,
        distribuicao: {
          total: 25569,
          urban: 19436,
          rural: 6112,
          unclassified: 21,
          urban_pct: 76.01392311001604,
          rural_pct: 23.903946184833195,
          unclassified_pct: 0.08213070515076851,
        },
        homens: {
          valor: 5237692,
          percentual: 48.22035882365234,
          setores_com_valor: 24874,
          total_setores: 25569,
          estado: 'parcial',
        },
        mulheres: {
          valor: 5624301,
          percentual: 51.77964117634766,
          setores_com_valor: 24869,
          total_setores: 25569,
          estado: 'parcial',
        },
      },
      setores_sem_municipio_identificavel: unidentifiedSectors,
    },
  }
}

function ranking(
  currentPage: number,
  perPage: PageSizeOption,
  total: number,
  lastPage: number,
  position = currentPage,
): StateRankingResponse {
  return {
    data: [
      {
        posicao: position,
        cd_mun: `43${String(position).padStart(5, '0')}`,
        nm_mun: `Município na posição ${position}`,
        populacao: 123456,
        area_km2: 789.123,
        densidade_hab_km2: 156.445,
      },
    ],
    meta: {
      current_page: currentPage,
      per_page: perPage,
      total,
      last_page: lastPage,
    },
  }
}

function setup(
  rankingImplementation: (
    cdUf: string,
    page: number,
    perPage: PageSizeOption,
  ) => Promise<StateRankingResponse> = async (_cdUf, page, perPage) =>
    ranking(page, perPage, 55, 3, (page - 1) * perPage + 1),
) {
  const listStates = vi.spyOn(censusApi, 'listStates').mockResolvedValue({ data: states })
  const getStateDetails = vi
    .spyOn(censusApi, 'getStateDetails')
    .mockImplementation(async (cdUf) =>
      cdUf === '53'
        ? details('53', 'Distrito Federal')
        : details('43', 'Rio Grande do Sul', 2),
    )
  const getStateRanking = vi
    .spyOn(censusApi, 'getStateRanking')
    .mockImplementation(rankingImplementation)
  const wrapper = mount(StateView)

  return { wrapper, listStates, getStateDetails, getStateRanking }
}

async function selectState(wrapper: VueWrapper, cdUf: string) {
  await wrapper.get<HTMLSelectElement>('#uf-select').setValue(cdUf)
  await flushPromises()
}

beforeEach(() => {
  vi.restoreAllMocks()
})

describe('StateView.vue', () => {
  it('lista as UFs uma vez, inclui o Distrito Federal e não consulta dados antes da seleção', async () => {
    const { wrapper, listStates, getStateDetails, getStateRanking } = setup()
    await flushPromises()

    expect(listStates).toHaveBeenCalledTimes(1)
    expect(getStateDetails).not.toHaveBeenCalled()
    expect(getStateRanking).not.toHaveBeenCalled()
    expect(wrapper.get<HTMLSelectElement>('#uf-select').element.value).toBe('')
    expect(wrapper.findAll('#uf-select option').map((option) => option.text())).toEqual([
      'Selecione uma UF',
      'Rio Grande do Sul',
      'Distrito Federal',
    ])
    expect(wrapper.text()).toContain(
      'Selecione uma UF para consultar os agregados estaduais e o ranking municipal.',
    )
  })

  it('seleciona a UF, renderiza agregados, aviso e usa ranking e meta recebidos', async () => {
    const { wrapper, getStateDetails, getStateRanking } = setup(async (_cdUf, _page, perPage) =>
      ranking(7, perPage, 207, 9, 144),
    )
    await flushPromises()

    await selectState(wrapper, '43')

    expect(getStateDetails).toHaveBeenCalledExactlyOnceWith('43')
    expect(getStateRanking).toHaveBeenCalledExactlyOnceWith('43', 1, 25)
    expect(wrapper.text()).toContain('Rio Grande do Sul')
    expect(wrapper.text()).toContain('Código: 43')
    expect(wrapper.text()).toContain('10.882.965 hab')
    expect(wrapper.text()).toContain('281.707,15 km²')
    expect(wrapper.text()).toContain('38,63 hab/km²')
    expect(wrapper.text()).toContain('Total de Setores: 25.569')
    expect(wrapper.text()).toContain(
      '2 setores sem município identificável estão incluídos nos totais estaduais e não aparecem no ranking municipal.',
    )
    expect(wrapper.text()).toContain('Município na posição 144')
    expect(wrapper.get('tbody th[scope="row"]').text()).toBe('144')
    expect(wrapper.text()).toContain('Página 7 de 9')
    expect(wrapper.text()).toContain('207 municípios')
  })

  it('pagina somente o ranking, usa a página anterior correta e respeita os limites', async () => {
    const responses = [
      ranking(1, 25, 50, 2, 1),
      ranking(2, 25, 50, 2, 26),
      ranking(1, 25, 50, 2, 1),
    ]
    const { wrapper, listStates, getStateDetails, getStateRanking } = setup(async () => {
      const response = responses.shift()
      if (!response) throw new Error('Resposta de ranking não prevista no teste')
      return response
    })
    await flushPromises()
    await selectState(wrapper, '43')

    const [previous, next] = wrapper.findAll('button')
    expect(previous!.attributes()).toHaveProperty('disabled')
    expect(next!.attributes()).not.toHaveProperty('disabled')

    await next!.trigger('click')
    await flushPromises()
    expect(getStateRanking).toHaveBeenNthCalledWith(2, '43', 2, 25)
    expect(wrapper.text()).toContain('Página 2 de 2')
    expect(wrapper.get('tbody th[scope="row"]').text()).toBe('26')
    expect(next!.attributes()).toHaveProperty('disabled')

    await previous!.trigger('click')
    await flushPromises()
    expect(getStateRanking).toHaveBeenNthCalledWith(3, '43', 1, 25)
    expect(listStates).toHaveBeenCalledTimes(1)
    expect(getStateDetails).toHaveBeenCalledTimes(1)
  })

  it('reinicia na página 1 ao trocar 25/50/100 e preserva os agregados', async () => {
    const { wrapper, getStateDetails, getStateRanking } = setup()
    await flushPromises()
    await selectState(wrapper, '43')
    const aggregateText = '10.882.965 hab'
    expect(wrapper.text()).toContain(aggregateText)

    await wrapper.get<HTMLSelectElement>('#page-size-select').setValue('50')
    await flushPromises()
    expect(getStateRanking).toHaveBeenNthCalledWith(2, '43', 1, 50)
    expect(wrapper.text()).toContain(aggregateText)

    await wrapper.get<HTMLSelectElement>('#page-size-select').setValue('100')
    await flushPromises()
    expect(getStateRanking).toHaveBeenNthCalledWith(3, '43', 1, 100)
    expect(getStateDetails).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain(aggregateText)
  })

  it('troca de UF volta à página 1 e preserva o tamanho escolhido', async () => {
    const { wrapper, listStates, getStateDetails, getStateRanking } = setup()
    await flushPromises()
    await selectState(wrapper, '43')

    await wrapper.get<HTMLSelectElement>('#page-size-select').setValue('50')
    await flushPromises()
    await selectState(wrapper, '53')

    expect(getStateDetails).toHaveBeenNthCalledWith(2, '53')
    expect(getStateRanking).toHaveBeenNthCalledWith(3, '53', 1, 50)
    expect(wrapper.get<HTMLSelectElement>('#page-size-select').element.value).toBe('50')
    expect(wrapper.text()).toContain('Distrito Federal')
    expect(listStates).toHaveBeenCalledTimes(1)
  })

  it('bloqueia paginação e tamanho enquanto o ranking está pendente', async () => {
    let resolveRanking: (response: StateRankingResponse) => void = () => {}
    const pendingRanking = new Promise<StateRankingResponse>((resolve) => {
      resolveRanking = resolve
    })
    const { wrapper } = setup(async () => pendingRanking)
    await flushPromises()

    await wrapper.get<HTMLSelectElement>('#uf-select').setValue('43')

    const [previous, next] = wrapper.findAll('button')
    expect(wrapper.get<HTMLSelectElement>('#page-size-select').attributes()).toHaveProperty(
      'disabled',
    )
    expect(previous!.attributes()).toHaveProperty('disabled')
    expect(next!.attributes()).toHaveProperty('disabled')
    expect(wrapper.text()).toContain('Carregando ranking municipal...')

    resolveRanking(ranking(1, 25, 55, 3, 1))
    await flushPromises()

    expect(wrapper.get<HTMLSelectElement>('#page-size-select').attributes()).not.toHaveProperty(
      'disabled',
    )
    expect(next!.attributes()).not.toHaveProperty('disabled')
  })
})
