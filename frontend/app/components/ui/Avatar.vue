<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    name: string
    src?: string | null
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  }>(),
  { size: 'md' },
)

const sizeClasses: Record<string, string> = {
  xs: 'w-6 h-6 text-[10px]',
  sm: 'w-8 h-8 text-xs',
  md: 'w-10 h-10 text-sm',
  lg: 'w-12 h-12 text-base',
  xl: 'w-16 h-16 text-lg',
}

const palette = [
  '#7c3aed', '#2563eb', '#059669', '#d97706', '#dc2626',
  '#db2777', '#0891b2', '#65a30d', '#9333ea', '#ea580c',
]

function hashColor(name: string): string {
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return palette[Math.abs(hash) % palette.length]!
}

function getInitials(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .map(word => word[0])
    .slice(0, 2)
    .join('')
    .toUpperCase() || '?'
}

const imageError = ref(false)

watch(() => props.src, () => {
  imageError.value = false
})

const showFallback = computed(() => !props.src || imageError.value)
const bg = computed(() => hashColor(props.name || '?'))
const sizeClass = computed(() => sizeClasses[props.size])
</script>

<template>
  <div
    :class="['flex-shrink-0 overflow-hidden rounded-full', sizeClass]"
    role="img"
    :aria-label="name"
  >
    <img
      v-if="!showFallback"
      :src="src!"
      :alt="name"
      class="h-full w-full object-cover"
      referrerpolicy="no-referrer"
      @error="imageError = true"
    >
    <div
      v-else
      class="flex h-full w-full select-none items-center justify-center font-bold text-white"
      :style="{ backgroundColor: bg }"
    >
      {{ getInitials(name) }}
    </div>
  </div>
</template>
