import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import MunicipalityView from './MunicipalityView.vue'
import MunicipalitySearch from '../components/MunicipalitySearch.vue'
import { censusApi } from '../api/census'
import type { DetailsResponse, Municipality } from '../api/types'

function createTestRouter() {
  return createRouter({
    history: createWebHistory(),
    routes: [{ path: '/municipios', name: 'municipios', component: MunicipalityView }]
  })
}

describe('MunicipalityView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('estado inicial sem município selecionado', async () => {
    const router = createTestRouter()
    router.push('/municipios')
    await router.isReady()

    const wrapper = mount(MunicipalityView, {
      global: { plugins: [router] }
    })

    expect(wrapper.text()).toContain('Consulta Municipal')
    expect(wrapper.findComponent(MunicipalitySearch).exists()).toBe(true)
    expect(wrapper.text()).not.toContain('População')
    expect(wrapper.text()).not.toContain('Carregando detalhes...')
  })

  it('seleção de município leva ao cd_mun correto', async () => {
    const router = createTestRouter()
    router.push('/municipios')
    await router.isReady()

    const mockDetails = {
      data: {
        cd_mun: '1100015',
        nm_mun: "Alta Floresta D'Oeste",
        cd_uf: '11',
        nm_uf: 'Rondônia',
        agregados: {
          total_setores: 85,
          populacao: 21494,
          area_km2: 7067.1268,
          densidade_hab_km2: 3.0414,
          distribuicao: { total: 85, urban: 26, rural: 59, unclassified: 0, urban_pct: 30.59, rural_pct: 69.41, unclassified_pct: 0 },
          homens: { valor: 10744, percentual: 50.33, setores_com_valor: 67, total_setores: 85, estado: 'parcial' },
          mulheres: { valor: 10601, percentual: 49.67, setores_com_valor: 67, total_setores: 85, estado: 'parcial' }
        }
      }
    } satisfies DetailsResponse

    const spy = vi.spyOn(censusApi, 'getDetails').mockResolvedValue(mockDetails)

    const wrapper = mount(MunicipalityView, {
      global: { plugins: [router] }
    })

    const selectedMun: Municipality = {
      cd_mun: '1100015',
      nm_mun: "Alta Floresta D'Oeste",
      cd_uf: '11',
      nm_uf: 'Rondônia'
    }

    // Select municipality from search component
    const searchComp = wrapper.findComponent(MunicipalitySearch)
    searchComp.vm.$emit('select', selectedMun)

    await flushPromises()

    expect(router.currentRoute.value.query.cd).toBe('1100015')
    expect(spy).toHaveBeenCalledWith('1100015')
    expect(wrapper.text()).toContain("Alta Floresta D'Oeste")
  })

  it('carregamento por ?cd= carrega detalhes e renderiza formatação pt-BR, área, densidade, total de setores, distribuição urbano/rural/unclassified e sexo', async () => {
    const router = createTestRouter()
    const mockDetails = {
      data: {
        cd_mun: '1100015',
        nm_mun: "Alta Floresta D'Oeste",
        cd_uf: '11',
        nm_uf: 'Rondônia',
        agregados: {
          total_setores: 85,
          populacao: 21494,
          area_km2: 7067.1268,
          densidade_hab_km2: 3.0414,
          distribuicao: { total: 85, urban: 26, rural: 59, unclassified: 0, urban_pct: 30.59, rural_pct: 69.41, unclassified_pct: 0 },
          homens: { valor: 10744, percentual: 50.33, setores_com_valor: 67, total_setores: 85, estado: 'parcial' },
          mulheres: { valor: 10601, percentual: 49.67, setores_com_valor: 67, total_setores: 85, estado: 'parcial' }
        }
      }
    } satisfies DetailsResponse

    const spy = vi.spyOn(censusApi, 'getDetails').mockResolvedValue(mockDetails)

    router.push('/municipios?cd=1100015')
    await router.isReady()

    const wrapper = mount(MunicipalityView, {
      global: { plugins: [router] }
    })

    await flushPromises()

    expect(spy).toHaveBeenCalledWith('1100015')
    expect(wrapper.text()).toContain("Alta Floresta D'Oeste")
    expect(wrapper.text()).toContain('Rondônia')
    expect(wrapper.text()).toContain('Código: 1100015')

    // População formatada pt-BR
    expect(wrapper.text()).toContain('21.494 hab')

    // Área formatada pt-BR com 4 casas
    expect(wrapper.text()).toContain('7.067,1268 km²')

    // Densidade
    expect(wrapper.text()).toContain('3,04 hab/km²')

    // Total de setores
    expect(wrapper.text()).toContain('Total de Setores: 85')

    // Setores urbano / rural / unclassified
    expect(wrapper.text()).toContain('Urbano')
    expect(wrapper.text()).toContain('26 setores')
    expect(wrapper.text()).toContain('30,59%')
    expect(wrapper.text()).toContain('Rural')
    expect(wrapper.text()).toContain('59 setores')
    expect(wrapper.text()).toContain('69,41%')
    expect(wrapper.text()).toContain('Não informada')
    expect(wrapper.text()).toContain('0 setores')
    expect(wrapper.text()).toContain('0,00%')

    // Sexo
    expect(wrapper.text()).toContain('Homens')
    expect(wrapper.text()).toContain('10.744 residentes')
    expect(wrapper.text()).toContain('50,33%')
    expect(wrapper.text()).toContain('Mulheres')
    expect(wrapper.text()).toContain('10.601 residentes')
    expect(wrapper.text()).toContain('49,67%')
  })

  it('exibe alerta e badge de cobertura parcial', async () => {
    const router = createTestRouter()
    const mockDetails = {
      data: {
        cd_mun: '1100015',
        nm_mun: "Alta Floresta D'Oeste",
        cd_uf: '11',
        nm_uf: 'Rondônia',
        agregados: {
          total_setores: 85,
          populacao: 21494,
          area_km2: 7067.1268,
          densidade_hab_km2: 3.0414,
          distribuicao: { total: 85, urban: 26, rural: 59, unclassified: 0, urban_pct: 30.59, rural_pct: 69.41, unclassified_pct: 0 },
          homens: { valor: 10744, percentual: 50.33, setores_com_valor: 67, total_setores: 85, estado: 'parcial' },
          mulheres: { valor: 10601, percentual: 49.67, setores_com_valor: 67, total_setores: 85, estado: 'parcial' }
        }
      }
    } satisfies DetailsResponse

    vi.spyOn(censusApi, 'getDetails').mockResolvedValue(mockDetails)

    router.push('/municipios?cd=1100015')
    await router.isReady()

    const wrapper = mount(MunicipalityView, {
      global: { plugins: [router] }
    })

    await flushPromises()

    expect(wrapper.text()).toContain('PARCIAL')
    expect(wrapper.text()).toContain('Atenção: A cobertura demográfica é parcial para homens ou mulheres.')
    expect(wrapper.text()).toContain('Dados baseados em 67 de 85 setores.')
  })

  it('exibe alerta e badge de cobertura indisponível', async () => {
    const router = createTestRouter()
    const mockDetails = {
      data: {
        cd_mun: '2200000',
        nm_mun: 'Sem Dados',
        cd_uf: '22',
        nm_uf: 'Piauí',
        agregados: {
          total_setores: 10,
          populacao: null,
          area_km2: 500.0,
          densidade_hab_km2: null,
          distribuicao: { total: 10, urban: 5, rural: 5, unclassified: 0, urban_pct: 50.0, rural_pct: 50.0, unclassified_pct: 0 },
          homens: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 10, estado: 'indisponivel' },
          mulheres: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 10, estado: 'indisponivel' }
        }
      }
    } satisfies DetailsResponse

    vi.spyOn(censusApi, 'getDetails').mockResolvedValue(mockDetails)

    router.push('/municipios?cd=2200000')
    await router.isReady()

    const wrapper = mount(MunicipalityView, {
      global: { plugins: [router] }
    })

    await flushPromises()

    expect(wrapper.text()).toContain('INDISPONIVEL')
    expect(wrapper.text()).toContain('Atenção: Dados demográficos indisponíveis para este município.')
  })

  it('converte null para Indisponível sem unidades espúrias', async () => {
    const router = createTestRouter()
    const mockDetails = {
      data: {
        cd_mun: '9999999',
        nm_mun: 'Município Nulo',
        cd_uf: '99',
        nm_uf: 'UF Nula',
        agregados: {
          total_setores: 0,
          populacao: null,
          area_km2: null,
          densidade_hab_km2: null,
          distribuicao: { total: 0, urban: 0, rural: 0, unclassified: 0, urban_pct: null, rural_pct: null, unclassified_pct: null },
          homens: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 0, estado: 'indisponivel' },
          mulheres: { valor: null, percentual: null, setores_com_valor: 0, total_setores: 0, estado: 'indisponivel' }
        }
      }
    } satisfies DetailsResponse

    vi.spyOn(censusApi, 'getDetails').mockResolvedValue(mockDetails)

    router.push('/municipios?cd=9999999')
    await router.isReady()

    const wrapper = mount(MunicipalityView, {
      global: { plugins: [router] }
    })

    await flushPromises()

    const text = wrapper.text()
    expect(text).toContain('PopulaçãoIndisponível')
    expect(text).not.toContain('Indisponível hab')

    expect(text).toContain('ÁreaIndisponível')
    expect(text).not.toContain('Indisponível km²')

    expect(text).toContain('DensidadeIndisponível')
    expect(text).not.toContain('Indisponível hab/km²')

    expect(text).not.toContain('Indisponível residentes')
  })

  it('zero continua zero e não se torna Indisponível', async () => {
    const router = createTestRouter()
    const mockDetails = {
      data: {
        cd_mun: '3300000',
        nm_mun: 'Município Com Zeros',
        cd_uf: '33',
        nm_uf: 'Rio de Janeiro',
        agregados: {
          total_setores: 0,
          populacao: 0,
          area_km2: 0,
          densidade_hab_km2: 0,
          distribuicao: { total: 0, urban: 0, rural: 0, unclassified: 0, urban_pct: 0, rural_pct: 0, unclassified_pct: 0 },
          homens: { valor: 0, percentual: 0, setores_com_valor: 0, total_setores: 0, estado: 'completo' },
          mulheres: { valor: 0, percentual: 0, setores_com_valor: 0, total_setores: 0, estado: 'completo' }
        }
      }
    } satisfies DetailsResponse

    vi.spyOn(censusApi, 'getDetails').mockResolvedValue(mockDetails)

    router.push('/municipios?cd=3300000')
    await router.isReady()

    const wrapper = mount(MunicipalityView, {
      global: { plugins: [router] }
    })

    await flushPromises()

    const text = wrapper.text()
    expect(text).toContain('0 hab')
    expect(text).toContain('0,0000 km²')
    expect(text).toContain('0,00 hab/km²')
    expect(text).toContain('Total de Setores: 0')
    expect(text).toContain('0 residentes')
    expect(text).toContain('0,00%')
  })

  it('cd_mun e cd_uf permanecem strings preservando zeros à esquerda', async () => {
    const router = createTestRouter()
    const mockDetails = {
      data: {
        cd_mun: '0100015',
        nm_mun: 'Lugar Com Zero',
        cd_uf: '01',
        nm_uf: 'Estado Zero',
        agregados: {
          total_setores: 1,
          populacao: 50,
          area_km2: 10.0,
          densidade_hab_km2: 5.0,
          distribuicao: { total: 1, urban: 1, rural: 0, unclassified: 0, urban_pct: 100, rural_pct: 0, unclassified_pct: 0 },
          homens: { valor: 25, percentual: 50, setores_com_valor: 1, total_setores: 1, estado: 'completo' },
          mulheres: { valor: 25, percentual: 50, setores_com_valor: 1, total_setores: 1, estado: 'completo' }
        }
      }
    } satisfies DetailsResponse

    const spy = vi.spyOn(censusApi, 'getDetails').mockResolvedValue(mockDetails)

    router.push('/municipios?cd=0100015')
    await router.isReady()

    const wrapper = mount(MunicipalityView, {
      global: { plugins: [router] }
    })

    await flushPromises()

    expect(spy).toHaveBeenCalledWith('0100015')
    expect(typeof mockDetails.data.cd_mun).toBe('string')
    expect(typeof mockDetails.data.cd_uf).toBe('string')
    expect(mockDetails.data.cd_mun).toBe('0100015')
    expect(mockDetails.data.cd_uf).toBe('01')
    expect(wrapper.text()).toContain('Código: 0100015')
  })
})
