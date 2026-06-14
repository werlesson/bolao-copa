<script setup lang="ts">
definePageMeta({
  middleware: [
    'auth',
    defineNuxtRouteMiddleware(() => navigateTo('/jogos', { replace: true })),
  ],
})
</script>

<template>
  <div class="flex min-h-dvh items-center justify-center bg-background text-on-surface-variant">
    <span class="font-body-sm text-body-sm">Carregando…</span>
  </div>
</template>
