<template>
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-bold text-slate-900 mb-8">Consulta Estadual</h1>

    <div
      :aria-busy="ufsState === 'loading'"
      class="max-w-md rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
    >
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
      <p v-else-if="ufsState === 'empty'" role="status" class="mt-2 text-sm text-slate-600">
        Nenhuma unidade da federação disponível.
      </p>
      <div
        v-else-if="ufsState === 'error' && failedStateList"
        role="alert"
        class="mt-2 text-sm text-red-800"
      >
        <p>{{ failedStateList.message }}</p>
        <button
          v-if="failedStateList.retryable"
          type="button"
          class="mt-2 rounded border border-red-300 px-3 py-2 font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
          @click="retryStateList"
        >
          Tentar carregar UFs novamente
        </button>
      </div>
    </div>

    <p v-if="detailsState === 'idle'" class="mt-8 text-slate-600">
      Selecione uma UF para consultar os agregados estaduais e o ranking municipal.
    </p>

    <p
      v-if="detailsState === 'loading'"
      role="status"
      aria-busy="true"
      class="mt-8 text-slate-600"
    >
      Carregando agregados estaduais...
    </p>

    <div
      v-else-if="detailsState === 'error' && failedDetails"
      role="alert"
      class="mt-8 text-red-800"
    >
      <p>{{ failedDetails.message }}</p>
      <button
        v-if="failedDetails.retryable"
        type="button"
        class="mt-2 rounded border border-red-300 px-3 py-2 font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
        @click="retryDetails"
      >
        Tentar carregar agregados novamente
      </button>
    </div>

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

    <section v-if="showRankingSection" :aria-busy="isRankingLoading" class="mt-12">
      <h2 class="mb-4 text-2xl font-bold text-slate-900">Ranking municipal</h2>

      <p v-if="rankingState === 'loading'" role="status" class="text-slate-600">
        Carregando ranking municipal...
      </p>
      <div v-else-if="rankingState === 'error' && failedRanking" role="alert" class="text-red-800">
        <p>{{ failedRanking.message }}</p>
        <button
          v-if="failedRanking.retryable"
          type="button"
          class="mt-2 rounded border border-red-300 px-3 py-2 font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
          @click="retryRanking"
        >
          Tentar carregar ranking novamente
        </button>
      </div>

      <p
        v-if="rankingState === 'empty'"
        role="status"
        class="rounded-lg border border-slate-200 bg-white p-4 text-slate-600"
      >
        Nenhum município elegível para o ranking nesta UF.
      </p>
      <StateRanking v-else-if="meta" :entries="ranking" />

      <nav
        v-if="showRankingControls"
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
    </section>
  </main>
</template>
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { censusApi, CensusHttpError } from '../api/census'
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

type ListState = 'loading' | 'empty' | 'success' | 'error'
type PanelState = 'idle' | 'loading' | 'success' | 'error'
type RankingState = PanelState | 'empty'
type Failure = { message: string; retryable: boolean }
type DetailsFailure = Failure & { cdUf: string }
type RankingFailure = Failure & { cdUf: string; page: number; perPage: PageSizeOption }

const ufs = ref<StateSummary[]>([])
const ufsState = ref<ListState>('loading')
const failedStateList = ref<Failure | null>(null)

const selectedUf = ref('')
const details = ref<StateDetails | null>(null)
const detailsState = ref<PanelState>('idle')
const failedDetails = ref<DetailsFailure | null>(null)

const ranking = ref<RankingEntry[]>([])
const meta = ref<PaginationMeta | null>(null)
const rankingState = ref<RankingState>('idle')
const failedRanking = ref<RankingFailure | null>(null)
const pageSize = ref<PageSizeOption>(DEFAULT_PAGE_SIZE)

let latestStateListRequestId = 0
let latestDetailsRequestId = 0
let latestRankingRequestId = 0
let activeDetailsUf: string | null = null
let activeRanking: { cdUf: string; page: number; perPage: PageSizeOption } | null = null

const isRankingLoading = computed(() => rankingState.value === 'loading')

const showRankingSection = computed(
  () => selectedUf.value !== '' && rankingState.value !== 'idle',
)
const showRankingControls = computed(
  () => meta.value !== null || rankingState.value === 'loading',
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

function isRetryable(error: unknown) {
  return !(error instanceof CensusHttpError) || error.status >= 500
}

function detailsError(error: unknown, cdUf: string): DetailsFailure {
  const retryable = isRetryable(error)
  return {
    cdUf,
    retryable,
    message: retryable
      ? 'Não foi possível carregar os agregados desta UF. Tente novamente.'
      : error instanceof CensusHttpError && error.status === 404
        ? 'UF não encontrada. Selecione outra UF.'
        : 'Não foi possível consultar os agregados desta UF. Selecione outra UF.',
  }
}

function rankingError(
  error: unknown,
  cdUf: string,
  page: number,
  perPage: PageSizeOption,
): RankingFailure {
  const retryable = isRetryable(error)
  return {
    cdUf,
    page,
    perPage,
    retryable,
    message: retryable
      ? 'Não foi possível carregar a página ' + page + ' do ranking municipal. Tente novamente.'
      : error instanceof CensusHttpError && error.status === 404
        ? 'UF não encontrada. Selecione outra UF.'
        : 'Não foi possível consultar o ranking municipal com os parâmetros atuais.',
  }
}

async function loadStateList() {
  if (ufsState.value === 'loading' && latestStateListRequestId > 0) return
  const requestId = ++latestStateListRequestId
  ufsState.value = 'loading'
  failedStateList.value = null
  try {
    const response = await censusApi.listStates()
    if (requestId !== latestStateListRequestId) return
    ufs.value = response.data
    ufsState.value = response.data.length === 0 ? 'empty' : 'success'
  } catch (error) {
    if (requestId !== latestStateListRequestId) return
    ufs.value = []
    const retryable = isRetryable(error)
    failedStateList.value = {
      retryable,
      message: retryable
        ? 'Não foi possível carregar a lista de UFs. Tente novamente.'
        : 'Não foi possível carregar a lista de UFs.',
    }
    ufsState.value = 'error'
  }
}

async function loadStateDetails(cdUf: string) {
  if (detailsState.value === 'loading' && activeDetailsUf === cdUf) return
  const requestId = ++latestDetailsRequestId
  activeDetailsUf = cdUf
  detailsState.value = 'loading'
  failedDetails.value = null
  try {
    const response = await censusApi.getStateDetails(cdUf)
    if (requestId !== latestDetailsRequestId || selectedUf.value !== cdUf) return
    details.value = response.data
    detailsState.value = 'success'
    activeDetailsUf = null
  } catch (error) {
    if (requestId !== latestDetailsRequestId || selectedUf.value !== cdUf) return
    details.value = null
    failedDetails.value = detailsError(error, cdUf)
    detailsState.value = 'error'
    activeDetailsUf = null
  }
}

async function loadRanking(cdUf: string, page: number, perPage: PageSizeOption) {
  if (
    rankingState.value === 'loading'
    && activeRanking?.cdUf === cdUf
    && activeRanking.page === page
    && activeRanking.perPage === perPage
  ) return
  const requestId = ++latestRankingRequestId
  activeRanking = { cdUf, page, perPage }
  rankingState.value = 'loading'
  failedRanking.value = null
  try {
    const response = await censusApi.getStateRanking(cdUf, page, perPage)
    if (
      requestId !== latestRankingRequestId
      || selectedUf.value !== cdUf
      || pageSize.value !== perPage
    ) return
    ranking.value = response.data
    meta.value = response.meta
    rankingState.value = response.meta.total === 0 ? 'empty' : 'success'
    activeRanking = null
  } catch (error) {
    if (
      requestId !== latestRankingRequestId
      || selectedUf.value !== cdUf
      || pageSize.value !== perPage
    ) return
    failedRanking.value = rankingError(error, cdUf, page, perPage)
    rankingState.value = 'error'
    activeRanking = null
  }
}

function retryStateList() {
  if (ufsState.value !== 'error' || !failedStateList.value?.retryable) return
  void loadStateList()
}

function retryDetails() {
  const failure = failedDetails.value
  if (
    detailsState.value !== 'error'
    || !failure?.retryable
    || selectedUf.value !== failure.cdUf
  ) return
  void loadStateDetails(failure.cdUf)
}

function retryRanking() {
  const failure = failedRanking.value
  if (
    rankingState.value !== 'error'
    || !failure?.retryable
    || selectedUf.value !== failure.cdUf
    || pageSize.value !== failure.perPage
  ) return
  void loadRanking(failure.cdUf, failure.page, failure.perPage)
}

function onUfChange(event: Event) {
  const cdUf = (event.target as HTMLSelectElement).value
  latestDetailsRequestId++
  latestRankingRequestId++
  activeDetailsUf = null
  activeRanking = null
  selectedUf.value = cdUf

  details.value = null
  failedDetails.value = null
  ranking.value = []
  meta.value = null
  failedRanking.value = null

  if (cdUf === '') {
    detailsState.value = 'idle'
    rankingState.value = 'idle'
    return
  }

  void loadStateDetails(cdUf)
  void loadRanking(cdUf, 1, pageSize.value)
}

function goToPreviousPage() {
  if (!canGoPrevious.value || selectedUf.value === '') return
  void loadRanking(selectedUf.value, currentPage.value - 1, pageSize.value)
}

function goToNextPage() {
  if (!canGoNext.value || selectedUf.value === '') return
  void loadRanking(selectedUf.value, currentPage.value + 1, pageSize.value)
}

function onPageSizeChange(event: Event) {
  const value = Number((event.target as HTMLSelectElement).value)
  if (!isPageSizeOption(value)) return
  pageSize.value = value
  if (selectedUf.value === '') return
  ranking.value = []
  meta.value = null
  failedRanking.value = null
  void loadRanking(selectedUf.value, 1, value)
}

onMounted(() => {
  void loadStateList()
})

onUnmounted(() => {
  latestStateListRequestId++
  latestDetailsRequestId++
  latestRankingRequestId++
})
</script>
