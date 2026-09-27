<template>
  <div class="relative">
    <label for="search" class="block text-sm font-medium text-slate-700 mb-1">Buscar Município</label>
    <input
      id="search"
      type="text"
      v-model="query"
      @input="handleInput"
      placeholder="Digite o nome do município..."
      class="w-full px-4 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-slate-900"
    />
    
    <div v-if="results.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-md shadow-lg max-h-60 overflow-auto">
      <ul class="py-1">
        <li
          v-for="mun in results"
          :key="mun.cd_mun"
          @click="select(mun)"
          class="px-4 py-2 hover:bg-slate-100 cursor-pointer text-slate-900"
        >
          <span class="font-medium">{{ mun.nm_mun }}</span>
          <span class="ml-2 text-sm text-slate-500">{{ mun.nm_uf }}</span>
        </li>
      </ul>
    </div>
    
    <div v-else-if="query.trim().length >= 2 && !loading" class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-md shadow-lg p-4 text-sm text-slate-500">
      Nenhum município encontrado.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { censusApi } from '../api/census'
import type { Municipality } from '../api/types'

const emit = defineEmits<{
  (e: 'select', mun: Municipality): void
}>()

const query = ref('')
const results = ref<Municipality[]>([])
const loading = ref(false)

async function handleInput() {
  const trimmed = query.value.trim()
  if (trimmed.length < 2) {
    results.value = []
    return
  }

  loading.value = true
  try {
    const response = await censusApi.search(trimmed)
    results.value = response.data
  } catch (err) {
    console.error(err)
    results.value = []
  } finally {
    loading.value = false
  }
}

function select(mun: Municipality) {
  query.value = mun.nm_mun
  results.value = []
  emit('select', mun)
}
</script>
