<template>
  <div class="relative">
    <label :for="inputId" class="block text-sm font-medium text-slate-700 mb-1">Buscar Município</label>
    <input
      :id="inputId"
      type="text"
      role="combobox"
      :aria-expanded="isOpen"
      :aria-busy="state === 'loading'"
      aria-haspopup="listbox"
      :aria-controls="listboxId"
      aria-autocomplete="list"
      :aria-activedescendant="activeOptionId"
      v-model="query"
      @input="onInput"
      @keydown="onKeyDown"
      placeholder="Digite o nome do município..."
      class="w-full px-4 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-slate-900"
    />

    <div v-if="isOpen" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-md shadow-lg max-h-60 overflow-auto">
      <ul
        :id="listboxId"
        role="listbox"
        class="py-1"
      >
        <li
          v-for="(mun, index) in results"
          :id="getOptionId(index)"
          :key="mun.cd_mun"
          role="option"
          :aria-selected="index === activeIndex"
          @click="select(mun)"
          @mouseenter="activeIndex = index"
          class="px-4 py-2 hover:bg-slate-100 cursor-pointer text-slate-900"
          :class="{ 'bg-slate-100': index === activeIndex }"
        >
          <span class="font-medium">{{ mun.nm_mun }}</span>
          <span class="ml-2 text-sm text-slate-500">{{ mun.nm_uf }}</span>
        </li>
      </ul>
    </div>

    <div v-else-if="state === 'empty'" role="status" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-md shadow-lg p-4 text-sm text-slate-500">
      Nenhum município encontrado.
    </div>
    <p v-if="state === 'initial' && query.trim().length < 2" class="mt-2 text-sm text-slate-600">
      Digite pelo menos dois caracteres para buscar.
    </p>
    <p v-else-if="state === 'debouncing' || state === 'loading'" role="status" class="mt-2 text-sm text-slate-600">
      {{ state === 'debouncing' ? 'Aguardando digitação...' : 'Buscando municípios...' }}
    </p>
    <div v-else-if="state === 'error' && failedSearch" role="alert" class="mt-2 text-sm text-red-800">
      <p>{{ failedSearch.message }}</p>
      <button v-if="failedSearch.retryable" type="button" @click="retrySearch"
        class="mt-2 rounded border border-red-300 px-3 py-2 font-medium focus-visible:outline-2 focus-visible:outline-offset-2">
        Tentar buscar novamente
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { censusApi, CensusHttpError } from '../api/census'
import type { Municipality } from '../api/types'

const emit = defineEmits<{
  (e: 'select', mun: Municipality): void
  (e: 'edit'): void
}>()

const inputId = 'municipality-search-input'
const listboxId = 'municipality-search-results'

const query = ref('')
const results = ref<Municipality[]>([])
const state = ref<'initial' | 'debouncing' | 'loading' | 'success' | 'empty' | 'error'>('initial')
const failedSearch = ref<{ term: string; message: string; retryable: boolean } | null>(null)
const activeIndex = ref(-1)

const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null)
let latestSearchId = 0

const isOpen = computed(() => results.value.length > 0)
const activeOptionId = computed(() => {
  if (activeIndex.value >= 0 && results.value.length > 0) {
    return getOptionId(activeIndex.value)
  }
  return undefined
})

function getOptionId(index: number) {
  return `option-${index}`
}

function clearDebounce() {
  if (debounceTimer.value) {
    clearTimeout(debounceTimer.value)
    debounceTimer.value = null
  }
}

async function performSearch(searchTerm: string, searchId: number) {
  state.value = 'loading'
  failedSearch.value = null
  try {
    const response = await censusApi.search(searchTerm)
    if (searchId !== latestSearchId) return

    results.value = response.data
    activeIndex.value = results.value.length > 0 ? 0 : -1
    state.value = results.value.length > 0 ? 'success' : 'empty'
  } catch (err) {
    if (searchId !== latestSearchId) return
    results.value = []
    activeIndex.value = -1
    failedSearch.value = {
      term: searchTerm,
      message: err instanceof CensusHttpError && err.status === 422
        ? 'Busca inválida. Edite o texto e use no máximo 100 caracteres.'
        : 'Não foi possível buscar municípios. Tente novamente ou edite a busca.',
      retryable: !(err instanceof CensusHttpError) || err.status >= 500,
    }
    state.value = 'error'
  }
}

function retrySearch() {
  const failure = failedSearch.value
  if (state.value !== 'error' || !failure?.retryable || query.value.trim() !== failure.term) return
  clearDebounce()
  performSearch(failure.term, ++latestSearchId)
}

function onInput() {
  emit('edit')
  clearDebounce()
  latestSearchId++
  results.value = []
  activeIndex.value = -1
  failedSearch.value = null
  state.value = 'initial'

  const trimmed = query.value.trim()
  if (trimmed.length < 2) return

  state.value = 'debouncing'
  debounceTimer.value = setTimeout(() => {
    debounceTimer.value = null
    performSearch(trimmed, ++latestSearchId)
  }, 300)
}

function onKeyDown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    clearDebounce()
    latestSearchId++
    results.value = []
    activeIndex.value = -1
    state.value = 'initial'
    failedSearch.value = null
    return
  }

  if (!isOpen.value) return

  switch (e.key) {
    case 'ArrowDown':
      e.preventDefault()
      activeIndex.value = (activeIndex.value + 1) % results.value.length
      break
    case 'ArrowUp':
      e.preventDefault()
      activeIndex.value = (activeIndex.value - 1 + results.value.length) % results.value.length
      break
    case 'Enter':
      e.preventDefault()
      if (activeIndex.value >= 0) {
        select(results.value[activeIndex.value])
      }
      break
  }
}

function select(mun: Municipality) {
  clearDebounce()
  latestSearchId++
  state.value = 'initial'
  failedSearch.value = null
  query.value = mun.nm_mun
  results.value = []
  activeIndex.value = -1
  emit('select', mun)
}

onUnmounted(() => {
  clearDebounce()
  latestSearchId++
})
</script>
