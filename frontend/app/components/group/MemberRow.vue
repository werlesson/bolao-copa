<script setup lang="ts">
import type { GroupMember } from '~/types/group'

const props = defineProps<{
  member: GroupMember
  isCurrentUser?: boolean
  isGroupOwner?: boolean
  interactive?: boolean
  rankingPosition?: number | null
  compact?: boolean
}>()

const emit = defineEmits<{
  select: []
}>()

const displayName = computed(() =>
  props.isCurrentUser ? 'Você' : props.member.name,
)

const joinedLabel = computed(() => {
  if (!props.member.joined_at) return null
  const formatted = new Intl.DateTimeFormat('pt-BR', {
    month: 'short',
    year: 'numeric',
  }).format(new Date(props.member.joined_at))
  return `Desde ${formatted.replace('.', '')}`
})

const roleLabel = computed(() => {
  if (props.isCurrentUser && props.isGroupOwner) return 'Dono do grupo'
  if (props.isGroupOwner) return 'Dono do grupo'
  if (props.isCurrentUser) return 'Seu perfil'
  return joinedLabel.value
})
</script>

<template>
  <component
    :is="interactive ? 'button' : 'div'"
    :type="interactive ? 'button' : undefined"
    class="flex w-full items-center gap-3 text-left transition-colors"
    :class="[
      compact ? 'px-1 py-2' : 'glass-card rounded-xl px-3 py-3',
      isCurrentUser && !compact ? 'border border-primary/15 bg-primary/[0.04]' : '',
      interactive ? 'cursor-pointer hover:bg-surface-container-high/60 active:scale-[0.99]' : '',
    ]"
    @click="interactive && emit('select')"
  >
    <div
      class="shrink-0 overflow-hidden rounded-full ring-1"
      :class="[
        compact ? 'h-8 w-8' : 'h-10 w-10',
        isCurrentUser ? 'ring-primary/35' : isGroupOwner ? 'ring-secondary-container/40' : 'ring-white/10',
      ]"
    >
      <UiAvatar
        :name="member.name"
        :src="member.avatar_url"
        size="md"
        class="!h-full !w-full"
      />
    </div>

    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-1.5">
        <p
          class="truncate font-body-sm font-semibold leading-tight text-on-surface"
          :class="compact ? 'text-[13px]' : 'text-[14px]'"
        >
          {{ displayName }}
        </p>
        <span
          v-if="isGroupOwner"
          class="material-symbols-outlined shrink-0 text-[14px] leading-none text-secondary-container"
          style="font-variation-settings: 'FILL' 1"
          title="Dono do grupo"
        >verified</span>
      </div>
      <p
        v-if="roleLabel"
        class="mt-0.5 truncate font-body-sm text-[11px] text-on-surface-variant/50"
      >
        {{ roleLabel }}
      </p>
    </div>

    <div v-if="rankingPosition && rankingPosition > 0" class="shrink-0 text-right">
      <p class="font-mono text-[12px] font-medium tabular-nums text-on-surface-variant/35">
        {{ rankingPosition }}º
      </p>
    </div>

    <span
      v-if="interactive"
      class="material-symbols-outlined shrink-0 text-[18px] text-on-surface-variant/20"
      aria-hidden="true"
    >chevron_right</span>
  </component>
</template>
