<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppNavigation from './components/AppNavigation.vue'
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <AppNavigation />
    <RouterView />
  </div>
</template>
