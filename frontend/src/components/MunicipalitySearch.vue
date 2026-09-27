<template>
  <div class="relative">
    <label :for="inputId" class="block text-sm font-medium text-slate-700 mb-1">Buscar Município</label>
    <input
      :id="inputId"
      type="text"
      role="combobox"
      :aria-expanded="isOpen"
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

    <div v-else-if="shouldShowEmpty" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-md shadow-lg p-4 text-sm text-slate-500">
      Nenhum município encontrado.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { censusApi } from '../api/census'
import type { Municipality } from '../api/types'

const emit = defineEmits<{
  (e: 'select', mun: Municipality): void
  (e: 'edit'): void
}>()

const inputId = 'municipality-search-input'
const listboxId = 'municipality-search-results'

const query = ref('')
const results = ref<Municipality[]>([])
const loading = ref(false)
const activeIndex = ref(-1)
const hasInteracted = ref(false)
const isSelected = ref(false)

const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null)
let latestSearchId = 0

const isOpen = computed(() => results.value.length > 0)
const shouldShowEmpty = computed(() => {
  return !isSelected.value && hasInteracted.value && query.value.trim().length >= 2 && !loading.value && results.value.length === 0
})

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
  loading.value = true
  try {
    const response = await censusApi.search(searchTerm)
    if (searchId !== latestSearchId) return

    results.value = response.data
    activeIndex.value = results.value.length > 0 ? 0 : -1
  } catch (err) {
    if (searchId !== latestSearchId) return
    console.error(err)
    results.value = []
    activeIndex.value = -1
  } finally {
    if (searchId === latestSearchId) {
      loading.value = false
    }
  }
}

function onInput() {
  emit('edit')
  hasInteracted.value = true
  isSelected.value = false
  clearDebounce()
  latestSearchId++
  results.value = []
  activeIndex.value = -1
  loading.value = false

  const trimmed = query.value.trim()
  if (trimmed.length < 2) {
    return
  }

  debounceTimer.value = setTimeout(() => {
    const searchId = ++latestSearchId
    performSearch(trimmed, searchId)
  }, 300)
}

function onKeyDown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    clearDebounce()
    latestSearchId++
    results.value = []
    activeIndex.value = -1
    loading.value = false
    isSelected.value = false
    hasInteracted.value = false
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
  loading.value = false
  isSelected.value = true
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
