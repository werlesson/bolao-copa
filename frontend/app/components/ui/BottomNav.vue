<script setup lang="ts">
const route = useRoute()

const tabs = [
  { label: 'Jogos', icon: 'sports_soccer', to: '/jogos' },
  { label: 'Ranking', icon: 'leaderboard', to: '/ranking' },
  { label: 'Grupos', icon: 'groups', to: '/grupos' },
  { label: 'Perfil', icon: 'person', to: '/perfil' },
] as const

function isActive(path: string) {
  if (path === '/jogos') {
    return route.path === '/' || route.path === '/jogos' || route.path.startsWith('/jogos/')
  }
  return route.path === path || route.path.startsWith(`${path}/`)
}

function iconStyle(active: boolean) {
  return active
    ? 'font-variation-settings: "FILL" 1, "wght" 600, "GRAD" 0, "opsz" 24'
    : 'font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24'
}
</script>

<template>
  <nav
    class="fixed inset-x-0 bottom-0 z-50 rounded-t-2xl border-t border-white/10 bg-surface/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl shadow-[0_-4px_20px_rgba(101,223,118,0.15)]"
    aria-label="Navegação principal"
  >
    <div class="mx-auto flex h-20 max-w-2xl items-center justify-around px-3">
      <NuxtLink
        v-for="tab in tabs"
        :key="tab.to"
        :to="tab.to"
        class="relative flex flex-col items-center gap-1 px-5 py-2 select-none transition-transform duration-150 active:scale-90"
        :class="isActive(tab.to) ? 'text-primary' : 'text-on-surface-variant'"
        :aria-current="isActive(tab.to) ? 'page' : undefined"
      >
        <!-- Active pill -->
        <div
          class="absolute inset-0 rounded-2xl border transition-all duration-300 ease-out"
          :class="isActive(tab.to)
            ? 'bg-primary/15 border-primary/30 opacity-100 scale-100 shadow-[0_0_14px_rgba(101,223,118,0.18)]'
            : 'bg-transparent border-transparent opacity-0 scale-90'"
          aria-hidden="true"
        />

        <!-- Icon -->
        <span
          class="material-symbols-outlined z-10 leading-none transition-all duration-300 ease-out"
          :class="isActive(tab.to) ? 'text-[26px] -translate-y-0.5' : 'text-[24px] translate-y-0'"
          :style="iconStyle(isActive(tab.to))"
        >{{ tab.icon }}</span>

        <!-- Label -->
        <span
          class="font-label-caps text-label-caps z-10 leading-none transition-all duration-300 ease-out"
          :class="isActive(tab.to) ? 'opacity-100' : 'opacity-45'"
        >{{ tab.label }}</span>
      </NuxtLink>
    </div>
  </nav>
</template>
