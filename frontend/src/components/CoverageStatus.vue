<template>
  <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-2">Cobertura Demográfica</h3>
    <div class="space-y-3">
      <div v-for="item in items" :key="item.label">
        <div class="flex items-center justify-between">
          <span class="text-sm text-slate-600">{{ item.label }}</span>
          <span 
            class="text-xs font-bold px-2 py-0.5 rounded uppercase"
            :class="statusClasses[item.metric.estado]"
          >
            {{ item.metric.estado.toUpperCase() }}
          </span>
        </div>
        <p v-if="item.metric.estado === 'parcial'" class="text-xs text-slate-500 mt-1">
          Dados baseados em {{ formatInt(item.metric.setores_com_valor) }} de {{ formatInt(item.metric.total_setores) }} setores.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { DemographicMetric } from '../api/types'
import { formatInt } from '../utils/formatters'

const props = defineProps<{
  homens: DemographicMetric
  mulheres: DemographicMetric
}>()

const items = computed(() => [
  { label: 'Homens', metric: props.homens },
  { label: 'Mulheres', metric: props.mulheres },
])

const statusClasses = {
  completo: 'bg-green-100 text-green-800',
  parcial: 'bg-amber-100 text-amber-800',
  indisponivel: 'bg-red-100 text-red-800',
}
</script>
