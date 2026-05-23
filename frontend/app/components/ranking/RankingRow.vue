<script setup lang="ts">
import type { RankingEntry } from '~/types/ranking'

const props = defineProps<{
  entry: RankingEntry
  isCurrentUser?: boolean
}>()

const displayName = computed(() =>
  props.isCurrentUser ? 'Você' : props.entry.user.name,
)

const exactLabel = computed(() => {
  const count = props.entry.exact_scores
  return `${count} ${count === 1 ? 'EXATO' : 'EXATOS'}`
})

const isLeader = computed(() => props.entry.position === 1)
</script>

<template>
  <div
    class="glass-card flex items-center gap-3 rounded-xl px-3 py-3"
    :class="[
      isLeader ? 'border-l-4 border-l-secondary-container neon-glow-green' : '',
      isCurrentUser && !isLeader ? 'border-l-4 border-l-primary/40 bg-primary/[0.03]' : '',
    ]"
  >
    <!-- Position -->
    <span
      class="w-7 shrink-0 text-center font-mono text-[15px] font-bold leading-none tabular-nums"
      :class="isLeader ? 'text-secondary-container' : isCurrentUser ? 'text-primary' : 'text-on-surface-variant/50'"
    >
      {{ entry.position }}
    </span>

    <!-- Avatar -->
    <div
      class="h-9 w-9 shrink-0 overflow-hidden rounded-full"
      :class="isLeader ? 'ring-1 ring-secondary-container/50' : isCurrentUser ? 'ring-1 ring-primary/30' : 'ring-1 ring-white/10'"
    >
      <UiAvatar
        :name="entry.user.name"
        :src="entry.user.avatar_url"
        size="md"
        class="!h-full !w-full"
      />
    </div>

    <!-- Info -->
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-1.5">
        <p class="truncate font-body-sm text-[14px] font-semibold leading-none text-on-surface">
          {{ displayName }}
        </p>
        <span
          v-if="isCurrentUser"
          class="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 font-label-caps text-[9px] text-primary"
        >
          EU
        </span>
      </div>
      <p class="mt-0.5 font-label-caps text-[10px] text-on-surface-variant/50">
        {{ exactLabel }}
      </p>
    </div>

    <!-- Points -->
    <div class="shrink-0 text-right">
      <p
        class="font-mono text-[20px] font-bold leading-none tabular-nums"
        :class="isLeader ? 'text-secondary-container' : isCurrentUser ? 'text-primary' : 'text-on-surface'"
      >
        {{ entry.total_points }}
      </p>
      <p class="mt-0.5 font-label-caps text-[9px] text-on-surface-variant/40">
        pts
      </p>
    </div>
  </div>
</template>
