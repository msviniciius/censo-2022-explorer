const intFormatter = new Intl.NumberFormat('pt-BR', {
  maximumFractionDigits: 0,
})

const decimalFormatter = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const percentFormatter = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 1,
  maximumFractionDigits: 1,
})

/** Texto único exigido pelo OpenSpec para qualquer valor nulo. */
export const NOT_AVAILABLE = 'Não disponível'

export const formatInt = (val: number | null): string => {
  if (val === null) return NOT_AVAILABLE
  return intFormatter.format(val)
}

export const formatDecimal = (val: number | null): string => {
  if (val === null) return NOT_AVAILABLE
  return decimalFormatter.format(val)
}

export const formatArea = (val: number | null): string => {
  if (val === null) return NOT_AVAILABLE
  return decimalFormatter.format(val)
}

export const formatPercent = (val: number | null): string => {
  if (val === null) return NOT_AVAILABLE
  return `${percentFormatter.format(val)}%`
}

export const formatDensity = (val: number | null): string => {
  if (val === null) return NOT_AVAILABLE
  return `${decimalFormatter.format(val)} hab/km²`
}
