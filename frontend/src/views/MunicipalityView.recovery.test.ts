import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import MunicipalityView from './MunicipalityView.vue'
import MunicipalitySearch from '../components/MunicipalitySearch.vue'
import { censusApi, CensusHttpError } from '../api/census'
import type { DetailsResponse } from '../api/types'

enableAutoUnmount(afterEach)
beforeEach(() => vi.useFakeTimers())
afterEach(() => { vi.restoreAllMocks(); vi.useRealTimers() })

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: Error) => void
  const promise = new Promise<T>((res, rej) => { resolve = res; reject = rej })
  return { promise, resolve, reject }
}
function details(code: string, name: string): DetailsResponse {
  return { data: {
    cd_mun: code, nm_mun: name, cd_uf: '11', nm_uf: 'Rondônia',
    agregados: {
      total_setores: 0, populacao: null, area_km2: null, densidade_hab_km2: null,
      distribuicao: { total: 0, urban: 0, rural: 0, unclassified: 0, urban_pct: null, rural_pct: null, unclassified_pct: null },
      homens: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 0, estado: 'indisponivel' },
      mulheres: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 0, estado: 'indisponivel' },
    },
  } }
}
async function open(path = '/municipios?cd=1100015') {
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/municipios', name: 'municipios', component: MunicipalityView },
  ] })
  await router.push(path)
  await router.isReady()
  const page = mount(MunicipalityView, { global: { plugins: [router] } })
  await flushPromises()
  return { page, router }
}

describe('recuperação dos detalhes municipais', () => {
  it('exibe orientação inicial sem consultar detalhes', async () => {
    const spy = vi.spyOn(censusApi, 'getDetails')
    const { page } = await open('/municipios')
    expect(page.text()).toContain('selecione uma sugestão')
    expect(spy).not.toHaveBeenCalled()
  })

  it('retry recupera falha no deep link, usa o mesmo código uma vez e bloqueia duplo clique', async () => {
    const retry = deferred<DetailsResponse>()
    const spy = vi.spyOn(censusApi, 'getDetails').mockRejectedValueOnce(new CensusHttpError(503, 'offline'))
      .mockReturnValueOnce(retry.promise)
    const { page, router } = await open()
    expect(page.get('[role="alert"]').text()).toContain('Não foi possível carregar')
    const button = page.get('button')
    expect(button.attributes('type')).toBe('button')
    button.element.click()
    button.element.click()
    await flushPromises()
    expect(page.get('[role="status"]').text()).toBe('Carregando detalhes...')
    expect(page.find('button').exists()).toBe(false)
    expect(spy.mock.calls).toEqual([['1100015'], ['1100015']])
    expect(router.currentRoute.value.query.cd).toBe('1100015')
    retry.resolve(details('1100015', 'Município A'))
    await flushPromises()
    expect(page.get('h2').text()).toBe('Município A')
    expect(page.find('[role="alert"]').exists()).toBe(false)
    expect(page.text()).not.toContain('Carregando detalhes')
    expect(spy).toHaveBeenCalledTimes(2)
  })

  it.each(['resolve', 'reject'] as const)('A falha, retry A começa, B resolve e retry A %s sem substituir B', async (outcome) => {
    const retryA = deferred<DetailsResponse>()
    const requestB = deferred<DetailsResponse>()
    const spy = vi.spyOn(censusApi, 'getDetails').mockRejectedValueOnce(new Error('rede'))
      .mockReturnValueOnce(retryA.promise).mockReturnValueOnce(requestB.promise)
    const { page, router } = await open()
    await page.get('button').trigger('click')
    page.getComponent(MunicipalitySearch).vm.$emit('select', details('3550308', 'Município B').data)
    await flushPromises()
    requestB.resolve(details('3550308', 'Município B'))
    await flushPromises()
    if (outcome === 'resolve') retryA.resolve(details('1100015', 'Município A'))
    else retryA.reject(new Error('falha antiga'))
    await flushPromises()
    expect(page.get('h2').text()).toBe('Município B')
    expect(page.find('[role="alert"]').exists()).toBe(false)
    expect(router.currentRoute.value.query.cd).toBe('3550308')
    expect(spy.mock.calls).toEqual([['1100015'], ['1100015'], ['3550308']])
  })

  it('editar durante retry limpa seleção e impede detalhes antigos de reaparecerem', async () => {
    const retry = deferred<DetailsResponse>()
    const spy = vi.spyOn(censusApi, 'getDetails').mockRejectedValueOnce(new Error('rede'))
      .mockReturnValueOnce(retry.promise)
    const { page, router } = await open()
    await page.get('button').trigger('click')
    await page.getComponent(MunicipalitySearch).get('input').setValue('x')
    await flushPromises()
    retry.resolve(details('1100015', 'Município A'))
    await flushPromises()
    expect(router.currentRoute.value.query.cd).toBeUndefined()
    expect(page.find('h2').exists()).toBe(false)
    expect(page.find('[role="alert"]').exists()).toBe(false)
    expect(page.text()).not.toContain('Carregando detalhes')
    expect(page.text()).toContain('selecione uma sugestão')
    expect(spy).toHaveBeenCalledTimes(2)
  })

  it('404 permite nova busca sem oferecer retry do município inexistente', async () => {
    vi.spyOn(censusApi, 'getDetails').mockRejectedValue(new CensusHttpError(404, 'not found'))
    const { page } = await open()
    expect(page.get('[role="alert"]').text()).toBe('Município não encontrado. Faça uma nova busca.')
    expect(page.find('button').exists()).toBe(false)
    expect(page.find('[role="combobox"]').exists()).toBe(true)
  })

  it('422 exibe erro sem instrução de tentar novamente, botão ou nova requisição', async () => {
    const spy = vi.spyOn(censusApi, 'getDetails').mockRejectedValue(new CensusHttpError(422, 'validação'))
    const { page } = await open()
    expect(page.get('[role="alert"]').text()).toBe('Não foi possível consultar este município. Faça uma nova busca.')
    expect(page.text()).not.toContain('Tente novamente')
    expect(page.find('button').exists()).toBe(false)
    await vi.runAllTimersAsync()
    await flushPromises()
    expect(spy).toHaveBeenCalledExactlyOnceWith('1100015')
  })

  it('deep link malformado é explicitamente inválido, sem requisição nem retry', async () => {
    const spy = vi.spyOn(censusApi, 'getDetails')
    const { page } = await open('/municipios?cd=.')
    expect(page.get('[role="alert"]').text()).toContain('Município não encontrado')
    expect(page.find('button').exists()).toBe(false)
    expect(spy).not.toHaveBeenCalled()
  })
})
