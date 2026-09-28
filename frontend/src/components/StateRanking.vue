<template>
  <div
    class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
    tabindex="0"
    role="region"
    aria-label="Ranking municipal por densidade demográfica"
  >
    <table class="min-w-full divide-y divide-slate-200 text-sm">
      <caption class="sr-only">
        Ranking municipal por densidade demográfica, ordenado pelo servidor
      </caption>
      <thead class="bg-slate-50">
        <tr>
          <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
            Posição
          </th>
          <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
            Código
          </th>
          <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
            Município
          </th>
          <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
            População (hab)
          </th>
          <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
            Área (km²)
          </th>
          <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
            Densidade (hab/km²)
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 bg-white">
        <tr v-for="entry in entries" :key="entry.cd_mun">
          <th scope="row" class="px-3 py-2 text-left font-medium tabular-nums text-slate-900">
            {{ entry.posicao }}
          </th>
          <td class="px-3 py-2 tabular-nums text-slate-600">{{ entry.cd_mun }}</td>
          <td class="px-3 py-2 text-slate-900">{{ entry.nm_mun }}</td>
          <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatInt(entry.populacao) }}</td>
          <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ formatArea(entry.area_km2) }}</td>
          <td class="px-3 py-2 text-right tabular-nums text-slate-700">
            {{ formatDecimal(entry.densidade_hab_km2) }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import type { RankingEntry } from '../api/types'
import { formatArea, formatDecimal, formatInt } from '../utils/formatters'

defineProps<{
  entries: RankingEntry[]
}>()
</script>
