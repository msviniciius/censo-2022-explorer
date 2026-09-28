<template>
  <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
    <dt class="text-sm font-medium text-slate-500 uppercase tracking-wider">{{ label }}</dt>
    <dd class="mt-1 text-2xl font-semibold text-slate-900">
      {{ value }}
      <span v-if="unit && value !== NOT_AVAILABLE" class="text-sm font-normal text-slate-500 ml-1">{{ unit }}</span>
    </dd>
  </div>
</template>

<script setup lang="ts">
import { NOT_AVAILABLE } from '../utils/formatters'

defineProps<{
  label: string
  value: string
  unit?: string
}>()
</script>
