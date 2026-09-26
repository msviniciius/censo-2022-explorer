<script setup lang="ts">
import { onMounted, ref } from 'vue'

const availability = ref('Verificando conexão…')

onMounted(async () => {
  try {
    const response = await fetch('/api/health', { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('Serviço indisponível')
    const result: { data?: { status?: string } } = await response.json()
    availability.value = result.data?.status === 'ok'
      ? 'Conexão disponível.'
      : 'Conexão indisponível.'
  } catch {
    availability.value = 'Conexão indisponível.'
  }
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 px-6 py-16 text-slate-900">
    <section class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white p-8">
      <h1 class="text-3xl font-semibold">Census Explorer</h1>
      <p class="mt-4">Aplicação inicializada. As consultas censitárias serão disponibilizadas em uma próxima etapa.</p>
      <p class="mt-6 text-sm text-slate-600" role="status">{{ availability }}</p>
    </section>
  </main>
</template>
