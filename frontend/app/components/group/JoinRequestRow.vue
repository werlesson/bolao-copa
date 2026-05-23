<script setup lang="ts">
import type { JoinRequest } from '~/types/group'

const props = defineProps<{
  request: JoinRequest
  busy?: boolean
}>()

const emit = defineEmits<{
  approve: []
  reject: []
}>()

const userName = computed(() => props.request.user?.name ?? 'Usuário')
</script>

<template>
  <div class="glass-card flex items-center gap-4 rounded-xl p-4">
    <UiAvatar
      :name="userName"
      :src="request.user?.avatar_url"
      size="md"
    />

    <div class="min-w-0 flex-1">
      <p class="truncate font-title-md text-title-md text-on-surface">
        {{ userName }}
      </p>
      <p class="font-label-caps text-label-caps text-on-surface-variant">
        QUER ENTRAR NO GRUPO
      </p>
    </div>

    <div class="flex shrink-0 gap-2">
      <button
        type="button"
        :disabled="busy"
        class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-on-primary shadow-sm shadow-primary/20 transition-opacity hover:brightness-110 disabled:opacity-50"
        aria-label="Aprovar"
        @click="emit('approve')"
      >
        <span class="material-symbols-outlined text-[20px]">check</span>
      </button>
      <button
        type="button"
        :disabled="busy"
        class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-surface-container-high text-on-surface transition-opacity hover:bg-surface-bright disabled:opacity-50"
        aria-label="Rejeitar"
        @click="emit('reject')"
      >
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>
  </div>
</template>
