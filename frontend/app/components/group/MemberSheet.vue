<script setup lang="ts">
import type { RankingEntry } from '~/types/ranking'

const props = defineProps<{
  open: boolean
  entry: RankingEntry | null
  isGroupOwner?: boolean
  isCurrentUser?: boolean
  canRemove?: boolean
  removing?: boolean
}>()

const emit = defineEmits<{
  close: []
  remove: []
  viewRanking: []
}>()

const hasRanking = computed(() => (props.entry?.position ?? 0) > 0)
</script>

<template>
  <Teleport to="body">
    <Transition name="overlay-fade">
      <div
        v-if="open"
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-[2px]"
        @click="emit('close')"
      />
    </Transition>

    <Transition name="sheet-up">
      <div
        v-if="open && entry"
        class="fixed bottom-0 left-0 right-0 z-50 rounded-t-2xl bg-surface-container"
        style="padding-bottom: env(safe-area-inset-bottom)"
      >
        <div class="flex justify-center pb-2 pt-3">
          <div class="h-1 w-10 rounded-full bg-on-surface-variant/20" />
        </div>

        <div class="px-margin pb-8">
          <div class="mb-6 flex flex-col items-center text-center">
            <div
              class="h-20 w-20 overflow-hidden rounded-full ring-2"
              :class="isCurrentUser ? 'ring-primary/40' : 'ring-white/10'"
            >
              <UiAvatar
                :name="entry.user.name"
                :src="entry.user.avatar_url"
                size="xl"
                class="!h-full !w-full"
              />
            </div>

            <h2 class="mt-4 font-headline-lg-mobile text-headline-lg-mobile uppercase tracking-tighter text-on-surface">
              {{ isCurrentUser ? 'Você' : entry.user.name }}
            </h2>

            <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
              <span
                v-if="isCurrentUser"
                class="rounded-full bg-primary/10 px-2.5 py-1 font-label-caps text-[9px] text-primary"
              >
                EU
              </span>
              <span
                v-if="isGroupOwner"
                class="rounded-full bg-secondary-container/15 px-2.5 py-1 font-label-caps text-[9px] text-secondary-container"
              >
                DONO
              </span>
            </div>

            <p
              v-if="!hasRanking"
              class="mt-3 font-body-sm text-[12px] text-on-surface-variant/55"
            >
              Ainda sem pontuação neste grupo
            </p>
          </div>

          <div v-if="hasRanking" class="mb-5 grid grid-cols-3 gap-2">
            <div class="glass-card rounded-xl px-2 py-3.5 text-center">
              <p class="font-mono text-[20px] font-bold tabular-nums text-primary">{{ entry.position }}º</p>
              <p class="mt-0.5 font-label-caps text-[9px] text-on-surface-variant/50">POSIÇÃO</p>
            </div>
            <div class="glass-card rounded-xl px-2 py-3.5 text-center">
              <p class="font-mono text-[20px] font-bold tabular-nums text-on-surface">{{ entry.total_points }}</p>
              <p class="mt-0.5 font-label-caps text-[9px] text-on-surface-variant/50">PONTOS</p>
            </div>
            <div class="glass-card rounded-xl px-2 py-3.5 text-center">
              <p class="font-mono text-[20px] font-bold tabular-nums text-on-surface">{{ entry.exact_scores }}</p>
              <p class="mt-0.5 font-label-caps text-[9px] text-on-surface-variant/50">EXATOS</p>
            </div>
          </div>

          <button
            type="button"
            class="mb-5 flex w-full items-center justify-center gap-2 rounded-xl border border-secondary-container/20 bg-secondary-container/10 py-3 font-label-caps text-label-caps text-on-surface transition-colors hover:bg-secondary-container/15 active:scale-[0.98]"
            @click="emit('viewRanking')"
          >
            <span
              class="material-symbols-outlined text-[18px] text-secondary-container"
              style="font-variation-settings: 'FILL' 1"
            >leaderboard</span>
            Ver classificação completa
          </button>

          <template v-if="canRemove">
            <div class="border-t border-white/5 pt-5">
              <button
                type="button"
                :disabled="removing"
                class="flex w-full items-center justify-center gap-2 rounded-xl border border-error/20 bg-error/5 py-3 font-label-caps text-label-caps text-error/90 transition-colors hover:bg-error/10 active:scale-[0.98] disabled:opacity-60"
                @click="emit('remove')"
              >
                <span class="material-symbols-outlined text-[18px]">person_remove</span>
                {{ removing ? 'Removendo…' : 'Remover do grupo' }}
              </button>
              <p class="mt-2 text-center font-body-sm text-[11px] text-on-surface-variant/45">
                O membro perderá o acesso e não poderá entrar novamente pelo convite.
              </p>
            </div>
          </template>

          <button
            type="button"
            class="mt-4 w-full rounded-xl border border-white/10 py-2.5 font-label-caps text-label-caps text-on-surface-variant transition-colors hover:bg-surface-container-high"
            @click="emit('close')"
          >
            Fechar
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
