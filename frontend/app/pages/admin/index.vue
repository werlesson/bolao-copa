<script setup lang="ts">
import type { Match } from '~/types/match'
import { unwrapList } from '~/utils/api'

definePageMeta({
  middleware: 'admin',
  layout: 'minimal',
})

interface AdminUser {
  id: string
  name: string
  email: string
  predictions_count: number
}

const config = useRuntimeConfig()
const apiUrl = config.public.apiUrl as string

const matches = ref<Match[]>([])
const users = ref<AdminUser[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const syncing = ref(false)
const syncMessage = ref<string | null>(null)
const showSyncDialog = ref(false)

function formatSyncedAt(value: string | null | undefined): string {
  if (!value) return '—'
  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'America/Sao_Paulo',
  })
}

async function loadAdminData() {
  loading.value = true
  error.value = null
  try {
    const [matchesRes, usersRes] = await Promise.all([
      $fetch<Match[] | { data: Match[] }>('/api/admin/matches', {
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      }),
      $fetch<{ data: AdminUser[] }>('/api/admin/users', {
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      }),
    ])
    matches.value = unwrapList(matchesRes)
    users.value = usersRes.data ?? []
  } catch (err: unknown) {
    matches.value = []
    users.value = []
    error.value = 'Não foi possível carregar o painel admin.'
    console.error('[admin] load failed', err)
  } finally {
    loading.value = false
  }
}

async function forceSync() {
  if (syncing.value) return
  syncing.value = true
  syncMessage.value = null
  try {
    const response = await $fetch<{ message?: string }>('/api/admin/matches/sync', {
      baseURL: apiUrl,
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    syncMessage.value = response.message ?? 'Sincronização disparada.'
    await loadAdminData()
  } catch (err: unknown) {
    syncMessage.value = 'Falha ao disparar sincronização.'
    console.error('[admin] sync failed', err)
  } finally {
    syncing.value = false
  }
}

onMounted(() => {
  loadAdminData()
})
</script>

<template>
  <div class="min-h-dvh bg-background px-margin py-6 text-on-surface">
    <header class="mb-6 flex items-center gap-3">
      <NuxtLink
        to="/jogos"
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high"
        aria-label="Voltar"
      >
        <span class="material-symbols-outlined">arrow_back</span>
      </NuxtLink>
      <h1 class="text-headline-lg font-bold">
        Admin
      </h1>
    </header>

    <p
      v-if="loading"
      class="text-body-md text-on-surface-variant"
    >
      Carregando…
    </p>

    <p
      v-else-if="error"
      class="text-body-md text-error"
    >
      {{ error }}
    </p>

    <template v-else>
      <section class="mb-10">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-label-sm font-bold uppercase tracking-wider text-on-surface-variant">
            Jogos
          </h2>
          <button
            type="button"
            :disabled="syncing"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2 text-label-sm font-bold text-on-primary transition-opacity hover:opacity-90 disabled:opacity-50"
            @click="showSyncDialog = true"
          >
            <span class="material-symbols-outlined text-[18px]">sync</span>
            {{ syncing ? 'Sincronizando…' : 'Forçar Sync' }}
          </button>
        </div>

        <p
          v-if="syncMessage"
          class="mb-3 text-label-sm text-on-surface-variant"
        >
          {{ syncMessage }}
        </p>

        <div class="overflow-x-auto rounded-lg border border-outline-variant">
          <table class="w-full min-w-[640px] text-left text-label-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low">
              <tr>
                <th class="px-3 py-2 font-semibold text-on-surface-variant">
                  external_id
                </th>
                <th class="px-3 py-2 font-semibold text-on-surface-variant">
                  Times
                </th>
                <th class="px-3 py-2 font-semibold text-on-surface-variant">
                  Status
                </th>
                <th class="px-3 py-2 font-semibold text-on-surface-variant">
                  synced_at
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="match in matches"
                :key="match.id"
                class="border-b border-outline-variant/60 last:border-0"
              >
                <td class="px-3 py-2 font-mono text-primary">
                  {{ match.external_id ?? '—' }}
                </td>
                <td class="px-3 py-2">
                  {{ match.home_team }} × {{ match.away_team }}
                </td>
                <td class="px-3 py-2">
                  {{ match.status }}
                </td>
                <td class="px-3 py-2 text-on-surface-variant">
                  {{ formatSyncedAt(match.synced_at) }}
                </td>
              </tr>
              <tr v-if="matches.length === 0">
                <td
                  colspan="4"
                  class="px-3 py-6 text-center text-on-surface-variant"
                >
                  Nenhum jogo cadastrado.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h2 class="mb-4 text-label-sm font-bold uppercase tracking-wider text-on-surface-variant">
          Usuários
        </h2>

        <div class="overflow-x-auto rounded-lg border border-outline-variant">
          <table class="w-full min-w-[480px] text-left text-label-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low">
              <tr>
                <th class="px-3 py-2 font-semibold text-on-surface-variant">
                  Nome
                </th>
                <th class="px-3 py-2 font-semibold text-on-surface-variant">
                  E-mail
                </th>
                <th class="px-3 py-2 font-semibold text-on-surface-variant">
                  Palpites
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="user in users"
                :key="user.id"
                class="border-b border-outline-variant/60 last:border-0"
              >
                <td class="px-3 py-2">
                  {{ user.name }}
                </td>
                <td class="px-3 py-2 text-on-surface-variant">
                  {{ user.email }}
                </td>
                <td class="px-3 py-2 font-mono">
                  {{ user.predictions_count }}
                </td>
              </tr>
              <tr v-if="users.length === 0">
                <td
                  colspan="3"
                  class="px-3 py-6 text-center text-on-surface-variant"
                >
                  Nenhum usuário.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <UiConfirmDialog
      :open="showSyncDialog"
      title="Forçar sincronização?"
      message="Isso irá disparar uma sincronização completa dos jogos com a API externa. Confirmar?"
      confirm-label="Sincronizar"
      cancel-label="Cancelar"
      @confirm="showSyncDialog = false; forceSync()"
      @cancel="showSyncDialog = false"
    />
  </div>
</template>
