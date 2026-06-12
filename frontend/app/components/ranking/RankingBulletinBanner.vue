<script setup lang="ts">
import type { RankingBulletin } from '~/types/rankingBulletin'
import type { RankingTabId } from '~/types/ranking'
import { extractBulletinBody, splitBulletinSentences } from '~/utils/bulletinContent'

const props = defineProps<{
  bulletin: RankingBulletin
  tabId: RankingTabId
}>()

const { isHidden, setHidden } = useRankingBulletinPrefs()

const expanded = ref(true)

watch(
  () => props.bulletin.id,
  (bulletinId) => {
    expanded.value = !isHidden(props.tabId, bulletinId)
  },
  { immediate: true },
)

const relativeTime = computed(() => {
  if (!props.bulletin.created_at) return ''
  const created = new Date(props.bulletin.created_at).getTime()
  const diffMin = Math.max(0, Math.floor((Date.now() - created) / 60_000))
  if (diffMin < 1) return 'agora'
  if (diffMin < 60) return `há ${diffMin} min`
  const hours = Math.floor(diffMin / 60)
  if (hours < 24) return `há ${hours}h`
  return `há ${Math.floor(hours / 24)}d`
})

const matchTeams = computed(() => {
  const match = props.bulletin.match
  if (!match) return null
  return { home: match.home_team, away: match.away_team }
})

const scoreDisplay = computed(() => {
  const label = props.bulletin.match?.label ?? ''
  const found = label.match(/(\d+)×(\d+)/)
  if (found) return { home: found[1], away: found[2] }
  return null
})

type HighlightTone = 'up' | 'down' | 'neutral' | 'stats'

interface ParsedHighlight {
  text: string
  icon: string
  tone: HighlightTone
}

const parsedHighlights = computed((): ParsedHighlight[] => {
  const body = extractBulletinBody(props.bulletin.content)

  return splitBulletinSentences(body)
    .map(classifyHighlight)
})

const narrativeHighlights = computed(() =>
  parsedHighlights.value.filter(h => h.tone !== 'stats'),
)

const statsLine = computed(() =>
  parsedHighlights.value.find(h => h.tone === 'stats')?.text ?? null,
)

const previewLine = computed(() =>
  narrativeHighlights.value[0]?.text ?? props.bulletin.content,
)

function classifyHighlight(text: string): ParsedHighlight {
  const lower = text.toLowerCase()

  if (lower.includes('virou líder') || lower.includes('passou na frente') || lower.includes('manda no')
    || lower.includes('entrou no top') || lower.includes('invadiu o pódio') || lower.includes('invadiu o podio')
    || lower.includes('subiu do') || lower.includes('deu um pulo') || lower.includes('cravou')) {
    return { text, icon: 'trending_up', tone: 'up' }
  }
  if (lower.includes('perdeu a liderança') || lower.includes('perdeu a ponta') || lower.includes('saiu do pódio')
    || lower.includes('saiu voando') || lower.includes('caiu do') || lower.includes('despencou')) {
    return { text, icon: 'trending_down', tone: 'down' }
  }
  if (/\d+\s+pontuaram/.test(lower) || /mudaram de lugar/.test(lower) || /trocaram de lugar/.test(lower) || /mexeu geral/.test(lower)) {
    return { text, icon: 'insights', tone: 'stats' }
  }

  return { text, icon: 'radio_button_checked', tone: 'neutral' }
}

const toneClass: Record<HighlightTone, string> = {
  up: 'text-primary',
  down: 'text-tertiary',
  neutral: 'text-on-surface-variant/70',
  stats: 'text-on-surface-variant/55',
}

function hide() {
  expanded.value = false
  setHidden(props.tabId, props.bulletin.id, true)
}

function show() {
  expanded.value = true
  setHidden(props.tabId, props.bulletin.id, false)
}

function goToMatch() {
  const matchId = props.bulletin.match?.id
  navigateTo(matchId ? `/jogos/${matchId}` : '/jogos')
}
</script>

<template>
  <section class="px-margin" :aria-expanded="expanded">
    <Transition name="page-fade" mode="out-in">
      <!-- Collapsed chip -->
      <button
        v-if="!expanded"
        :key="`${bulletin.id}-hidden`"
        type="button"
        class="glass-card group flex w-full items-center gap-3 rounded-xl border border-white/10 px-3 py-2.5 text-left transition-colors hover:border-primary/20 hover:bg-surface-container-high/40"
        aria-label="Expandir resumo do último jogo"
        @click="show"
      >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
          <span class="material-symbols-outlined text-[16px] text-primary">campaign</span>
        </span>
        <span class="min-w-0 flex-1">
          <span class="flex items-center gap-2">
            <span class="font-label-caps text-[9px] uppercase tracking-widest text-primary/80">
              Último jogo
            </span>
            <span v-if="relativeTime" class="font-body-sm text-[10px] text-on-surface-variant/45">
              · {{ relativeTime }}
            </span>
          </span>
          <span class="mt-0.5 block truncate font-body-sm text-[13px] leading-snug text-on-surface-variant">
            {{ previewLine }}
          </span>
        </span>
        <span class="material-symbols-outlined shrink-0 text-[18px] text-on-surface-variant/40 transition-transform group-hover:-translate-y-0.5">
          expand_less
        </span>
      </button>

      <!-- Expanded card -->
      <article
        v-else
        :key="`${bulletin.id}-expanded`"
        class="glass-card overflow-hidden rounded-xl border border-white/10 neon-glow-green"
      >
        <!-- Header -->
        <div class="flex items-start justify-between gap-3 border-b border-white/[0.06] bg-gradient-to-r from-primary/[0.08] to-transparent px-4 py-2.5">
          <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/15">
              <span class="material-symbols-outlined text-[17px] text-primary">
                {{ bulletin.source === 'ai' ? 'auto_awesome' : 'campaign' }}
              </span>
            </span>
            <div class="min-w-0">
              <p class="font-label-caps text-[10px] uppercase tracking-widest text-primary">
                {{ bulletin.source === 'ai' ? 'Resumo IA' : 'O que mudou' }}
              </p>
              <p v-if="relativeTime" class="font-body-sm text-[10px] text-on-surface-variant/50">
                {{ relativeTime }}
              </p>
            </div>
          </div>
          <button
            type="button"
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-on-surface-variant/60 transition-colors hover:bg-white/5 hover:text-on-surface-variant"
            aria-label="Ocultar resumo"
            @click="hide"
          >
            <span class="material-symbols-outlined text-[17px] leading-none">close</span>
          </button>
        </div>

        <!-- Match score strip -->
        <div
          v-if="matchTeams"
          class="flex items-center gap-2 border-b border-white/[0.04] px-4 py-2.5"
        >
          <span class="min-w-0 flex-1 truncate text-right font-body-sm text-[13px] font-medium text-on-surface">
            {{ matchTeams.home }}
          </span>
          <div
            v-if="scoreDisplay"
            class="flex shrink-0 items-center gap-1.5 rounded-lg bg-surface-container-high/80 px-2.5 py-1"
          >
            <span class="font-mono text-[15px] font-bold tabular-nums text-on-surface">
              {{ scoreDisplay.home }}
            </span>
            <span class="font-mono text-[11px] text-on-surface-variant/40">×</span>
            <span class="font-mono text-[15px] font-bold tabular-nums text-on-surface">
              {{ scoreDisplay.away }}
            </span>
          </div>
          <span
            v-else
            class="shrink-0 font-mono text-[12px] text-on-surface-variant/50"
          >
            ×
          </span>
          <span class="min-w-0 flex-1 truncate font-body-sm text-[13px] font-medium text-on-surface">
            {{ matchTeams.away }}
          </span>
        </div>

        <!-- Highlights -->
        <div class="space-y-2.5 px-4 py-3">
          <div
            v-for="(highlight, i) in narrativeHighlights"
            :key="i"
            class="flex items-start gap-2.5"
          >
            <span
              class="material-symbols-outlined mt-px shrink-0 text-[16px] leading-none"
              :class="toneClass[highlight.tone]"
            >
              {{ highlight.icon }}
            </span>
            <p class="font-body-sm text-[13px] leading-snug text-on-surface/90">
              {{ highlight.text }}
            </p>
          </div>

          <p
            v-if="statsLine"
            class="flex items-center gap-1.5 pt-0.5 pl-[26px] font-body-sm text-[11px] leading-snug text-on-surface-variant/50"
          >
            <span class="material-symbols-outlined text-[14px] leading-none">insights</span>
            {{ statsLine }}
          </p>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end border-t border-white/[0.04] px-4 py-2.5">
          <button
            type="button"
            class="inline-flex items-center gap-1 font-label-caps text-[10px] uppercase tracking-widest text-primary transition-opacity hover:opacity-80"
            @click="goToMatch"
          >
            Ver jogo
            <span class="material-symbols-outlined text-[14px] leading-none">arrow_forward</span>
          </button>
        </div>
      </article>
    </Transition>
  </section>
</template>
