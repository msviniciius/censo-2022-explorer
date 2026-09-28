import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import MunicipalitySearch from './MunicipalitySearch.vue'
import { censusApi, CensusHttpError } from '../api/census'
import type { SearchResponse } from '../api/types'

enableAutoUnmount(afterEach)
function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: Error) => void
  const promise = new Promise<T>((res, rej) => { resolve = res; reject = rej })
  return { promise, resolve, reject }
}
const result = (name: string): SearchResponse => ({
  data: [{ cd_mun: '1100015', nm_mun: name, cd_uf: '11', nm_uf: 'Rondônia' }],
})

beforeEach(() => vi.useFakeTimers())
afterEach(() => { vi.restoreAllMocks(); vi.useRealTimers() })

describe('recuperação da busca municipal', () => {
  it('distingue initial, debounce, loading e resposta vazia bem-sucedida', async () => {
    const request = deferred<SearchResponse>()
    const spy = vi.spyOn(censusApi, 'search').mockReturnValue(request.promise)
    const page = mount(MunicipalitySearch)
    expect(page.text()).toContain('Digite pelo menos dois caracteres')
    await page.get('input').setValue('cidade')
    expect(page.text()).toContain('Aguardando digitação')
    expect(page.text()).not.toContain('Nenhum município encontrado')
    await vi.advanceTimersByTimeAsync(299)
    expect(spy).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    expect(page.get('[role="status"]').text()).toBe('Buscando municípios...')
    expect(page.get('input').attributes('aria-busy')).toBe('true')
    expect(page.text()).not.toContain('Nenhum município encontrado')
    request.resolve({ data: [] })
    await flushPromises()
    expect(page.get('[role="status"]').text()).toBe('Nenhum município encontrado.')
    expect(page.get('input').attributes('aria-busy')).toBe('false')
  })

  it('erro permite retry do termo exato por botão, sem duplicar durante loading', async () => {
    const retry = deferred<SearchResponse>()
    const spy = vi.spyOn(censusApi, 'search').mockRejectedValueOnce(new TypeError('rede'))
      .mockReturnValueOnce(retry.promise)
    const page = mount(MunicipalitySearch)
    await page.get('input').setValue('  São José  ')
    await vi.advanceTimersByTimeAsync(300)
    expect(page.get('[role="alert"]').text()).toContain('Não foi possível buscar')
    expect(page.text()).not.toContain('Nenhum município encontrado')
    const button = page.get('button')
    expect(button.attributes('type')).toBe('button')
    button.element.click()
    button.element.click()
    await flushPromises()
    expect(spy).toHaveBeenCalledTimes(2)
    expect(spy.mock.calls).toEqual([['São José'], ['São José']])
    expect(page.text()).toContain('Buscando municípios')
    expect(page.find('button').exists()).toBe(false)
    retry.resolve(result('São José'))
    await flushPromises()
    expect(page.get('[role="option"]').text()).toContain('São José')
    expect(page.find('[role="alert"]').exists()).toBe(false)
  })

  it('422 é erro editável, sem vazio e sem retry da entrada inválida', async () => {
    const spy = vi.spyOn(censusApi, 'search').mockRejectedValueOnce(new CensusHttpError(422, 'validação'))
      .mockResolvedValueOnce(result('Cidade'))
    const page = mount(MunicipalitySearch)
    await page.get('input').setValue('x'.repeat(101))
    await vi.advanceTimersByTimeAsync(300)
    expect(page.get('[role="alert"]').text()).toContain('Edite o texto')
    expect(page.text()).not.toContain('Nenhum município encontrado')
    expect(page.find('button').exists()).toBe(false)
    await page.get('input').setValue('cidade')
    expect(page.find('[role="alert"]').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(300)
    expect(spy).toHaveBeenCalledTimes(2)
    expect(page.get('[role="option"]').text()).toContain('Cidade')
  })

  it.each(['resolve', 'reject'] as const)('retry antigo que %s não muda resultado da busca nova', async (outcome) => {
    const retry = deferred<SearchResponse>()
    const spy = vi.spyOn(censusApi, 'search').mockRejectedValueOnce(new CensusHttpError(503, 'offline'))
      .mockReturnValueOnce(retry.promise).mockResolvedValueOnce(result('Cidade B'))
    const page = mount(MunicipalitySearch)
    await page.get('input').setValue('cidade A')
    await vi.advanceTimersByTimeAsync(300)
    await page.get('button').trigger('click')
    await page.get('input').setValue('cidade B')
    await vi.advanceTimersByTimeAsync(300)
    if (outcome === 'resolve') retry.resolve({ data: [] })
    else retry.reject(new Error('falha antiga'))
    await flushPromises()
    expect(spy.mock.calls).toEqual([['cidade A'], ['cidade A'], ['cidade B']])
    expect(page.get('[role="option"]').text()).toContain('Cidade B')
    expect(page.find('[role="alert"]').exists()).toBe(false)
    expect(page.text()).not.toContain('Nenhum município encontrado')
  })

  it('editar após erro remove o retry anterior e cancelar com Escape mantém a busca fechada', async () => {
    const retry = deferred<SearchResponse>()
    const spy = vi.spyOn(censusApi, 'search').mockRejectedValueOnce(new Error('rede'))
      .mockReturnValueOnce(retry.promise)
    const page = mount(MunicipalitySearch)
    await page.get('input').setValue('cidade A')
    await vi.advanceTimersByTimeAsync(300)
    const oldButton = page.get('button')
    await page.get('input').setValue('cidade B')
    oldButton.element.click()
    expect(spy).toHaveBeenCalledTimes(1)
    expect(page.find('[role="alert"]').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(300)
    await page.get('input').trigger('keydown', { key: 'Escape' })
    retry.resolve(result('Cidade B'))
    await flushPromises()
    expect(page.find('[role="option"]').exists()).toBe(false)
    expect(page.text()).not.toContain('Nenhum município encontrado')
  })
})
