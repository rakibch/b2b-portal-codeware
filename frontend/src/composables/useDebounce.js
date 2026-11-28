import { onBeforeUnmount, ref, watch } from 'vue'

/** Debounced mirror of a ref (live search). */
export function useDebouncedRef(source, delay = 300) {
  const debounced = ref(source.value)
  let timer = null

  watch(source, (value) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      debounced.value = value
    }, delay)
  })

  onBeforeUnmount(() => clearTimeout(timer))

  return debounced
}

export function debounce(fn, delay = 300) {
  let timer = null
  const wrapped = (...args) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
  wrapped.cancel = () => clearTimeout(timer)
  return wrapped
}
