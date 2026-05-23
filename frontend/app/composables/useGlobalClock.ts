const _now = ref(Date.now())
let _interval: ReturnType<typeof setInterval> | null = null
let _count = 0

function startClock() {
  if (!_interval) {
    _now.value = Date.now()
    _interval = setInterval(() => {
      _now.value = Date.now()
    }, 1000)
  }
}

function stopClock() {
  if (_interval) {
    clearInterval(_interval)
    _interval = null
  }
}

export function useGlobalClock() {
  onMounted(() => {
    _count++
    startClock()
  })

  onUnmounted(() => {
    _count--
    if (_count === 0) stopClock()
  })

  return { now: readonly(_now) }
}
