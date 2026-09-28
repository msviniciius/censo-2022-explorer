import type { DetailsResponse, SearchResponse } from './types'

export class CensusHttpError extends Error {
  constructor(public readonly status: number, message: string) {
    super(message)
    this.name = 'CensusHttpError'
  }
}

export const censusApi = {
  async search(query: string): Promise<SearchResponse> {
    const params = new URLSearchParams({ q: query })
    const response = await fetch(`/api/municipios?${params}`, {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) {
      throw new CensusHttpError(response.status, 'Erro ao buscar municípios')
    }
    return response.json()
  },

  async getDetails(code: string): Promise<DetailsResponse> {
    const response = await fetch(`/api/municipios/${code}`, {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) {
      throw new CensusHttpError(response.status, 'Erro ao carregar detalhes do município')
    }
    return response.json()
  },
}
