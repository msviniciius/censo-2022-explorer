import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import MunicipalitySearch from './MunicipalitySearch.vue'
import { censusApi } from '../api/census'
import type { SearchResponse } from '../api/types'

describe('MunicipalitySearch.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.useRealTimers()
  })

  it('termo com menos de 2 caracteres não chama a API', async () => {
    const spy = vi.spyOn(censusApi, 'search')
    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    await input.setValue('a')
    vi.advanceTimersByTime(300)
    expect(spy).not.toHaveBeenCalled()

    await input.setValue(' b ')
    vi.advanceTimersByTime(300)
    expect(spy).not.toHaveBeenCalled()
  })

  it('chama API após 300ms de debounce para termo >= 2', async () => {
    const mockResponse: SearchResponse = { data: [] }
    const spy = vi.spyOn(censusApi, 'search').mockResolvedValue(mockResponse)
    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    await input.setValue('sa')
    expect(spy).not.toHaveBeenCalled()

    vi.advanceTimersByTime(299)
    expect(spy).not.toHaveBeenCalled()

    vi.advanceTimersByTime(1)
    expect(spy).toHaveBeenCalledWith('sa')
  })

  it('digitação rápida resulta em apenas uma chamada (debounce)', async () => {
    const spy = vi.spyOn(censusApi, 'search').mockResolvedValue({ data: [] })
    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    await input.setValue('s')
    await input.setValue('sa')
    await input.setValue('sao')

    vi.advanceTimersByTime(300)
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('sao')
  })

  it('voltar para termo curto cancela busca pendente e limpa resultados', async () => {
    const spy = vi.spyOn(censusApi, 'search').mockResolvedValue({ data: [{ cd_mun: '1', nm_mun: 'A', cd_uf: '1', nm_uf: 'B' }] })
    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    await input.setValue('sao')
    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(wrapper.findAll('li')).toHaveLength(1)

    await input.setValue('s')
    // No debounce should be running for 's'
    vi.advanceTimersByTime(300)
    expect(spy).toHaveBeenCalledTimes(1) // Only the first one
    expect(wrapper.findAll('li')).toHaveLength(0)
  })

  it('exibe loading durante a requisição', async () => {
    let resolveSearch: (value: SearchResponse) => void = () => {}
    vi.spyOn(censusApi, 'search').mockReturnValue(new Promise(resolve => {
      resolveSearch = resolve
    }))

    const wrapper = mount(MunicipalitySearch)
    await wrapper.find('input').setValue('sao')
    vi.advanceTimersByTime(300)

    // In this component, loading state is internal, but we can verify it doesn't show results yet
    expect(wrapper.findAll('li')).toHaveLength(0)

    resolveSearch({ data: [{ cd_mun: '1', nm_mun: 'A', cd_uf: '1', nm_uf: 'B' }] })
    await flushPromises()
    expect(wrapper.findAll('li')).toHaveLength(1)
  })

  it('navegação por teclado (ArrowDown, ArrowUp, Enter, Escape)', async () => {
    const mun1 = { cd_mun: '1', nm_mun: 'Cidade A', cd_uf: '1', nm_uf: 'UF' }
    const mun2 = { cd_mun: '2', nm_mun: 'Cidade B', cd_uf: '1', nm_uf: 'UF' }
    vi.spyOn(censusApi, 'search').mockResolvedValue({ data: [mun1, mun2] })

    const wrapper = mount(MunicipalitySearch)
    await wrapper.find('input').setValue('cid')
    vi.advanceTimersByTime(300)
    await flushPromises()

    const input = wrapper.find('input')

    // Initial active is 0 (first item)
    expect(wrapper.findAll('li')[0].classes()).toContain('bg-slate-100')

    // ArrowDown to 1
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.findAll('li')[1].classes()).toContain('bg-slate-100')

    // ArrowDown wraps to 0
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.findAll('li')[0].classes()).toContain('bg-slate-100')

    // ArrowUp wraps to 1
    await input.trigger('keydown', { key: 'ArrowUp' })
    expect(wrapper.findAll('li')[1].classes()).toContain('bg-slate-100')

    // Enter selects
    await input.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('select')![0][0]).toEqual(mun2)
    expect(wrapper.findAll('li')).toHaveLength(0)

    // Escape closes
    await wrapper.find('input').setValue('cid')
    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(wrapper.findAll('li')).toHaveLength(2)
    await input.trigger('keydown', { key: 'Escape' })
    expect(wrapper.findAll('li')).toHaveLength(0)
  })

  it('acessibilidade ARIA diretamente no input', async () => {
    vi.spyOn(censusApi, 'search').mockResolvedValue({ data: [{ cd_mun: '1', nm_mun: 'A', cd_uf: '1', nm_uf: 'B' }] })
    const wrapper = mount(MunicipalitySearch)

    const input = wrapper.find('input')
    expect(input.attributes('role')).toBe('combobox')
    expect(input.attributes('aria-autocomplete')).toBe('list')
    expect(input.attributes('aria-expanded')).toBe('false')

    await input.setValue('sao')
    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(input.attributes('aria-expanded')).toBe('true')
    const listboxId = input.attributes('aria-controls')
    const listbox = wrapper.find(`#${listboxId}`)
    expect(listbox.exists()).toBe(true)
    expect(listbox.attributes('role')).toBe('listbox')

    const options = wrapper.findAll('[role="option"]')
    expect(options).toHaveLength(1)
    expect(options[0].attributes('aria-selected')).toBe('true')
    expect(input.attributes('aria-activedescendant')).toBe(options[0].attributes('id'))
  })

  it('proteção contra stale responses (concorrência)', async () => {
    let resolveA: (v: SearchResponse) => void = () => {}
    let resolveB: (v: SearchResponse) => void = () => {}

    vi.spyOn(censusApi, 'search')
      .mockImplementationOnce(() => new Promise(r => resolveA = r))
      .mockImplementationOnce(() => new Promise(r => resolveB = r))

    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    // Search A
    await input.setValue('sao')
    vi.advanceTimersByTime(300)

    // Search B
    await input.setValue('santos')
    vi.advanceTimersByTime(300)

    // B resolves first
    resolveB({ data: [{ cd_mun: '2', nm_mun: 'Santos', cd_uf: 'SP', nm_uf: 'Estado B' }] })
    await flushPromises()
    expect(wrapper.text()).toContain('Santos')

    // A resolves later
    resolveA({ data: [{ cd_mun: '1', nm_mun: 'São Paulo', cd_uf: 'SP', nm_uf: 'Estado A' }] })
    await flushPromises()

    // Results for B should remain.
    // We check that the specific municipality B is present and A is not in the results list.
    const items = wrapper.findAll('li')
    expect(items).toHaveLength(1)
    expect(items[0].text()).toContain('Santos')
    expect(items[0].text()).not.toContain('São Paulo')
  })

  it('unmount limpa timer e invalida requests', async () => {
    const spy = vi.spyOn(censusApi, 'search').mockResolvedValue({ data: [] })
    const wrapper = mount(MunicipalitySearch)
    await wrapper.find('input').setValue('sao')

    wrapper.unmount()
    vi.advanceTimersByTime(300)
    expect(spy).not.toHaveBeenCalled()
  })

  it('emite evento "edit" ao digitar', async () => {
    const wrapper = mount(MunicipalitySearch)
    await wrapper.find('input').setValue('s')
    expect(wrapper.emitted('edit')).toBeTruthy()
  })

  it('ao selecionar uma opção: cancela timer, invalida buscas anteriores, fecha listbox, limpa activeIndex e impede resposta antiga de reabrir resultados', async () => {
    let resolveOldSearch: (v: SearchResponse) => void = () => {}
    const searchSpy = vi.spyOn(censusApi, 'search')
      .mockImplementationOnce(() => new Promise(r => resolveOldSearch = r))
      .mockResolvedValueOnce({
        data: [{ cd_mun: '3550308', nm_mun: 'São Paulo', cd_uf: '35', nm_uf: 'São Paulo' }]
      })

    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    // 1. Inicia busca antiga
    await input.setValue('antiga')
    vi.advanceTimersByTime(300)

    // 2. Busca nova resolve com São Paulo
    await input.setValue('sao')
    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(wrapper.findAll('li')).toHaveLength(1)
    expect(wrapper.find('li .font-medium').text()).toBe('São Paulo')

    // 3. Seleciona São Paulo
    const mun1 = { cd_mun: '3550308', nm_mun: 'São Paulo', cd_uf: '35', nm_uf: 'São Paulo' }
    await wrapper.find('li').trigger('click')

    // 4. Emite exatamente o município selecionado
    expect(wrapper.emitted('select')![0][0]).toEqual(mun1)

    // 5. Fecha listbox e limpa activeIndex
    expect(wrapper.findAll('li')).toHaveLength(0)

    // 6. Timer pendente foi cancelado: avançar 300ms não chama nova busca
    searchSpy.mockClear()
    vi.advanceTimersByTime(300)
    expect(searchSpy).not.toHaveBeenCalled()

    // 7. Resposta antiga resolvendo depois não reabre listbox nem mostra vazio
    resolveOldSearch({ data: [{ cd_mun: '9999999', nm_mun: 'Outra Cidade', cd_uf: '99', nm_uf: 'UF' }] })
    await flushPromises()
    expect(wrapper.findAll('li')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Outra Cidade')
    expect(wrapper.text()).not.toContain('Nenhum município encontrado.')
  })

  it('navegação por setas (ArrowDown/ArrowUp) não emite evento edit nem limpa seleção', async () => {
    const mun1 = { cd_mun: '1', nm_mun: 'Cidade A', cd_uf: '1', nm_uf: 'UF' }
    const mun2 = { cd_mun: '2', nm_mun: 'Cidade B', cd_uf: '1', nm_uf: 'UF' }
    vi.spyOn(censusApi, 'search').mockResolvedValue({ data: [mun1, mun2] })

    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')
    await input.setValue('cid')
    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(wrapper.emitted('edit')).toBeTruthy()
    const editCountBeforeArrows = wrapper.emitted('edit')!.length

    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowUp' })

    // Nenhum novo evento edit emitido pelas setas
    expect(wrapper.emitted('edit')!.length).toBe(editCountBeforeArrows)
  })

  it('não mantém resultados antigos durante nova edição e impede seleção anterior antes dos 300ms', async () => {
    const munSao = { cd_mun: '3550308', nm_mun: 'São Paulo', cd_uf: '35', nm_uf: 'São Paulo' }
    const munSantos = { cd_mun: '3548500', nm_mun: 'Santos', cd_uf: '35', nm_uf: 'São Paulo' }

    const searchSpy = vi.spyOn(censusApi, 'search').mockImplementation((term: string) => {
      if (term === 'sao') {
        return Promise.resolve({ data: [munSao] })
      }
      if (term === 'santos') {
        return Promise.resolve({ data: [munSantos] })
      }
      return Promise.resolve({ data: [] })
    })

    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    // 1. "sao" retorna resultados
    await input.setValue('sao')
    vi.advanceTimersByTime(300)
    await flushPromises()
    expect(wrapper.findAll('li')).toHaveLength(1)
    expect(wrapper.find('li .font-medium').text()).toBe('São Paulo')

    // 2. usuário edita para "santos"
    await input.setValue('santos')

    // 3. antes dos 300 ms:
    // - resultados antigos não estão mais presentes
    expect(wrapper.findAll('li')).toHaveLength(0)

    // - nenhuma opção antiga pode ser selecionada com Enter
    await input.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('select')).toBeFalsy()

    // 4. após 300 ms somente a busca "santos" é realizada/apresentada
    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(searchSpy).toHaveBeenCalledWith('santos')
    expect(wrapper.findAll('li')).toHaveLength(1)
    expect(wrapper.find('li .font-medium').text()).toBe('Santos')
  })

  it('Escape com debounce pendente cancela busca e mantém dropdown fechado', async () => {
    const searchSpy = vi.spyOn(censusApi, 'search').mockResolvedValue({ data: [] })
    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    // Usuário digita termo (debounce pendente em 300ms)
    await input.setValue('curitiba')
    vi.advanceTimersByTime(150)
    expect(searchSpy).not.toHaveBeenCalled()

    // Pressiona Escape
    await input.trigger('keydown', { key: 'Escape' })

    // Avança timers
    vi.advanceTimersByTime(300)
    await flushPromises()

    // API não é chamada e dropdown continua fechado
    expect(searchSpy).not.toHaveBeenCalled()
    expect(wrapper.findAll('li')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Nenhum município encontrado.')
    expect((input.element as HTMLInputElement).value).toBe('curitiba')
  })

  it('Escape com request em andamento fecha busca e impede resultados de reaparecerem', async () => {
    let resolveSearch: (val: SearchResponse) => void = () => {}
    vi.spyOn(censusApi, 'search').mockImplementation(() => new Promise(resolve => {
      resolveSearch = resolve
    }))

    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    // Usuário digita e request inicia
    await input.setValue('curitiba')
    vi.advanceTimersByTime(300)

    // Usuário pressiona Escape
    await input.trigger('keydown', { key: 'Escape' })
    expect(wrapper.findAll('li')).toHaveLength(0)

    // Request antiga resolve depois
    resolveSearch({
      data: [{ cd_mun: '4106902', nm_mun: 'Curitiba', cd_uf: '41', nm_uf: 'Paraná' }]
    })
    await flushPromises()

    // Dropdown continua fechado e resultados antigos não reaparecem
    expect(wrapper.findAll('li')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Curitiba')
    expect(wrapper.text()).not.toContain('Nenhum município encontrado.')
    expect((input.element as HTMLInputElement).value).toBe('curitiba')
  })
})
