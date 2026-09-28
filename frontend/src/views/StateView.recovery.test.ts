import { afterEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import StateView from './StateView.vue'
import { censusApi, CensusHttpError } from '../api/census'
import type {
  PageSizeOption,
  StateDetailsResponse,
  StateListResponse,
  StateRankingResponse,
} from '../api/types'

enableAutoUnmount(afterEach)
afterEach(() => vi.restoreAllMocks())

const states = [
  { cd_uf: '43', nm_uf: 'Rio Grande do Sul' },
  { cd_uf: '53', nm_uf: 'Distrito Federal' },
]

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

function details(cdUf: string, name: string, population = 1000): StateDetailsResponse {
  return {
    data: {
      cd_uf: cdUf,
      nm_uf: name,
      agregados: {
        populacao: population,
        area_km2: 100,
        densidade_hab_km2: 10,
        total_setores: 10,
        distribuicao: {
          total: 10,
          urban: 6,
          rural: 3,
          unclassified: 1,
          urban_pct: 60,
          rural_pct: 30,
          unclassified_pct: 10,
        },
        homens: {
          valor: 490,
          percentual: 49,
          setores_com_valor: 10,
          total_setores: 10,
          estado: 'completo',
        },
        mulheres: {
          valor: 510,
          percentual: 51,
          setores_com_valor: 10,
          total_setores: 10,
          estado: 'completo',
        },
      },
      setores_sem_municipio_identificavel: 0,
    },
  }
}

function ranking(
  cdUf: string,
  page: number,
  perPage: PageSizeOption,
  name = 'Ranking ' + cdUf + ' página ' + page,
  total = 55,
  lastPage = 3,
): StateRankingResponse {
  return {
    data: [
      {
        posicao: (page - 1) * perPage + 1,
        cd_mun: cdUf + String(page).padStart(5, '0'),
        nm_mun: name,
        populacao: 100,
        area_km2: 10,
        densidade_hab_km2: 10,
      },
    ],
    meta: {
      current_page: page,
      per_page: perPage,
      total,
      last_page: lastPage,
    },
  }
}

type SetupOptions = {
  list?: () => Promise<StateListResponse>
  details?: (cdUf: string) => Promise<StateDetailsResponse>
  ranking?: (
    cdUf: string,
    page: number,
    perPage: PageSizeOption,
  ) => Promise<StateRankingResponse>
}

function setup(options: SetupOptions = {}) {
  const listStates = vi.spyOn(censusApi, 'listStates').mockImplementation(
    options.list ?? (async () => ({ data: states })),
  )
  const getStateDetails = vi.spyOn(censusApi, 'getStateDetails').mockImplementation(
    options.details
      ?? (async (cdUf) =>
        cdUf === '53'
          ? details('53', 'Distrito Federal', 2000)
          : details('43', 'Rio Grande do Sul')),
  )
  const getStateRanking = vi.spyOn(censusApi, 'getStateRanking').mockImplementation(
    options.ranking ?? (async (cdUf, page, perPage) => ranking(cdUf, page, perPage)),
  )
  const page = mount(StateView)

  return { page, listStates, getStateDetails, getStateRanking }
}

async function selectState(page: VueWrapper, cdUf: string) {
  await page.get<HTMLSelectElement>('#uf-select').setValue(cdUf)
}

function button(page: VueWrapper, label: string) {
  const match = page.findAll('button').find((candidate) => candidate.text() === label)
  if (!match) throw new Error('Botão não encontrado: ' + label)
  return match
}

describe('recuperação da consulta estadual', () => {
  it('distingue uma lista de UFs vazia sem consultar detalhes ou ranking', async () => {
    const { page, getStateDetails, getStateRanking } = setup({
      list: async () => ({ data: [] }),
    })
    await flushPromises()

    expect(page.get('[role="status"]').text()).toBe('Nenhuma unidade da federação disponível.')
    expect(page.get<HTMLSelectElement>('#uf-select').attributes()).toHaveProperty('disabled')
    expect(getStateDetails).not.toHaveBeenCalled()
    expect(getStateRanking).not.toHaveBeenCalled()
  })

  it.each([
    ['rede', () => new TypeError('offline')],
    ['503', () => new CensusHttpError(503, 'offline')],
  ])('recupera falha %s da lista sem chamar detalhes ou ranking', async (_label, failure) => {
    const retry = deferred<StateListResponse>()
    const { page, listStates, getStateDetails, getStateRanking } = setup({
      list: vi.fn().mockRejectedValueOnce(failure()).mockReturnValueOnce(retry.promise),
    })
    await flushPromises()

    expect(page.get('[role="alert"]').text()).toContain('Tente novamente')
    const retryButton = button(page, 'Tentar carregar UFs novamente')
    retryButton.element.click()
    retryButton.element.click()
    await flushPromises()

    expect(listStates).toHaveBeenCalledTimes(2)
    expect(getStateDetails).not.toHaveBeenCalled()
    expect(getStateRanking).not.toHaveBeenCalled()
    expect(page.get('[role="status"]').text()).toContain('Carregando unidades')

    retry.resolve({ data: states })
    await flushPromises()

    expect(page.find('[role="alert"]').exists()).toBe(false)
    expect(page.findAll('#uf-select option')).toHaveLength(3)
  })

  it('não oferece retry para erro HTTP 4xx da lista', async () => {
    const { page, listStates } = setup({
      list: async () => {
        throw new CensusHttpError(422, 'inválido')
      },
    })
    await flushPromises()

    expect(page.get('[role="alert"]').text()).toBe('Não foi possível carregar a lista de UFs.')
    expect(page.text()).not.toContain('Tente novamente')
    expect(page.find('button').exists()).toBe(false)
    expect(listStates).toHaveBeenCalledTimes(1)
  })

  it('retry dos detalhes é independente e preserva um ranking válido', async () => {
    const retry = deferred<StateDetailsResponse>()
    const { page, listStates, getStateDetails, getStateRanking } = setup({
      details: vi.fn()
        .mockRejectedValueOnce(new CensusHttpError(503, 'offline'))
        .mockReturnValueOnce(retry.promise),
    })
    await flushPromises()
    await selectState(page, '43')
    await flushPromises()

    expect(page.text()).toContain('Ranking 43 página 1')
    expect(page.get('[role="alert"]').text()).toContain('agregados')
    const retryButton = button(page, 'Tentar carregar agregados novamente')
    retryButton.element.click()
    retryButton.element.click()
    await flushPromises()

    expect(getStateDetails.mock.calls).toEqual([['43'], ['43']])
    expect(getStateRanking).toHaveBeenCalledExactlyOnceWith('43', 1, 25)
    expect(listStates).toHaveBeenCalledTimes(1)
    expect(page.text()).toContain('Ranking 43 página 1')

    retry.resolve(details('43', 'Rio Grande do Sul recuperado', 3000))
    await flushPromises()

    expect(page.text()).toContain('Rio Grande do Sul recuperado')
    expect(page.find('[role="alert"]').exists()).toBe(false)
  })

  it('404 dos detalhes não oferece retry e não remove o ranking', async () => {
    const { page } = setup({
      details: async () => {
        throw new CensusHttpError(404, 'ausente')
      },
    })
    await flushPromises()
    await selectState(page, '43')
    await flushPromises()

    expect(page.get('[role="alert"]').text()).toBe('UF não encontrada. Selecione outra UF.')
    expect(page.text()).not.toContain('Tentar carregar agregados novamente')
    expect(page.text()).toContain('Ranking 43 página 1')
  })

  it('falha ao paginar preserva agregados e página anterior, e retry repete o contexto exato', async () => {
    const retry = deferred<StateRankingResponse>()
    const rankingImplementation = vi.fn()
      .mockResolvedValueOnce(ranking('43', 1, 25, 'Página válida 1'))
      .mockRejectedValueOnce(new CensusHttpError(503, 'offline'))
      .mockReturnValueOnce(retry.promise)
    const { page, listStates, getStateDetails, getStateRanking } = setup({
      ranking: rankingImplementation,
    })
    await flushPromises()
    await selectState(page, '43')
    await flushPromises()

    expect(page.text()).toContain('1.000 hab')
    await button(page, 'Próxima').trigger('click')
    await flushPromises()

    expect(page.get('[role="alert"]').text()).toContain('página 2')
    expect(page.text()).toContain('Página válida 1')
    expect(page.text()).toContain('Página 1 de 3')
    expect(page.text()).toContain('1.000 hab')

    const retryButton = button(page, 'Tentar carregar ranking novamente')
    retryButton.element.click()
    retryButton.element.click()
    await flushPromises()

    expect(getStateRanking.mock.calls).toEqual([
      ['43', 1, 25],
      ['43', 2, 25],
      ['43', 2, 25],
    ])
    expect(getStateDetails).toHaveBeenCalledTimes(1)
    expect(listStates).toHaveBeenCalledTimes(1)
    expect(page.text()).toContain('Página válida 1')

    retry.resolve(ranking('43', 2, 25, 'Página recuperada 2'))
    await flushPromises()

    expect(page.text()).toContain('Página recuperada 2')
    expect(page.text()).toContain('Página 2 de 3')
    expect(page.find('[role="alert"]').exists()).toBe(false)
  })

  it('422 do ranking não oferece retry e mantém os agregados', async () => {
    const { page } = setup({
      ranking: async () => {
        throw new CensusHttpError(422, 'inválido')
      },
    })
    await flushPromises()
    await selectState(page, '43')
    await flushPromises()

    expect(page.get('[role="alert"]').text()).toContain('parâmetros atuais')
    expect(page.text()).not.toContain('Tentar carregar ranking novamente')
    expect(page.text()).toContain('1.000 hab')
  })

  it.each(['resolve', 'reject'] as const)(
    'troca A → B ignora detalhes antigos quando eles %s',
    async (outcome) => {
      const oldDetails = deferred<StateDetailsResponse>()
      const { page } = setup({
        details: async (cdUf) =>
          cdUf === '43' ? oldDetails.promise : details('53', 'Distrito Federal atual', 2000),
      })
      await flushPromises()

      await selectState(page, '43')
      await selectState(page, '53')
      await flushPromises()

      if (outcome === 'resolve') {
        oldDetails.resolve(details('43', 'Rio Grande do Sul antigo', 1000))
      } else {
        oldDetails.reject(new Error('falha antiga'))
      }
      await flushPromises()

      expect(page.text()).toContain('Distrito Federal atual')
      expect(page.text()).not.toContain('Rio Grande do Sul antigo')
      expect(page.find('[role="alert"]').exists()).toBe(false)
    },
  )

  it.each(['resolve', 'reject'] as const)(
    'ranking pendente da página 3 da UF A não altera a UF B quando %s',
    async (outcome) => {
      const oldPage = deferred<StateRankingResponse>()
      const { page, getStateRanking } = setup({
        ranking: async (cdUf, requestedPage, perPage) => {
          if (cdUf === '53') return ranking('53', 1, perPage, 'Ranking atual de B', 1, 1)
          if (requestedPage === 3) return oldPage.promise
          return ranking('43', requestedPage, perPage, 'Página A ' + requestedPage)
        },
      })
      await flushPromises()
      await selectState(page, '43')
      await flushPromises()
      await button(page, 'Próxima').trigger('click')
      await flushPromises()
      await button(page, 'Próxima').trigger('click')
      await selectState(page, '53')
      await flushPromises()

      if (outcome === 'resolve') {
        oldPage.resolve(ranking('43', 3, 25, 'Página antiga A 3'))
      } else {
        oldPage.reject(new Error('falha antiga'))
      }
      await flushPromises()

      expect(getStateRanking).toHaveBeenLastCalledWith('53', 1, 25)
      expect(page.text()).toContain('Ranking atual de B')
      expect(page.text()).not.toContain('Página antiga A 3')
      expect(page.find('[role="alert"]').exists()).toBe(false)
    },
  )

  it('ranking antigo de outra página e perPage não substitui a requisição posterior', async () => {
    const oldPage = deferred<StateRankingResponse>()
    const currentPage = deferred<StateRankingResponse>()
    const { page, getStateRanking } = setup({
      ranking: async (cdUf, requestedPage, perPage) => {
        if (requestedPage === 2 && perPage === 25) return oldPage.promise
        if (requestedPage === 1 && perPage === 50) return currentPage.promise
        return ranking(cdUf, requestedPage, perPage, 'Página inicial')
      },
    })
    await flushPromises()
    await selectState(page, '43')
    await flushPromises()

    const pageSizeSelect = page.get<HTMLSelectElement>('#page-size-select')
    button(page, 'Próxima').element.click()
    pageSizeSelect.element.value = '50'
    pageSizeSelect.element.dispatchEvent(new Event('change'))
    await flushPromises()

    expect(getStateRanking).toHaveBeenNthCalledWith(2, '43', 2, 25)
    expect(getStateRanking).toHaveBeenNthCalledWith(3, '43', 1, 50)
    expect(page.text()).not.toContain('Página inicial')

    currentPage.resolve(ranking('43', 1, 50, 'Página atual com 50'))
    await flushPromises()
    oldPage.resolve(ranking('43', 2, 25, 'Página antiga com 25'))
    await flushPromises()

    expect(page.text()).toContain('Página atual com 50')
    expect(page.text()).not.toContain('Página antiga com 25')
    expect(page.get<HTMLSelectElement>('#page-size-select').element.value).toBe('50')
  })

  it.each(['resolve', 'reject'] as const)(
    'retry antigo da UF A não altera a UF B quando %s',
    async (outcome) => {
      const oldRetry = deferred<StateRankingResponse>()
      let stateACalls = 0
      const { page } = setup({
        ranking: async (cdUf, _requestedPage, perPage) => {
          if (cdUf === '53') return ranking('53', 1, perPage, 'Ranking atual de B', 1, 1)
          stateACalls++
          if (stateACalls === 1) throw new CensusHttpError(503, 'offline')
          return oldRetry.promise
        },
      })
      await flushPromises()
      await selectState(page, '43')
      await flushPromises()
      await button(page, 'Tentar carregar ranking novamente').trigger('click')
      await selectState(page, '53')
      await flushPromises()

      if (outcome === 'resolve') {
        oldRetry.resolve(ranking('43', 1, 25, 'Retry antigo de A'))
      } else {
        oldRetry.reject(new Error('falha antiga'))
      }
      await flushPromises()

      expect(page.text()).toContain('Ranking atual de B')
      expect(page.text()).not.toContain('Retry antigo de A')
      expect(page.find('[role="alert"]').exists()).toBe(false)
    },
  )

  it('limpar a seleção invalida detalhes e ranking pendentes e volta ao estado inicial', async () => {
    const pendingDetails = deferred<StateDetailsResponse>()
    const pendingRanking = deferred<StateRankingResponse>()
    const { page } = setup({
      details: async () => pendingDetails.promise,
      ranking: async () => pendingRanking.promise,
    })
    await flushPromises()
    await selectState(page, '43')
    await selectState(page, '')

    pendingDetails.resolve(details('43', 'Resultado antigo'))
    pendingRanking.resolve(ranking('43', 1, 25, 'Ranking antigo'))
    await flushPromises()

    expect(page.text()).toContain('Selecione uma UF para consultar')
    expect(page.text()).not.toContain('Resultado antigo')
    expect(page.text()).not.toContain('Ranking antigo')
    expect(page.find('[role="alert"]').exists()).toBe(false)
  })

  it('loading de detalhes não bloqueia ranking ou seletor de UF', async () => {
    const pendingDetails = deferred<StateDetailsResponse>()
    const { page } = setup({
      details: async () => pendingDetails.promise,
    })
    await flushPromises()
    await selectState(page, '43')
    await flushPromises()

    expect(page.text()).toContain('Carregando agregados estaduais')
    expect(page.text()).toContain('Ranking 43 página 1')
    expect(page.get<HTMLSelectElement>('#uf-select').attributes()).not.toHaveProperty('disabled')

    pendingDetails.resolve(details('43', 'Rio Grande do Sul'))
    await flushPromises()
  })

  it('loading de ranking preserva agregados, permite trocar UF e bloqueia somente seus controles', async () => {
    const pendingRanking = deferred<StateRankingResponse>()
    const { page } = setup({
      ranking: async () => pendingRanking.promise,
    })
    await flushPromises()
    await selectState(page, '43')
    await flushPromises()

    expect(page.text()).toContain('1.000 hab')
    expect(page.text()).toContain('Carregando ranking municipal')
    expect(page.get<HTMLSelectElement>('#uf-select').attributes()).not.toHaveProperty('disabled')
    expect(page.get<HTMLSelectElement>('#page-size-select').attributes()).toHaveProperty('disabled')
    expect(button(page, 'Anterior').attributes()).toHaveProperty('disabled')
    expect(button(page, 'Próxima').attributes()).toHaveProperty('disabled')

    pendingRanking.resolve(ranking('43', 1, 25))
    await flushPromises()
  })

  it('unmount invalida detalhes e ranking pendentes', async () => {
    const pendingDetails = deferred<StateDetailsResponse>()
    const pendingRanking = deferred<StateRankingResponse>()
    const { page, getStateDetails, getStateRanking } = setup({
      details: async () => pendingDetails.promise,
      ranking: async () => pendingRanking.promise,
    })
    await flushPromises()
    await selectState(page, '43')

    page.unmount()
    pendingDetails.resolve(details('43', 'Resultado após unmount'))
    pendingRanking.reject(new Error('Falha após unmount'))
    await flushPromises()

    expect(getStateDetails).toHaveBeenCalledExactlyOnceWith('43')
    expect(getStateRanking).toHaveBeenCalledExactlyOnceWith('43', 1, 25)
    expect(page.exists()).toBe(false)
  })
})
