export function useToast() {
  const visible = ref(false)
  const message = ref('')
  let timer: ReturnType<typeof setTimeout> | null = null

  function show(msg: string, duration = 4500) {
    if (timer) clearTimeout(timer)
    message.value = msg
    visible.value = true
    timer = setTimeout(() => {
      visible.value = false
    }, duration)
  }

  onUnmounted(() => {
    if (timer) clearTimeout(timer)
  })

  return { visible, message, show }
}
