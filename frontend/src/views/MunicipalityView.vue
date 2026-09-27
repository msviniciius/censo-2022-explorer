<template>
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-3xl mx-auto">
      <h1 class="text-3xl font-bold text-slate-900 mb-8">Consulta Municipal</h1>

      <MunicipalitySearch @select="onSelect" @edit="onEdit" />

      <div v-if="loading" class="mt-12 text-center text-slate-600">
        Carregando detalhes...
      </div>

      <div v-else-if="details" class="mt-12 space-y-8">
        <header>
          <h2 class="text-2xl font-bold text-slate-900">{{ details.nm_mun }}</h2>
          <p class="text-lg text-slate-600">{{ details.nm_uf }}</p>
          <p class="text-sm text-slate-400 mt-1">Código: {{ details.cd_mun }}</p>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <MetricCard
            label="População"
            :value="formatInt(details.agregados.populacao)"
            unit="hab"
          />
          <MetricCard
            label="Área"
            :value="formatArea(details.agregados.area_km2)"
            unit="km²"
          />
          <MetricCard
            label="Densidade"
            :value="formatDensity(details.agregados.densidade_hab_km2)"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <SectorDistribution :distribution="details.agregados.distribuicao" />
          <div class="space-y-8">
            <SexDistribution
              :homens="details.agregados.homens"
              :mulheres="details.agregados.mulheres"
            />
            <CoverageStatus
              :homens="details.agregados.homens"
              :mulheres="details.agregados.mulheres"
            />
          </div>
        </div>

        <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
          <p class="text-sm text-blue-800">
            <strong>Total de Setores:</strong> {{ formatInt(details.agregados.total_setores) }}
          </p>
        </div>
      </div>
    </div>
  </main>
</template>

<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { censusApi } from '../api/census'
import type { Municipality, MunicipalityDetails } from '../api/types'
import { formatInt, formatArea, formatDensity } from '../utils/formatters'
import MunicipalitySearch from '../components/MunicipalitySearch.vue'
import MetricCard from '../components/MetricCard.vue'
import SectorDistribution from '../components/SectorDistribution.vue'
import SexDistribution from '../components/SexDistribution.vue'
import CoverageStatus from '../components/CoverageStatus.vue'

const route = useRoute()
const router = useRouter()

const details = ref<MunicipalityDetails | null>(null)
const loading = ref(false)
let latestRequestId = 0

async function loadMunicipality(code: string) {
  const requestId = ++latestRequestId
  loading.value = true
  details.value = null
  try {
    const response = await censusApi.getDetails(code)
    // Stale response protection
    if (requestId !== latestRequestId) return

    details.value = response.data
  } catch (err) {
    if (requestId !== latestRequestId) return
    console.error(err)
    details.value = null
  } finally {
    if (requestId === latestRequestId) {
      loading.value = false
    }
  }
}

function onSelect(mun: Municipality) {
  if (route.query.cd === mun.cd_mun) {
    loadMunicipality(mun.cd_mun)
  } else {
    router.push({ name: 'municipios', query: { cd: mun.cd_mun } })
  }
}

function onEdit() {
  // Clear selection and URL on edit
  details.value = null
  loading.value = false
  latestRequestId++ // Invalidate any pending details request
  if (route.query.cd) {
    router.replace({ name: 'municipios', query: {} }).catch(() => {})
  }
}

watch(() => route.query.cd, (newCd) => {
  if (typeof newCd === 'string' && /^\d{7}$/.test(newCd)) {
    loadMunicipality(newCd)
  } else {
    details.value = null
    loading.value = false
    latestRequestId++
  }
}, { immediate: true })

onUnmounted(() => {
  latestRequestId++
})
</script>
