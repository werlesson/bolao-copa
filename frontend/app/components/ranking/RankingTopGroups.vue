<script setup lang="ts">
import type { RankingEntry } from '~/types/ranking'

const MAX_GROUPS = 3

const props = defineProps<{
  entries: RankingEntry[]
  currentUserId?: string | null
}>()

interface PositionGroup {
  position: number
  tier: 1 | 2 | 3
  entries: RankingEntry[]
}

const groups = computed<PositionGroup[]>(() => {
  const result: PositionGroup[] = []
  const tiers = [1, 2, 3] as const
  let tierIdx = 0

  for (const entry of props.entries) {
    if (tierIdx >= MAX_GROUPS) break
    const last = result[result.length - 1]
    if (last && last.position === entry.position) {
      last.entries.push(entry)
    } else {
      result.push({ position: entry.position, tier: tiers[tierIdx++], entries: [entry] })
    }
  }

  return result
})

function isCurrentUser(entry: RankingEntry): boolean {
  return Boolean(props.currentUserId && entry.user.id === props.currentUserId)
}
</script>

<template>
  <section v-if="entries.length > 0" class="mt-3 space-y-1.5 px-margin">
    <template v-for="group in groups" :key="group.position">
      <div
        v-for="entry in group.entries"
        :key="entry.user.id"
        class="glass-card flex items-center gap-3 rounded-xl border-l-[3px] px-3"
        :class="[
          group.tier === 1
            ? 'border-l-secondary-container bg-secondary-container/[0.04] py-3.5 shadow-[0_0_20px_rgba(253,220,0,0.07)]'
            : group.tier === 2
              ? 'border-l-on-surface-variant/30 py-3'
              : 'border-l-outline-variant/40 py-2.5',
          isCurrentUser(entry) && group.tier !== 1
            ? 'border-l-primary/50 bg-primary/[0.03]'
            : '',
        ]"
      >
        <!-- Position badge -->
        <div class="flex w-8 shrink-0 flex-col items-center gap-0.5">
          <span
            v-if="group.tier === 1"
            class="text-[10px] leading-none"
            aria-hidden="true"
          >👑</span>
          <span
            class="font-mono font-bold tabular-nums leading-none"
            :class="[
              group.tier === 1 ? 'text-[15px] text-secondary-container' : '',
              group.tier === 2 ? 'text-[15px] text-on-surface-variant/60' : '',
              group.tier === 3 ? 'text-[14px] text-on-surface-variant/40' : '',
              isCurrentUser(entry) && group.tier !== 1 ? '!text-primary' : '',
            ]"
          >{{ entry.position }}</span>
        </div>

        <!-- Avatar -->
        <div
          class="shrink-0 overflow-hidden rounded-full"
          :class="[
            group.tier === 1 ? 'h-10 w-10 ring-2 ring-secondary-container/50' : 'h-9 w-9 ring-1',
            group.tier !== 1 && isCurrentUser(entry) ? 'ring-primary/30' : '',
            group.tier !== 1 && !isCurrentUser(entry) ? 'ring-white/10' : '',
          ]"
        >
          <UiAvatar
            :name="entry.user.name"
            :src="entry.user.avatar_url"
            :size="group.tier === 1 ? 'md' : 'sm'"
            class="!h-full !w-full"
          />
        </div>

        <!-- Name + exact scores -->
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-1.5">
            <p class="truncate font-body-sm text-[14px] font-semibold leading-none text-on-surface">
              {{ isCurrentUser(entry) ? 'Você' : entry.user.name }}
            </p>
            <span
              v-if="isCurrentUser(entry)"
              class="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 font-label-caps text-[9px] text-primary"
            >EU</span>
          </div>
          <p class="mt-0.5 font-label-caps text-[10px] text-on-surface-variant/50">
            {{ entry.exact_scores }} {{ entry.exact_scores === 1 ? 'EXATO' : 'EXATOS' }}
          </p>
        </div>

        <!-- Points -->
        <div class="shrink-0 text-right">
          <p
            class="font-mono font-bold tabular-nums leading-none"
            :class="[
              group.tier === 1 ? 'text-[22px] text-secondary-container' : '',
              group.tier === 2 ? 'text-[19px] text-on-surface' : '',
              group.tier === 3 ? 'text-[17px] text-on-surface/70' : '',
              isCurrentUser(entry) && group.tier !== 1 ? '!text-primary' : '',
            ]"
          >{{ entry.total_points }}</p>
          <p class="mt-0.5 font-label-caps text-[9px] text-on-surface-variant/40">pts</p>
        </div>
      </div>
    </template>
  </section>
</template>
