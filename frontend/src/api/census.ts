import type {
  DetailsResponse,
  PageSizeOption,
  SearchResponse,
  StateDetailsResponse,
  StateListResponse,
  StateRankingResponse,
} from './types'

const JSON_HEADERS = { headers: { Accept: 'application/json' } } as const

export class CensusHttpError extends Error {
  constructor(public readonly status: number, message: string) {
    super(message)
    this.name = 'CensusHttpError'
  }
}

async function getJson<T>(path: string, failureMessage: string): Promise<T> {
  const response = await fetch(path, JSON_HEADERS)
  if (!response.ok) {
    throw new CensusHttpError(response.status, failureMessage)
  }
  return response.json()
}

export const censusApi = {
  async search(query: string): Promise<SearchResponse> {
    const params = new URLSearchParams({ q: query })
    return getJson<SearchResponse>(`/api/municipios?${params}`, 'Erro ao buscar municípios')
  },

  async getDetails(code: string): Promise<DetailsResponse> {
    return getJson<DetailsResponse>(`/api/municipios/${code}`, 'Erro ao carregar detalhes do município')
  },

  async listStates(): Promise<StateListResponse> {
    return getJson<StateListResponse>('/api/ufs', 'Erro ao carregar a lista de UFs')
  },

  async getStateDetails(cdUf: string): Promise<StateDetailsResponse> {
    return getJson<StateDetailsResponse>(`/api/ufs/${cdUf}`, 'Erro ao carregar os dados da UF')
  },

  async getStateRanking(
    cdUf: string,
    page: number,
    perPage: PageSizeOption,
  ): Promise<StateRankingResponse> {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(perPage),
    })
    return getJson<StateRankingResponse>(
      `/api/ufs/${cdUf}/municipios?${params}`,
      'Erro ao carregar o ranking municipal',
    )
  },
}
