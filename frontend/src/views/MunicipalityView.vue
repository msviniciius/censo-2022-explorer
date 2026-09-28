<template>
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-3xl mx-auto">
      <h1 class="text-3xl font-bold text-slate-900 mb-8">Consulta Municipal</h1>

      <MunicipalitySearch @select="onSelect" @edit="onEdit" />

      <div v-if="state === 'loading'" role="status" class="mt-12 text-center text-slate-600">
        Carregando detalhes...
      </div>

      <div v-else-if="state === 'error' && failedDetails" role="alert" class="mt-8 text-red-800">
        <p>{{ failedDetails.message }}</p>
        <button v-if="failedDetails.retryable" type="button" @click="retryDetails"
          class="mt-2 rounded border border-red-300 px-3 py-2 font-medium focus-visible:outline-2 focus-visible:outline-offset-2">
          Tentar carregar detalhes novamente
        </button>
      </div>
      <p v-else-if="state === 'initial'" class="mt-8 text-slate-600">
        Busque um município e selecione uma sugestão para consultar os dados.
      </p>

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
import { censusApi, CensusHttpError } from '../api/census'
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
const state = ref<'initial' | 'loading' | 'success' | 'error'>('initial')
const failedDetails = ref<{ code: string; message: string; retryable: boolean } | null>(null)
let activeCode: string | null = null
let latestRequestId = 0

async function loadMunicipality(code: string) {
  if (state.value === 'loading' && activeCode === code) return
  const requestId = ++latestRequestId
  activeCode = code
  state.value = 'loading'
  failedDetails.value = null
  details.value = null
  try {
    const response = await censusApi.getDetails(code)
    if (requestId !== latestRequestId) return

    details.value = response.data
    state.value = 'success'
  } catch (err) {
    if (requestId !== latestRequestId) return
    failedDetails.value = {
      code,
      message: err instanceof CensusHttpError && err.status === 404
        ? 'Município não encontrado. Faça uma nova busca.'
        : err instanceof CensusHttpError && err.status >= 400 && err.status < 500
          ? 'Não foi possível consultar este município. Faça uma nova busca.'
          : 'Não foi possível carregar os detalhes do município. Tente novamente.',
      retryable: !(err instanceof CensusHttpError) || err.status >= 500,
    }
    state.value = 'error'
  }
}

function retryDetails() {
  const failure = failedDetails.value
  if (state.value !== 'error' || !failure?.retryable || route.query.cd !== failure.code) return
  loadMunicipality(failure.code)
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
  state.value = 'initial'
  failedDetails.value = null
  activeCode = null
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
    state.value = 'initial'
    failedDetails.value = null
    activeCode = null
    if (newCd !== undefined) {
      state.value = 'error'
      failedDetails.value = { code: '', message: 'Município não encontrado. Faça uma nova busca.', retryable: false }
    }
    latestRequestId++
  }
}, { immediate: true })

onUnmounted(() => {
  latestRequestId++
})
</script>
