<script setup lang="ts">
import type { BolaoGroup } from '~/types/group'
import type { GroupRankingPreviewRow } from '~/types/group'
import { shortDisplayName } from '~/utils/ranking'

const props = defineProps<{
  group: BolaoGroup
  userRank: number
  previewRows: GroupRankingPreviewRow[]
  pendingRequestsCount?: number
}>()

const isTopRank = computed(() => props.userRank === 1)

function rowLabel(row: GroupRankingPreviewRow): string {
  if (row.isCurrentUser) return 'Você'
  return shortDisplayName(row.name)
}
</script>

<template>
  <NuxtLink
    :to="`/grupos/${group.id}`"
    class="glass-card relative block overflow-hidden rounded-xl border border-white/10 p-4 transition-all duration-200 hover:border-primary/30 active:scale-[0.98]"
  >
    <!-- Rank badge -->
    <div
      class="absolute right-0 top-0 flex flex-col items-center rounded-bl-xl border-b border-l px-3 py-2"
      :class="isTopRank ? 'border-primary/20 bg-primary/10' : 'border-white/10 bg-surface-container-highest'"
    >
      <span
        class="font-label-caps text-[9px] uppercase tracking-widest"
        :class="isTopRank ? 'text-primary/70' : 'text-on-surface-variant/50'"
      >
        Rank
      </span>
      <span
        class="font-mono text-[18px] font-bold leading-none tabular-nums"
        :class="isTopRank ? 'text-primary' : 'text-on-surface'"
      >
        #{{ userRank }}
      </span>
    </div>

    <!-- Group identity -->
    <div class="mb-3 flex items-center gap-3 pr-16">
      <div
        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-surface-container-highest"
      >
        <span
          class="material-symbols-outlined text-[26px]"
          :class="isTopRank ? 'text-secondary-container' : 'text-primary'"
          style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24"
        >{{ isTopRank ? 'emoji_events' : 'sports_soccer' }}</span>
      </div>
      <div class="min-w-0">
        <h3 class="font-title-md text-title-md truncate text-on-surface">
          {{ group.name }}
        </h3>
        <p class="font-body-sm text-[12px] flex items-center gap-1 text-on-surface-variant/60">
          <span class="material-symbols-outlined text-[13px]">groups</span>
          {{ group.members_count ?? 0 }} {{ (group.members_count ?? 0) === 1 ? 'membro' : 'membros' }}
          <span v-if="group.require_approval" class="ml-1 inline-flex items-center gap-0.5">
            <span class="material-symbols-outlined text-[11px]">lock</span>
          </span>
        </p>
      </div>
    </div>

    <!-- Mini ranking preview -->
    <div v-if="previewRows.length > 0" class="mb-3 space-y-1.5 border-t border-white/5 pt-3">
      <template v-for="row in previewRows" :key="row.position">
        <div v-if="row.gapBefore" class="flex items-center justify-center py-0.5">
          <span class="font-mono text-[10px] tracking-widest text-on-surface-variant/25">···</span>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="w-5 shrink-0 font-mono text-[11px] tabular-nums"
            :class="row.isCurrentUser ? 'text-primary font-bold' : 'text-on-surface-variant/40'"
          >{{ row.position }}</span>
          <span
            class="flex-1 truncate font-label-caps text-[11px]"
            :class="row.isCurrentUser ? 'text-primary font-bold' : 'text-on-surface-variant/70'"
          >{{ rowLabel(row) }}</span>
          <span
            class="shrink-0 font-mono text-[11px] tabular-nums"
            :class="row.isCurrentUser ? 'text-primary font-bold' : 'text-on-surface-variant/60'"
          >{{ row.totalPoints }} pts</span>
        </div>
      </template>
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-between border-t border-white/5 pt-3">
      <span
        v-if="pendingRequestsCount && pendingRequestsCount > 0"
        class="inline-flex items-center gap-1 rounded-full border border-secondary-container/20 bg-secondary-container/10 px-2.5 py-1 font-label-caps text-[10px] text-secondary-container"
      >
        <span class="material-symbols-outlined text-[12px]">pending</span>
        {{ pendingRequestsCount }} pendente{{ pendingRequestsCount === 1 ? '' : 's' }}
      </span>
      <span v-else class="font-label-caps text-[10px] text-on-surface-variant/40">
        {{ previewRows.length > 0 ? `${group.members_count ?? previewRows.length} no ranking` : 'Sem ranking' }}
      </span>
      <span class="font-label-caps text-[10px] uppercase tracking-widest text-primary/70 flex items-center gap-1">
        VER
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
      </span>
    </div>
  </NuxtLink>
</template>
