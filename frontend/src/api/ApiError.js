/** Normalised API error shape used throughout the UI. */
export class ApiError extends Error {
  constructor({ status = 0, code = null, message = '', errors = {}, context = null } = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.errors = errors
    this.context = context
  }

  get isValidation() {
    return this.status === 422 && Object.keys(this.errors).length > 0
  }

  /** First message for a field (Laravel returns arrays). */
  field(name) {
    const value = this.errors?.[name]
    return Array.isArray(value) ? value[0] : value ?? null
  }
}
