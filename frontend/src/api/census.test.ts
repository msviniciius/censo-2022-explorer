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
