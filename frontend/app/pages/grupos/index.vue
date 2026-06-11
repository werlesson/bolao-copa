<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { groups, loading, error, fetchGroups } = useGroups()

const groupSearch = ref('')

const filteredGroups = computed(() => {
  const q = groupSearch.value.toLowerCase().trim()
  if (!q) return groups.value
  return groups.value.filter(g => g.name.toLowerCase().includes(q))
})

const competitionLabel = computed(() => {
  const n = groups.value.length
  if (n === 0) return 'Você ainda não está em nenhuma competição'
  if (n === 1) return '1 grupo'
  return `${n} grupos`
})

onMounted(() => {
  fetchGroups()
})

async function reload() {
  await fetchGroups()
}
</script>

<template>
  <div class="pb-6">
    <UiSubPageHeader title="GRUPOS">
      <span
        v-if="groups.length > 0"
        class="font-label-caps text-label-caps rounded-full bg-primary/10 px-2.5 py-1 text-primary"
      >
        {{ competitionLabel }}
      </span>
    </UiSubPageHeader>

    <p
      v-if="loading"
      class="px-margin-mobile pt-8 font-body-lg text-body-lg text-on-surface-variant"
    >
      Carregando grupos…
    </p>

    <div v-else-if="error" class="space-y-3 px-margin-mobile pt-4">
      <p class="font-body-lg text-body-lg text-error">{{ error }}</p>
      <button
        type="button"
        class="font-label-caps text-label-caps flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container py-3 text-on-surface transition-colors hover:bg-surface-container-high"
        @click="reload"
      >
        <span class="material-symbols-outlined text-[18px]">refresh</span>
        TENTAR NOVAMENTE
      </button>
    </div>

    <div v-else class="flex flex-col gap-3 px-margin-mobile pt-4">

      <!-- Empty state -->
      <div
        v-if="groups.length === 0"
        class="flex flex-col items-center gap-5 py-12 text-center"
      >
        <div class="flex h-20 w-20 items-center justify-center rounded-full border border-primary/20 bg-primary/10">
          <span
            class="material-symbols-outlined text-[40px] text-primary/60"
            style="font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 48"
          >emoji_events</span>
        </div>
        <div>
          <p class="font-title-md text-title-md text-on-surface">Nenhum grupo ainda</p>
          <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
            Crie seu grupo e convide seus amigos para competir
          </p>
        </div>
        <NuxtLink
          to="/grupos/novo"
          class="flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-label-caps text-label-caps uppercase text-on-primary shadow-[0_0_20px_rgba(101,223,118,0.3)] transition-[background-color,transform] active:scale-[0.98] hover:brightness-110"
        >
          <span class="material-symbols-outlined text-[18px]">add</span>
          CRIAR MEU GRUPO
        </NuxtLink>
      </div>

      <template v-else>
        <!-- Search -->
        <div v-if="groups.length > 1" class="relative mb-1">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] leading-none text-on-surface-variant/40">search</span>
          <input
            v-model="groupSearch"
            type="text"
            placeholder="Buscar grupo…"
            class="w-full rounded-lg border border-white/10 bg-surface-container-high py-2.5 pl-9 pr-9 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/40 focus:border-primary/40 focus:outline-none"
          >
          <button
            v-if="groupSearch"
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2"
            @click="groupSearch = ''"
          >
            <span class="material-symbols-outlined text-[14px] leading-none text-on-surface-variant/40">close</span>
          </button>
        </div>

        <!-- No results -->
        <div v-if="filteredGroups.length === 0" class="py-8 text-center">
          <span class="material-symbols-outlined text-[32px] text-on-surface-variant/20">search_off</span>
          <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant/50">Nenhum grupo encontrado.</p>
        </div>

        <!-- Group cards -->
        <GroupCard
          v-for="group in filteredGroups"
          :key="group.id"
          :group="group"
          :user-rank="group.user_rank ?? null"
          :pending-requests-count="group.is_owner ? group.pending_requests_count : undefined"
        />
      </template>
    </div>

    <!-- FAB -->
    <NuxtLink
      to="/grupos/novo"
      class="fixed bottom-[calc(5.5rem+env(safe-area-inset-bottom))] right-margin-mobile z-40 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-on-primary shadow-[0_8px_30px_rgba(101,223,118,0.4)] transition-all duration-200 active:scale-90 hover:brightness-110"
    >
      <span class="material-symbols-outlined text-[28px]">add</span>
    </NuxtLink>
  </div>
</template>
