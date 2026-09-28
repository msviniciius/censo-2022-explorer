<template>
  <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wider mb-4">Distribuição de Setores</h3>
    <div class="space-y-4">
      <div v-for="item in items" :key="item.label" class="flex items-center justify-between">
        <div class="flex flex-col">
          <span class="text-sm font-medium text-slate-700">{{ item.label }}</span>
          <span class="text-xs text-slate-500">{{ formatInt(item.count) }} setores</span>
        </div>
        <div class="text-right">
          <span class="text-lg font-semibold text-slate-900">{{ formatPercent(item.pct) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { SectorDistribution } from '../api/types'
import { formatInt, formatPercent } from '../utils/formatters'

const props = defineProps<{
  distribution: SectorDistribution
}>()

const items = computed(() => [
  { label: 'Urbano', count: props.distribution.urban, pct: props.distribution.urban_pct },
  { label: 'Rural', count: props.distribution.rural, pct: props.distribution.rural_pct },
  { label: 'Não informada', count: props.distribution.unclassified, pct: props.distribution.unclassified_pct },
])
</script>
