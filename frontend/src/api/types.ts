export interface Municipality {
  cd_mun: string;
  nm_mun: string;
  cd_uf: string;
  nm_uf: string;
}

export interface DemographicMetric {
  valor: number | null;
  percentual: number | null;
  setores_com_valor: number;
  total_setores: number;
  estado: 'completo' | 'parcial' | 'indisponivel';
}

export interface SectorDistribution {
  total: number;
  urban: number;
  rural: number;
  unclassified: number;
  urban_pct: number | null;
  rural_pct: number | null;
  unclassified_pct: number | null;
}

export interface CensusAggregates {
  populacao: number | null;
  area_km2: number | null;
  densidade_hab_km2: number | null;
  total_setores: number;
  distribuicao: SectorDistribution;
  homens: DemographicMetric;
  mulheres: DemographicMetric;
}

export interface MunicipalityDetails extends Municipality {
  agregados: CensusAggregates;
}

export interface SearchResponse {
  data: Municipality[];
}

export interface DetailsResponse {
  data: MunicipalityDetails;
}

/** Tamanhos de página aceitos pelo contrato de paginação estadual. */
export const PAGE_SIZE_OPTIONS = [25, 50, 100] as const;

export type PageSizeOption = (typeof PAGE_SIZE_OPTIONS)[number];

export const DEFAULT_PAGE_SIZE: PageSizeOption = 25;

export interface StateSummary {
  cd_uf: string;
  nm_uf: string;
}

export interface StateDetails extends StateSummary {
  agregados: CensusAggregates;
  setores_sem_municipio_identificavel: number;
}

export interface RankingEntry {
  posicao: number;
  cd_mun: string;
  nm_mun: string;
  populacao: number | null;
  area_km2: number | null;
  densidade_hab_km2: number | null;
}

export interface PaginationMeta {
  current_page: number;
  per_page: PageSizeOption;
  total: number;
  last_page: number;
}

export interface StateListResponse {
  data: StateSummary[];
}

export interface StateDetailsResponse {
  data: StateDetails;
}

export interface StateRankingResponse {
  data: RankingEntry[];
  meta: PaginationMeta;
}
