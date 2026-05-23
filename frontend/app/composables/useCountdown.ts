import { toValue, type MaybeRefOrGetter } from 'vue'

function parseStartsAt(value: Date | string): number {
  const date = value instanceof Date ? value : new Date(value)
  return date.getTime()
}

function formatCountdown(ms: number): string {
  if (ms <= 0) return 'AO VIVO'

  const totalSeconds = Math.floor(ms / 1000)
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60

  if (hours >= 1) return `em ${hours}h ${minutes}min`
  if (minutes >= 1) return `em ${minutes}min ${seconds}s`
  return `em ${seconds}s`
}

export function useCountdown(startsAt: MaybeRefOrGetter<Date | string>) {
  const { now } = useGlobalClock()

  const remainingMs = computed(() =>
    Math.max(0, parseStartsAt(toValue(startsAt)) - now.value),
  )

  const label = computed(() => formatCountdown(remainingMs.value))
  const isLive = computed(() => remainingMs.value <= 0)

  const kickoffListeners = new Set<() => void>()
  let kickoffEmitted = false

  watch(remainingMs, (next, prev) => {
    if (prev > 0 && next === 0 && !kickoffEmitted) {
      kickoffEmitted = true
      kickoffListeners.forEach(fn => fn())
    }
  })

  watch(
    () => toValue(startsAt),
    () => { kickoffEmitted = false },
  )

  function on(event: 'kickoff', handler: () => void) {
    if (event !== 'kickoff') return () => {}
    kickoffListeners.add(handler)
    return () => kickoffListeners.delete(handler)
  }

  return { label, isLive, remainingMs, on }
}
