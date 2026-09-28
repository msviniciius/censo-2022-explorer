<template>
  <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-4">Distribuição por Sexo (Valores Conhecidos)</h3>
    <div class="space-y-4">
      <div v-for="item in items" :key="item.label" class="flex items-center justify-between">
        <div class="flex flex-col">
          <span class="text-sm font-medium text-slate-700">{{ item.label }}</span>
          <span v-if="item.metric.valor !== null" class="text-xs text-slate-500">{{ formatInt(item.metric.valor) }} residentes</span>
        </div>
        <div class="text-right">
          <span class="text-lg font-semibold text-slate-900">{{ formatPercent(item.metric.percentual) }}</span>
        </div>
      </div>
    </div>

    <div class="mt-4 pt-4 border-t border-slate-100">
      <div v-if="hasPartial" class="text-xs text-amber-700 bg-amber-50 p-2 rounded">
        Atenção: A cobertura demográfica é parcial para homens ou mulheres.
      </div>
      <div v-else-if="hasUnavailable" class="text-xs text-red-700 bg-red-50 p-2 rounded">
        Atenção: Dados demográficos não disponíveis para este território.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { DemographicMetric } from '../api/types'
import { formatInt, formatPercent } from '../utils/formatters'

const props = defineProps<{
  homens: DemographicMetric
  mulheres: DemographicMetric
}>()

const items = computed(() => [
  { label: 'Homens', metric: props.homens },
  { label: 'Mulheres', metric: props.mulheres },
])

const hasPartial = computed(() => props.homens.estado === 'parcial' || props.mulheres.estado === 'parcial')
const hasUnavailable = computed(() => props.homens.estado === 'indisponivel' || props.mulheres.estado === 'indisponivel')
</script>
