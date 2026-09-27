import type { DetailsResponse, SearchResponse } from './types'

export const censusApi = {
  async search(query: string): Promise<SearchResponse> {
    const params = new URLSearchParams({ q: query })
    const response = await fetch(`/api/municipios?${params}`, {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) {
      if (response.status === 422) {
        return { data: [] }
      }
      throw new Error('Erro ao buscar municípios')
    }
    return response.json()
  },

  async getDetails(code: string): Promise<DetailsResponse> {
    const response = await fetch(`/api/municipios/${code}`, {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) {
      throw new Error('Erro ao carregar detalhes do município')
    }
    return response.json()
  },
}
