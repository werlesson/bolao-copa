<script setup lang="ts">
import type { RankingEntry } from '~/types/ranking'
import { championLabel, shortDisplayName } from '~/utils/ranking'

const props = defineProps<{
  entries: RankingEntry[]
  currentUserId?: string | null
}>()

interface PodiumSlot {
  place: 1 | 2 | 3
  entry: RankingEntry | null
}

const PODIUM_LAYOUT: Array<{ place: 1 | 2 | 3 }> = [
  { place: 2 },
  { place: 1 },
  { place: 3 },
]

const rankedTop = computed(() =>
  [...props.entries].sort((a, b) => a.position - b.position).slice(0, 3),
)

const slots = computed<PodiumSlot[]>(() => {
  // Assign by array index so tied players (same position value) both appear.
  // Visual layout: left = 2nd-index, center = 1st-index, right = 3rd-index.
  const [first, second, third] = rankedTop.value
  return [
    { place: 2 as const, entry: second ?? null },
    { place: 1 as const, entry: first ?? null },
    { place: 3 as const, entry: third ?? null },
  ]
})

// Stagger delays: left (2nd) → right (3rd) → center (1st) for dramatic reveal
const ANIMATION_DELAYS = [80, 240, 160]

const podiumHeights: Record<1 | 2 | 3, string> = {
  1: 'h-28',
  2: 'h-20',
  3: 'h-16',
}

const columnMaxWidth: Record<1 | 2 | 3, string> = {
  1: 'max-w-[110px]',
  2: 'max-w-[90px]',
  3: 'max-w-[90px]',
}

function isCurrentUser(entry: RankingEntry): boolean {
  return Boolean(props.currentUserId && entry.user.id === props.currentUserId)
}

function avatarWrapperClass(place: 1 | 2 | 3): string {
  return place === 1
    ? 'h-16 w-16 ring-[3px] ring-secondary-container'
    : 'h-12 w-12 ring-2 ring-outline-variant'
}

function pedestalClass(place: 1 | 2 | 3, entry: RankingEntry): string {
  const highlighted = isCurrentUser(entry)
  if (place === 1) {
    return [
      'border-primary/30 bg-primary-container text-white',
      highlighted ? 'ring-2 ring-secondary-container shadow-lg shadow-secondary-container/20' : 'neon-glow-green',
    ].join(' ')
  }
  if (place === 2) {
    return [
      'border-outline-variant bg-surface-container-high text-on-surface',
      highlighted ? 'border-primary/40 ring-1 ring-primary/20' : '',
    ].join(' ')
  }
  return [
    'border-outline-variant bg-surface-container text-on-surface',
    highlighted ? 'border-primary/40 ring-1 ring-primary/20' : '',
  ].join(' ')
}

function emptyPedestalClass(place: 1 | 2 | 3): string {
  if (place === 1) return 'border-dashed border-primary/30 bg-primary-container/10'
  return 'border-dashed border-outline-variant/40 bg-surface-container/30'
}

function pointsClass(place: 1 | 2 | 3): string {
  return place === 1 ? 'text-secondary-container' : 'text-on-surface'
}

function counterIcon(place: 1 | 2 | 3): string {
  return `counter_${place}`
}
</script>

<template>
  <section
    v-if="entries.length > 0"
    class="mt-3 flex h-40 sm:h-52 items-end justify-center gap-2 px-margin"
  >
    <div
      v-for="(slot, i) in slots"
      :key="slot.place"
      class="podium-col flex w-full flex-col items-center"
      :class="columnMaxWidth[slot.place]"
      :style="{ animationDelay: `${ANIMATION_DELAYS[i]}ms` }"
    >
      <span
        v-if="slot.place === 1 && slot.entry"
        class="mb-1 text-xl leading-none"
        aria-hidden="true"
      >👑</span>
      <span v-else class="mb-1 h-7" aria-hidden="true" />

      <template v-if="slot.entry">
        <div class="relative mb-2">
          <div
            :class="[
              'overflow-hidden rounded-full',
              avatarWrapperClass(slot.place),
              isCurrentUser(slot.entry) ? 'ring-offset-2 ring-offset-background' : '',
            ]"
          >
            <UiAvatar
              :name="slot.entry.user.name"
              :src="slot.entry.user.avatar_url"
              :size="slot.place === 1 ? 'xl' : 'lg'"
              class="!h-full !w-full"
            />
          </div>
          <div
            :class="[
              'absolute -bottom-1 -right-1 flex items-center justify-center rounded-full border-2 border-background',
              slot.place === 1
                ? 'h-[22px] w-[22px] bg-secondary-container'
                : 'h-[18px] w-[18px] border-outline-variant bg-surface-container-highest',
            ]"
          >
            <span
              class="font-bold leading-none tabular-nums"
              :class="slot.place === 1 ? 'text-[11px] text-on-secondary-container' : 'text-[9px] text-on-surface'"
            >{{ slot.entry.position }}</span>
          </div>
          <span
            v-if="isCurrentUser(slot.entry)"
            class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border-2 border-background bg-secondary"
            aria-hidden="true"
          />
        </div>

        <div
          :class="[
            'flex w-full flex-col items-center justify-start rounded-t-lg border-x border-t px-2',
            podiumHeights[slot.place],
            pedestalClass(slot.place, slot.entry),
            slot.place === 1 ? 'pt-3' : 'pt-2',
          ]"
        >
          <div class="flex max-w-full items-center justify-center gap-1">
            <span class="truncate text-center font-label-caps text-[11px]">
              {{ isCurrentUser(slot.entry) ? 'Você' : shortDisplayName(slot.entry.user.name) }}
            </span>
            <span
              v-if="isCurrentUser(slot.entry)"
              class="shrink-0 rounded bg-primary/10 px-1 text-[7px] font-bold font-label-caps text-primary"
            >
              EU
            </span>
          </div>
          <span
            :class="[
              'mt-0.5 font-mono text-[12px] font-bold tabular-nums',
              pointsClass(slot.place),
            ]"
          >
            {{ slot.entry.total_points }} pts
          </span>
          <span
            v-if="slot.place === 1"
            :class="'mt-0.5 text-[9px] uppercase tracking-wider font-label-caps text-white/50'"
          >
            {{ slot.entry.exact_scores }} {{ slot.entry.exact_scores === 1 ? 'exato' : 'exatos' }}
          </span>
          <div
            v-if="slot.place === 1"
            class="mt-1.5 rounded bg-secondary-container/15 px-1.5 py-0.5 text-[9px] font-bold text-secondary-container font-label-caps"
          >
            {{ championLabel(slot.entry.user.name) }}
          </div>
        </div>
      </template>

      <template v-else>
        <div
          class="mb-2 flex items-center justify-center rounded-full border-2 border-dashed border-outline-variant/50 bg-surface-container-low"
          :class="slot.place === 1 ? 'h-16 w-16' : 'h-12 w-12'"
          aria-hidden="true"
        >
          <span
            class="material-symbols-outlined text-on-surface-variant/30"
            :class="slot.place === 1 ? 'text-[24px]' : 'text-[20px]'"
            :style="{ fontVariationSettings: '\'FILL\' 0, \'wght\' 400, \'GRAD\' 0, \'opsz\' 24' }"
          >person</span>
        </div>
        <div
          :class="[
            'flex w-full flex-col items-center justify-center rounded-t-lg border-x border-t',
            podiumHeights[slot.place],
            emptyPedestalClass(slot.place),
          ]"
          :aria-label="`Posição ${slot.place} vazia`"
        >
          <span
            class="material-symbols-outlined text-on-surface-variant/30"
            :style="{ fontVariationSettings: '\'FILL\' 0, \'wght\' 400, \'GRAD\' 0, \'opsz\' 24' }"
          >{{ counterIcon(slot.place) }}</span>
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.podium-col {
  animation: podium-rise 420ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes podium-rise {
  from {
    opacity: 0;
    transform: translateY(20px) scale(0.96);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>
