<template>
  <nav class="border-b border-slate-200 bg-white" aria-label="Navegação principal">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-2 py-3 sm:h-16 sm:flex-row sm:items-center sm:gap-6 sm:py-0">
        <span class="text-xl font-bold text-slate-900">Census Explorer</span>
        <div class="flex flex-wrap gap-4 sm:gap-6">
          <RouterLink
            to="/municipios"
            class="inline-flex items-center border-b-2 py-1 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            :class="
              $route.name === 'municipios'
                ? 'border-blue-500 text-slate-900'
                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
            "
          >
            Municípios
          </RouterLink>
          <RouterLink
            to="/estados"
            class="inline-flex items-center border-b-2 py-1 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            :class="
              $route.name === 'estados'
                ? 'border-blue-500 text-slate-900'
                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
            "
          >
            Estados
          </RouterLink>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>
