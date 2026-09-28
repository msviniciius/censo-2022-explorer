import { afterEach, describe, expect, it, vi } from 'vitest'
import { censusApi, CensusHttpError } from './census'

afterEach(() => vi.unstubAllGlobals())

describe('censusApi: respostas e falhas', () => {
  it('preserva sucesso vazio e sucesso com dados', async () => {
    const data = [{ cd_mun: '0100015', nm_mun: 'Cidade', cd_uf: '01', nm_uf: 'Estado' }]
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(new Response(JSON.stringify({ data: [] })))
      .mockResolvedValueOnce(new Response(JSON.stringify({ data })))
    vi.stubGlobal('fetch', fetchMock)
    expect(await censusApi.search('vazio')).toEqual({ data: [] })
    expect(await censusApi.search("%_!'\\")).toEqual({ data })
    expect(fetchMock).toHaveBeenLastCalledWith(
      `/api/municipios?${new URLSearchParams({ q: "%_!'\\" })}`,
      { headers: { Accept: 'application/json' } },
    )
  })

  it.each([422, 503])('preserva HTTP %i na busca sem retornar vazio ou repetir a chamada', async (status) => {
    const fetchMock = vi.fn().mockResolvedValue(new Response('{}', { status }))
    vi.stubGlobal('fetch', fetchMock)
    await expect(censusApi.search('cidade')).rejects.toMatchObject({ status, name: 'CensusHttpError' })
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it.each([404, 503])('preserva HTTP %i nos detalhes e o código textual', async (status) => {
    const fetchMock = vi.fn().mockResolvedValue(new Response('{}', { status }))
    vi.stubGlobal('fetch', fetchMock)
    await expect(censusApi.getDetails('0100015')).rejects.toMatchObject({ status, name: CensusHttpError.name })
    expect(fetchMock).toHaveBeenCalledExactlyOnceWith('/api/municipios/0100015', {
      headers: { Accept: 'application/json' },
    })
  })

  it('preserva falha de rede distinta de HTTP sem retry automático', async () => {
    const failure = new TypeError('Network unavailable')
    const fetchMock = vi.fn().mockRejectedValue(failure)
    vi.stubGlobal('fetch', fetchMock)
    await expect(censusApi.search('cidade')).rejects.toBe(failure)
    await expect(censusApi.getDetails('0100015')).rejects.toBe(failure)
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })
})
describe('censusApi: contratos estaduais', () => {
  it('lista as UFs em /api/ufs preservando códigos textuais e o Distrito Federal', async () => {
    const data = [
      { cd_uf: '53', nm_uf: 'Distrito Federal' },
      { cd_uf: '01', nm_uf: 'Acre' },
    ]
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data })))
    vi.stubGlobal('fetch', fetchMock)

    await expect(censusApi.listStates()).resolves.toEqual({ data })
    expect(fetchMock).toHaveBeenCalledExactlyOnceWith('/api/ufs', {
      headers: { Accept: 'application/json' },
    })
  })

  it('carrega detalhes estaduais em /api/ufs/{cd_uf} preservando o código textual', async () => {
    const payload = {
      data: {
        cd_uf: '01',
        nm_uf: 'Acre',
        agregados: {
          populacao: 830018,
          area_km2: 164123.0,
          densidade_hab_km2: 5.0571,
          total_setores: 1,
          distribuicao: {
            total: 1,
            urban: 1,
            rural: 0,
            unclassified: 0,
            urban_pct: 100,
            rural_pct: 0,
            unclassified_pct: 0,
          },
          homens: {
            valor: 400000,
            percentual: 50,
            setores_com_valor: 1,
            total_setores: 1,
            estado: 'completo',
          },
          mulheres: {
            valor: 400000,
            percentual: 50,
            setores_com_valor: 1,
            total_setores: 1,
            estado: 'completo',
          },
        },
        setores_sem_municipio_identificavel: 2,
      },
    }
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify(payload)))
    vi.stubGlobal('fetch', fetchMock)

    await expect(censusApi.getStateDetails('01')).resolves.toEqual(payload)
    expect(fetchMock).toHaveBeenCalledExactlyOnceWith('/api/ufs/01', {
      headers: { Accept: 'application/json' },
    })
  })

  it('carrega o ranking enviando page e per_page ao servidor', async () => {
    const payload = {
      data: [
        {
          posicao: 26,
          cd_mun: '4300001',
          nm_mun: 'Município Válido',
          populacao: 10,
          area_km2: 1.5,
          densidade_hab_km2: 6.6666,
        },
      ],
      meta: { current_page: 2, per_page: 25, total: 55, last_page: 3 },
    }
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify(payload)))
    vi.stubGlobal('fetch', fetchMock)

    await expect(censusApi.getStateRanking('43', 2, 25)).resolves.toEqual(payload)
    expect(fetchMock).toHaveBeenCalledExactlyOnceWith('/api/ufs/43/municipios?page=2&per_page=25', {
      headers: { Accept: 'application/json' },
    })
  })

  it.each([404, 422, 503])(
    'preserva HTTP %i nos contratos estaduais sem repetir chamadas',
    async (status) => {
      const fetchMock = vi.fn().mockResolvedValue(new Response('{}', { status }))
      vi.stubGlobal('fetch', fetchMock)

      await expect(censusApi.listStates()).rejects.toMatchObject({
        status,
        name: CensusHttpError.name,
      })
      await expect(censusApi.getStateDetails('43')).rejects.toMatchObject({
        status,
        name: CensusHttpError.name,
      })
      await expect(censusApi.getStateRanking('43', 1, 100)).rejects.toMatchObject({
        status,
        name: CensusHttpError.name,
      })
      expect(fetchMock).toHaveBeenCalledTimes(3)
    },
  )

  it('preserva falha de rede nos contratos estaduais sem retry automático', async () => {
    const failure = new TypeError('Network unavailable')
    const fetchMock = vi.fn().mockRejectedValue(failure)
    vi.stubGlobal('fetch', fetchMock)

    await expect(censusApi.listStates()).rejects.toBe(failure)
    await expect(censusApi.getStateDetails('43')).rejects.toBe(failure)
    await expect(censusApi.getStateRanking('43', 1, 25)).rejects.toBe(failure)
    expect(fetchMock).toHaveBeenCalledTimes(3)
  })
})
