const intFormatter = new Intl.NumberFormat('pt-BR', {
  maximumFractionDigits: 0,
})

const decimalFormatter = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const areaFormatter = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 4,
  maximumFractionDigits: 4,
})

const percentFormatter = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

export const formatInt = (val: number | null): string => {
  if (val === null) return 'Indisponível'
  return intFormatter.format(val)
}

export const formatDecimal = (val: number | null): string => {
  if (val === null) return 'Indisponível'
  return decimalFormatter.format(val)
}

export const formatArea = (val: number | null): string => {
  if (val === null) return 'Indisponível'
  return areaFormatter.format(val)
}

export const formatPercent = (val: number | null): string => {
  if (val === null) return 'Indisponível'
  return `${percentFormatter.format(val)}%`
}

export const formatDensity = (val: number | null): string => {
  if (val === null) return 'Indisponível'
  return `${decimalFormatter.format(val)} hab/km²`
}
