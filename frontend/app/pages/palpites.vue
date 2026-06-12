<script setup lang="ts">
import type { OutcomeFilter, PredictionViewMode } from '~/composables/usePredictionHistory'

definePageMeta({ middleware: 'auth' })

const {
  loading,
  error,
  viewMode,
  teamSearch,
  activeStage,
  activeTeam,
  activeDate,
  activeOutcome,
  grouped,
  summary,
  availableStages,
  filteredTeams,
  dateCards,
  activeDateLabel,
  hasActiveFilters,
  activeFilterCount,
  hasAnyPredictions,
  fetchHistory,
  setStage,
  setTeam,
  setDate,
  setOutcome,
  clearFilters,
  STAGE_LABELS,
  OUTCOME_LABELS,
} = usePredictionHistory()

const showFilterSheet = ref(false)

onMounted(() => {
  void fetchHistory()
})

const viewChipBase = 'flex-1 rounded-full py-2 font-label-caps text-[11px] uppercase tracking-widest transition-colors duration-150'
const viewChipInactive = 'text-on-surface-variant'
const viewChipActive = 'bg-primary text-on-primary shadow-sm shadow-primary/20'

const chipBase = 'rounded-full border px-3 py-1.5 font-label-caps text-[11px] uppercase tracking-widest transition-colors duration-150'
const chipInactive = 'border-white/10 text-on-surface-variant hover:border-white/20 hover:text-on-surface'
const chipActive = 'border-primary/30 bg-primary/10 text-primary'

const outcomeOptions: OutcomeFilter[] = ['exact', 'partial', 'miss']

const accuracyLabel = computed(() => {
  const value = summary.value.accuracyPercent
  if (value == null) return '—'
  return `${value}%`
})

const summaryCountLabel = computed(() => {
  const { predictionCount, finishedCount } = summary.value
  if (viewMode.value === 'finalizados') {
    return `${predictionCount} ${predictionCount === 1 ? 'palpite' : 'palpites'}`
  }
  if (viewMode.value === 'aguardando') {
    return `${predictionCount} aguardando`
  }
  return `${predictionCount} palpites · ${finishedCount} finalizados`
})

const showEmptyNoPredictions = computed(() =>
  !loading.value && !error.value && !hasAnyPredictions.value,
)

const showEmptyFiltered = computed(() =>
  !loading.value && !error.value && hasAnyPredictions.value && grouped.value.length === 0,
)

const showEmptyAguardando = computed(() =>
  viewMode.value === 'aguardando' && showEmptyFiltered.value && !hasActiveFilters.value,
)

function setViewMode(mode: PredictionViewMode) {
  viewMode.value = mode
}
</script>

<template>
  <div>
    <UiSubPageHeader title="MEUS PALPITES" back-to="/perfil">
      <button
        type="button"
        class="relative flex h-8 w-8 items-center justify-center rounded-full transition-colors hover:bg-white/5"
        :class="hasActiveFilters ? 'text-primary' : 'text-on-surface-variant'"
        :aria-label="hasActiveFilters ? `${activeFilterCount} filtro(s) ativo(s)` : 'Filtrar palpites'"
        @click="showFilterSheet = true"
      >
        <span
          class="material-symbols-outlined text-[22px] leading-none"
          :style="hasActiveFilters ? 'font-variation-settings: \'FILL\' 1' : ''"
        >tune</span>
        <span
          v-if="activeFilterCount > 0"
          class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[9px] font-bold text-on-primary"
        >{{ activeFilterCount }}</span>
      </button>
    </UiSubPageHeader>

    <div
      v-if="hasActiveFilters"
      class="flex items-center gap-2 border-b border-white/5 bg-background px-margin-mobile py-2"
    >
      <span
        class="material-symbols-outlined shrink-0 text-[14px] leading-none text-primary/70"
        style="font-variation-settings: 'FILL' 1"
      >filter_alt</span>
      <div class="flex min-w-0 flex-1 gap-1.5 overflow-x-auto hide-scrollbar">
        <span
          v-if="activeStage"
          class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 font-label-caps text-[10px] uppercase tracking-widest text-primary"
        >{{ STAGE_LABELS[activeStage] ?? activeStage }}</span>
        <span
          v-if="activeTeam"
          class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 font-label-caps text-[10px] uppercase tracking-widest text-primary"
        >{{ activeTeam }}</span>
        <span
          v-if="activeDateLabel"
          class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 font-label-caps text-[10px] uppercase tracking-widest text-primary"
        >{{ activeDateLabel }}</span>
        <span
          v-if="activeOutcome"
          class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 font-label-caps text-[10px] uppercase tracking-widest text-primary"
        >{{ OUTCOME_LABELS[activeOutcome] }}</span>
      </div>
      <button
        type="button"
        class="shrink-0 text-on-surface-variant/50 transition-opacity hover:opacity-80"
        aria-label="Limpar filtros"
        @click="clearFilters"
      >
        <span class="material-symbols-outlined text-[16px] leading-none">close</span>
      </button>
    </div>

    <div class="space-y-4 px-margin-mobile py-4 pb-32">
      <div class="flex rounded-full border border-white/10 bg-surface-container-low p-1">
        <button
          type="button"
          :class="[viewChipBase, viewMode === 'todos' ? viewChipActive : viewChipInactive]"
          :aria-pressed="viewMode === 'todos'"
          @click="setViewMode('todos')"
        >
          Todos
        </button>
        <button
          type="button"
          :class="[viewChipBase, viewMode === 'aguardando' ? viewChipActive : viewChipInactive]"
          :aria-pressed="viewMode === 'aguardando'"
          @click="setViewMode('aguardando')"
        >
          Aguardando
        </button>
        <button
          type="button"
          :class="[viewChipBase, viewMode === 'finalizados' ? viewChipActive : viewChipInactive]"
          :aria-pressed="viewMode === 'finalizados'"
          @click="setViewMode('finalizados')"
        >
          Finalizados
        </button>
      </div>

      <div
        v-if="!loading && !error && hasAnyPredictions && summary.predictionCount > 0"
        class="grid grid-cols-3 gap-2"
      >
        <div class="glass-card flex flex-col items-center gap-1 rounded-xl p-3">
          <span class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/50">Pontos</span>
          <span
            class="font-headline-lg-mobile text-headline-lg-mobile leading-none"
            :class="summary.totalPoints > 0 ? 'text-secondary-container' : 'text-on-surface-variant/40'"
          >
            {{ summary.totalPoints > 0 ? `+${summary.totalPoints}` : '0' }}
          </span>
        </div>
        <div class="glass-card flex flex-col items-center gap-1 rounded-xl p-3">
          <span class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/50">Aproveit.</span>
          <span class="font-headline-lg-mobile text-headline-lg-mobile leading-none text-primary">
            {{ accuracyLabel }}
          </span>
        </div>
        <div class="glass-card flex flex-col items-center gap-1 rounded-xl p-3">
          <span class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/50">Total</span>
          <span class="text-center font-body-sm text-body-sm font-semibold leading-tight text-on-surface">
            {{ summaryCountLabel }}
          </span>
        </div>
      </div>

      <div v-if="loading" class="space-y-2">
        <UiMatchCardSkeleton v-for="n in 4" :key="n" />
      </div>

      <p v-else-if="error" class="font-body-lg text-body-lg text-error">{{ error }}</p>

      <button
        v-if="error"
        type="button"
        class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container py-3 font-label-caps text-label-caps uppercase text-on-surface transition-colors hover:bg-surface-container-high"
        @click="fetchHistory"
      >
        <span class="material-symbols-outlined text-[18px]">refresh</span>
        Tentar novamente
      </button>

      <div
        v-else-if="showEmptyNoPredictions"
        class="flex flex-col items-center gap-4 py-12 text-center"
      >
        <span class="material-symbols-outlined text-[48px] text-on-surface-variant/25">edit_square</span>
        <div>
          <p class="font-title-md text-title-md text-on-surface">Nenhum palpite ainda</p>
          <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
            Faça seu primeiro palpite nos jogos da Copa.
          </p>
        </div>
        <button
          type="button"
          class="font-label-caps text-label-caps rounded-lg bg-primary px-6 py-3 text-on-primary shadow-lg shadow-primary/20"
          @click="navigateTo('/jogos')"
        >
          Ir para jogos
        </button>
      </div>

      <div
        v-else-if="showEmptyFiltered"
        class="flex flex-col items-center gap-4 py-10 text-center"
      >
        <span class="material-symbols-outlined text-[40px] text-on-surface-variant/25">filter_alt_off</span>
        <div>
          <p class="font-title-md text-title-md text-on-surface">
            <template v-if="showEmptyAguardando">Nenhum palpite pendente de resultado</template>
            <template v-else-if="hasActiveFilters">Nenhum palpite com esses filtros</template>
            <template v-else>Nenhum palpite nesta visualização</template>
          </p>
        </div>
        <button
          v-if="hasActiveFilters"
          type="button"
          class="font-label-caps text-label-caps text-primary uppercase tracking-widest"
          @click="clearFilters"
        >
          Limpar filtros
        </button>
      </div>

      <template v-else>
        <section v-for="group in grouped" :key="group.key" class="space-y-2">
          <div class="flex items-center gap-3">
            <h2 class="shrink-0 font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
              {{ group.label }}
            </h2>
            <div class="h-px flex-1 bg-outline-variant" />
          </div>
          <PredictionHistoryCard
            v-for="item in group.items"
            :key="item.id"
            :item="item"
          />
        </section>
      </template>
    </div>

    <Teleport to="body">
      <Transition name="overlay-fade">
        <div
          v-if="showFilterSheet"
          class="fixed inset-0 z-40 bg-black/60 backdrop-blur-[2px]"
          @click="showFilterSheet = false"
        />
      </Transition>

      <Transition name="sheet-up">
        <div
          v-if="showFilterSheet"
          class="fixed bottom-0 left-0 right-0 z-50 flex max-h-[85vh] flex-col rounded-t-2xl bg-surface-container"
          style="padding-bottom: env(safe-area-inset-bottom)"
        >
          <div class="flex justify-center pb-2 pt-3">
            <div class="h-1 w-10 rounded-full bg-on-surface-variant/20" />
          </div>

          <div class="flex items-center justify-between px-margin-mobile pb-3 pt-1">
            <span class="font-headline-lg-mobile text-headline-lg-mobile uppercase tracking-tighter text-on-surface">
              Filtros
            </span>
            <button
              type="button"
              class="flex h-8 w-8 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-white/5"
              @click="showFilterSheet = false"
            >
              <span class="material-symbols-outlined text-[20px] leading-none">close</span>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto px-margin-mobile">
            <div v-if="viewMode === 'finalizados' || viewMode === 'todos'" class="mb-6">
              <p class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                Resultado do palpite
              </p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="outcome in outcomeOptions"
                  :key="outcome"
                  type="button"
                  :class="[chipBase, activeOutcome === outcome ? chipActive : chipInactive]"
                  @click="setOutcome(outcome)"
                >
                  {{ OUTCOME_LABELS[outcome] }}
                </button>
              </div>
            </div>

            <div v-if="availableStages.length > 1" class="mb-6">
              <p class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                Fase
              </p>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  :class="[chipBase, activeStage === null ? chipActive : chipInactive]"
                  @click="setStage(null)"
                >
                  Todas
                </button>
                <button
                  v-for="stage in availableStages"
                  :key="stage.value"
                  type="button"
                  :class="[chipBase, activeStage === stage.value ? chipActive : chipInactive]"
                  @click="setStage(stage.value)"
                >
                  {{ stage.label }}
                </button>
              </div>
            </div>

            <div v-if="filteredTeams.length > 0" class="mb-6">
              <p class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                Seleção
              </p>
              <div class="relative mb-3">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] leading-none text-on-surface-variant/40">search</span>
                <input
                  v-model="teamSearch"
                  type="search"
                  placeholder="Buscar seleção..."
                  class="w-full rounded-lg border border-white/10 bg-surface-container-high py-2 pl-9 pr-9 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/40 focus:border-primary/40 focus:outline-none"
                >
                <button
                  v-if="teamSearch"
                  type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2"
                  @click="teamSearch = ''"
                >
                  <span class="material-symbols-outlined text-[14px] leading-none text-on-surface-variant/40">close</span>
                </button>
              </div>
              <div class="grid grid-cols-4 gap-2">
                <button
                  v-for="team in filteredTeams"
                  :key="team.name"
                  type="button"
                  class="flex flex-col items-center gap-1.5 rounded-xl border py-2.5 transition-all duration-150 active:scale-95"
                  :class="activeTeam === team.name
                    ? 'border-primary/40 bg-primary/10'
                    : 'border-white/10 hover:border-white/15'"
                  @click="setTeam(team.name)"
                >
                  <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border p-[5px]"
                    :class="activeTeam === team.name
                      ? 'border-primary/30 bg-surface-container-high'
                      : 'border-white/10 bg-surface-container-highest'"
                  >
                    <img
                      v-if="team.flag"
                      :src="team.flag"
                      :alt="team.name"
                      class="h-full w-full object-contain"
                      loading="lazy"
                    >
                  </div>
                  <span
                    class="w-full truncate px-1 text-center font-label-caps text-[9px] uppercase leading-none tracking-wide"
                    :class="activeTeam === team.name ? 'text-primary' : 'text-on-surface-variant/70'"
                  >{{ team.name }}</span>
                </button>
              </div>
            </div>

            <div v-if="dateCards.length > 1" class="mb-6">
              <p class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                Data
              </p>
              <div class="overflow-x-auto hide-scrollbar">
                <div class="flex gap-2 pb-1">
                  <button
                    v-for="d in dateCards"
                    :key="d.key"
                    type="button"
                    class="flex w-[54px] shrink-0 flex-col items-center rounded-xl border py-2.5 transition-all duration-150 active:scale-95"
                    :class="activeDate === d.key
                      ? 'border-primary/40 bg-primary/10'
                      : 'border-white/10 hover:border-white/20'"
                    @click="setDate(d.key)"
                  >
                    <span
                      class="mb-1 h-[5px] w-[5px] rounded-full transition-colors"
                      :class="d.isToday
                        ? (activeDate === d.key ? 'bg-primary' : 'bg-primary/50')
                        : 'opacity-0'"
                    />
                    <span
                      class="font-label-caps text-[8px] leading-none tracking-widest"
                      :class="activeDate === d.key ? 'text-primary/70' : 'text-on-surface-variant/50'"
                    >{{ d.weekday }}</span>
                    <span
                      class="font-display text-[22px] leading-tight"
                      :class="activeDate === d.key ? 'text-primary' : 'text-on-surface'"
                    >{{ d.day }}</span>
                    <span
                      class="font-label-caps text-[8px] leading-none tracking-widest"
                      :class="activeDate === d.key ? 'text-primary/70' : 'text-on-surface-variant/50'"
                    >{{ d.month }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-white/5 px-margin-mobile py-4">
            <button
              type="button"
              class="font-label-caps text-label-caps w-full rounded-xl bg-primary py-3 text-on-primary shadow-lg shadow-primary/20"
              @click="showFilterSheet = false"
            >
              Aplicar filtros
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
