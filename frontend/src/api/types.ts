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
