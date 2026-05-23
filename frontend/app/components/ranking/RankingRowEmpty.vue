<script setup lang="ts">
defineProps<{
  position: number
}>()
</script>

<template>
  <div
    class="flex items-center justify-between rounded-xl border border-dashed border-outline-variant/30 bg-surface-container/20 p-4 opacity-60"
    :aria-label="`Posição ${position} vazia`"
  >
    <div class="flex min-w-0 items-center gap-4">
      <span
        class="w-6 shrink-0 text-center font-headline-lg-mobile text-on-surface-variant/50"
      >
        {{ position }}
      </span>

      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-outline-variant/40 bg-surface-container-low"
        aria-hidden="true"
      >
        <span
          class="material-symbols-outlined text-[20px] text-on-surface-variant/40"
          :style="{ fontVariationSettings: '\'FILL\' 0, \'wght\' 400, \'GRAD\' 0, \'opsz\' 24' }"
        >person</span>
      </div>

      <div class="min-w-0">
        <span class="font-title-md text-title-md text-on-surface-variant/50">
          Vaga disponível
        </span>
        <span class="mt-0.5 block font-label-caps text-label-caps text-on-surface-variant/40">
          AGUARDANDO PARTICIPANTE
        </span>
      </div>
    </div>

    <span class="shrink-0 font-mono font-bold tabular-nums text-[20px] text-on-surface-variant/30 leading-none">
      —
    </span>
  </div>
</template>
