import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import MunicipalitySearch from './MunicipalitySearch.vue'
import { censusApi } from '../api/census'
import type { Municipality, SearchResponse } from '../api/types'

describe('MunicipalitySearch.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('termo com menos de 2 caracteres não chama a API', async () => {
    const spy = vi.spyOn(censusApi, 'search')
    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    // Empty input
    await input.setValue('')
    expect(spy).not.toHaveBeenCalled()

    // Single character
    await input.setValue('a')
    expect(spy).not.toHaveBeenCalled()

    // Whitespace with 1 char
    await input.setValue(' b ')
    expect(spy).not.toHaveBeenCalled()

    expect(wrapper.findAll('li')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Nenhum município encontrado.')
  })

  it('termo >= 2 chama censusApi.search', async () => {
    const mockResponse: SearchResponse = { data: [] }
    const spy = vi.spyOn(censusApi, 'search').mockResolvedValue(mockResponse)
    const wrapper = mount(MunicipalitySearch)
    const input = wrapper.find('input')

    await input.setValue('sa')
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('sa')

    await input.setValue('  curitiba  ')
    expect(spy).toHaveBeenCalledTimes(2)
    expect(spy).toHaveBeenCalledWith('curitiba')
  })

  it('resultados exibem município + UF', async () => {
    const mockResponse: SearchResponse = {
      data: [
        { cd_mun: '4106902', nm_mun: 'Curitiba', cd_uf: '41', nm_uf: 'Paraná' }
      ]
    }
    vi.spyOn(censusApi, 'search').mockResolvedValue(mockResponse)
    const wrapper = mount(MunicipalitySearch)

    await wrapper.find('input').setValue('cur')
    await flushPromises()

    const items = wrapper.findAll('li')
    expect(items).toHaveLength(1)
    expect(items[0].text()).toContain('Curitiba')
    expect(items[0].text()).toContain('Paraná')
  })

  it('homônimos de UFs diferentes permanecem distinguíveis', async () => {
    const mockResponse: SearchResponse = {
      data: [
        { cd_mun: '1506807', nm_mun: 'Santarém', cd_uf: '15', nm_uf: 'Pará' },
        { cd_mun: '2513903', nm_mun: 'Santarém', cd_uf: '25', nm_uf: 'Paraíba' }
      ]
    }
    vi.spyOn(censusApi, 'search').mockResolvedValue(mockResponse)
    const wrapper = mount(MunicipalitySearch)

    await wrapper.find('input').setValue('sant')
    await flushPromises()

    const items = wrapper.findAll('li')
    expect(items).toHaveLength(2)
    expect(items[0].text()).toContain('Santarém')
    expect(items[0].text()).toContain('Pará')
    expect(items[1].text()).toContain('Santarém')
    expect(items[1].text()).toContain('Paraíba')
  })

  it('seleção emite o município correto preservando cd_mun string', async () => {
    const selectedMun: Municipality = {
      cd_mun: '0100015',
      nm_mun: 'Município Zero Inicial',
      cd_uf: '01',
      nm_uf: 'Estado Teste'
    }
    const mockResponse: SearchResponse = { data: [selectedMun] }
    vi.spyOn(censusApi, 'search').mockResolvedValue(mockResponse)
    const wrapper = mount(MunicipalitySearch)

    await wrapper.find('input').setValue('zero')
    await flushPromises()

    const item = wrapper.find('li')
    await item.trigger('click')

    const emitted = wrapper.emitted('select')
    expect(emitted).toBeTruthy()
    expect(emitted).toHaveLength(1)

    const payload = emitted![0][0] as Municipality
    expect(payload).toEqual(selectedMun)
    expect(typeof payload.cd_mun).toBe('string')
    expect(payload.cd_mun).toBe('0100015')
    expect(typeof payload.cd_uf).toBe('string')
    expect(payload.cd_uf).toBe('01')

    // Input receives municipality name and dropdown closes
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Município Zero Inicial')
    expect(wrapper.findAll('li')).toHaveLength(0)
  })

  it('lista vazia apresenta o estado esperado', async () => {
    const mockResponse: SearchResponse = { data: [] }
    vi.spyOn(censusApi, 'search').mockResolvedValue(mockResponse)
    const wrapper = mount(MunicipalitySearch)

    await wrapper.find('input').setValue('termo inexistente')
    await flushPromises()

    expect(wrapper.findAll('li')).toHaveLength(0)
    expect(wrapper.text()).toContain('Nenhum município encontrado.')
  })

  it('erro básico não quebra o componente', async () => {
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.spyOn(censusApi, 'search').mockRejectedValue(new Error('Falha de rede'))
    const wrapper = mount(MunicipalitySearch)

    await wrapper.find('input').setValue('falha')
    await flushPromises()

    expect(wrapper.findAll('li')).toHaveLength(0)
    expect(wrapper.find('input').exists()).toBe(true)
    consoleSpy.mockRestore()
  })
})
