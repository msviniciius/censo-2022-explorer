<template>
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-bold text-slate-900 mb-8">Consulta Estadual</h1>

    <div class="max-w-md rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <label for="uf-select" class="block text-sm font-medium text-slate-700">
        Unidade da Federação
      </label>
      <select
        id="uf-select"
        :value="selectedUf"
        :disabled="ufsState !== 'success'"
        class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
        @change="onUfChange"
      >
        <option value="">Selecione uma UF</option>
        <option v-for="uf in ufs" :key="uf.cd_uf" :value="uf.cd_uf">{{ uf.nm_uf }}</option>
      </select>

      <p v-if="ufsState === 'loading'" role="status" class="mt-2 text-sm text-slate-500">
        Carregando unidades da federação...
      </p>
      <p v-else-if="ufsState === 'error'" role="alert" class="mt-2 text-sm text-red-800">
        Não foi possível carregar a lista de UFs.
      </p>
    </div>

    <p v-if="detailsState === 'idle'" class="mt-8 text-slate-600">
      Selecione uma UF para consultar os agregados estaduais e o ranking municipal.
    </p>

    <p v-if="detailsState === 'loading'" role="status" class="mt-8 text-slate-600">
      Carregando agregados estaduais...
    </p>

    <p v-else-if="detailsState === 'error'" role="alert" class="mt-8 text-red-800">
      Não foi possível carregar os agregados desta UF.
    </p>

    <section v-else-if="details" class="mt-8 space-y-8">
      <header>
        <h2 class="text-2xl font-bold text-slate-900">{{ details.nm_uf }}</h2>
        <p class="mt-1 text-sm text-slate-400">Código: {{ details.cd_uf }}</p>
      </header>

      <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <MetricCard label="População" :value="formatInt(details.agregados.populacao)" unit="hab" />
        <MetricCard label="Área" :value="formatArea(details.agregados.area_km2)" unit="km²" />
        <MetricCard label="Densidade" :value="formatDensity(details.agregados.densidade_hab_km2)" />
      </dl>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
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

      <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <p class="text-sm text-slate-700">
          <strong>Total de Setores:</strong> {{ formatInt(details.agregados.total_setores) }}
        </p>
      </div>

      <p
        v-if="hasUnidentifiedSectors"
        class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
      >
        {{ formatInt(details.setores_sem_municipio_identificavel) }} setores sem município
        identificável estão incluídos nos totais estaduais e não aparecem no ranking municipal.
      </p>
    </section>

    <section v-if="showRankingSection" class="mt-12">
      <h2 class="mb-4 text-2xl font-bold text-slate-900">Ranking municipal</h2>

      <p v-if="rankingState === 'loading'" role="status" class="text-slate-600">
        Carregando ranking municipal...
      </p>
      <p v-else-if="rankingState === 'error'" role="alert" class="text-red-800">
        Não foi possível carregar o ranking municipal desta UF.
      </p>
      <template v-if="rankingState !== 'error'">
        <p
          v-if="meta && meta.total === 0"
          class="rounded-lg border border-slate-200 bg-white p-4 text-slate-600"
        >
          Nenhum município elegível para o ranking nesta UF.
        </p>
        <StateRanking v-else-if="meta" :entries="ranking" />

        <nav
          aria-label="Paginação do ranking municipal"
          class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
          <p v-if="meta" class="text-sm text-slate-600">
            Página {{ meta.current_page }} de {{ meta.last_page }} ·
            {{ formatInt(meta.total) }} municípios
          </p>
          <p v-else class="text-sm text-slate-600">Carregando paginação...</p>

          <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
              <label for="page-size-select" class="text-sm text-slate-700">
                Municípios por página
              </label>
              <select
                id="page-size-select"
                :value="pageSize"
                :disabled="isRankingLoading"
                class="rounded border border-slate-300 bg-white px-2 py-1 text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                @change="onPageSizeChange"
              >
                <option v-for="option in PAGE_SIZE_OPTIONS" :key="option" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>

            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="!canGoPrevious"
                class="rounded border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                @click="goToPreviousPage"
              >
                Anterior
              </button>
              <button
                type="button"
                :disabled="!canGoNext"
                class="rounded border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                @click="goToNextPage"
              >
                Próxima
              </button>
            </div>
          </div>
        </nav>
      </template>
    </section>
  </main>
</template>
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { censusApi } from '../api/census'
import {
  DEFAULT_PAGE_SIZE,
  PAGE_SIZE_OPTIONS,
  type PageSizeOption,
  type PaginationMeta,
  type RankingEntry,
  type StateDetails,
  type StateSummary,
} from '../api/types'
import { formatArea, formatDensity, formatInt } from '../utils/formatters'
import MetricCard from '../components/MetricCard.vue'
import SectorDistribution from '../components/SectorDistribution.vue'
import SexDistribution from '../components/SexDistribution.vue'
import CoverageStatus from '../components/CoverageStatus.vue'
import StateRanking from '../components/StateRanking.vue'

type ListState = 'loading' | 'success' | 'error'
type PanelState = 'idle' | 'loading' | 'success' | 'error'

const ufs = ref<StateSummary[]>([])
const ufsState = ref<ListState>('loading')

const selectedUf = ref('')
const details = ref<StateDetails | null>(null)
const detailsState = ref<PanelState>('idle')

const ranking = ref<RankingEntry[]>([])
const meta = ref<PaginationMeta | null>(null)
const rankingState = ref<PanelState>('idle')
const pageSize = ref<PageSizeOption>(DEFAULT_PAGE_SIZE)

const isRankingLoading = computed(() => rankingState.value === 'loading')

const showRankingSection = computed(
  () => selectedUf.value !== '' && rankingState.value !== 'idle',
)

const currentPage = computed(() => meta.value?.current_page ?? 1)
const lastPage = computed(() => meta.value?.last_page ?? 1)

const canGoPrevious = computed(
  () => !isRankingLoading.value && meta.value !== null && currentPage.value > 1,
)
const canGoNext = computed(
  () => !isRankingLoading.value && meta.value !== null && currentPage.value < lastPage.value,
)

const hasUnidentifiedSectors = computed(
  () => (details.value?.setores_sem_municipio_identificavel ?? 0) > 0,
)

function isPageSizeOption(value: number): value is PageSizeOption {
  return (PAGE_SIZE_OPTIONS as readonly number[]).includes(value)
}

async function loadStateList() {
  ufsState.value = 'loading'
  try {
    const response = await censusApi.listStates()
    ufs.value = response.data
    ufsState.value = 'success'
  } catch {
    ufs.value = []
    ufsState.value = 'error'
  }
}

async function loadStateDetails(cdUf: string) {
  detailsState.value = 'loading'
  try {
    const response = await censusApi.getStateDetails(cdUf)
    details.value = response.data
    detailsState.value = 'success'
  } catch {
    details.value = null
    detailsState.value = 'error'
  }
}

async function loadRanking(cdUf: string, page: number) {
  rankingState.value = 'loading'
  try {
    const response = await censusApi.getStateRanking(cdUf, page, pageSize.value)
    ranking.value = response.data
    meta.value = response.meta
    rankingState.value = 'success'
  } catch {
    ranking.value = []
    meta.value = null
    rankingState.value = 'error'
  }
}

function onUfChange(event: Event) {
  const cdUf = (event.target as HTMLSelectElement).value
  selectedUf.value = cdUf

  details.value = null
  ranking.value = []
  meta.value = null

  if (cdUf === '') {
    detailsState.value = 'idle'
    rankingState.value = 'idle'
    return
  }

  void loadStateDetails(cdUf)
  void loadRanking(cdUf, 1)
}

function goToPreviousPage() {
  if (!canGoPrevious.value || selectedUf.value === '') return
  void loadRanking(selectedUf.value, currentPage.value - 1)
}

function goToNextPage() {
  if (!canGoNext.value || selectedUf.value === '') return
  void loadRanking(selectedUf.value, currentPage.value + 1)
}

function onPageSizeChange(event: Event) {
  const value = Number((event.target as HTMLSelectElement).value)
  if (!isPageSizeOption(value)) return
  pageSize.value = value
  if (selectedUf.value === '') return
  void loadRanking(selectedUf.value, 1)
}

onMounted(() => {
  void loadStateList()
})
</script>
