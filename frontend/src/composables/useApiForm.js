import { reactive, ref } from 'vue'
import { ApiError } from '@/api/ApiError'
import { useToastStore } from '@/stores/toast'

/**
 * Form submission helper: tracks busy state, maps Laravel 422 errors to fields
 * (dot-notation keys preserved) and surfaces business-rule errors as a form-level message.
 */
export function useApiForm(initial = {}) {
  const form = reactive({ ...initial })
  const errors = ref({})
  const formError = ref(null)
  const busy = ref(false)
  const toast = useToastStore()

  function fieldError(name) {
    const value = errors.value[name]
    return Array.isArray(value) ? value[0] : (value ?? null)
  }

  function reset(values = initial) {
    Object.keys(form).forEach((k) => delete form[k])
    Object.assign(form, { ...values })
    errors.value = {}
    formError.value = null
  }

  async function submit(handler, { successMessage = null } = {}) {
    busy.value = true
    errors.value = {}
    formError.value = null
    try {
      const result = await handler(form)
      if (successMessage) toast.success(successMessage)
      return result
    } catch (e) {
      if (e instanceof ApiError) {
        if (e.isValidation) errors.value = e.errors
        else formError.value = e.message
      } else {
        formError.value = String(e?.message ?? e)
      }
      throw e
    } finally {
      busy.value = false
    }
  }

  return { form, errors, formError, busy, fieldError, submit, reset }
}
