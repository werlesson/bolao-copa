<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

await navigateTo('/jogos', { replace: true })
</script>

<template>
  <div />
</template>
