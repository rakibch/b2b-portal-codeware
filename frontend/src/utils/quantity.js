/** Client mirror of the server MOQ/step rule (for steppers only; the API re-validates). */
export function nextValidQuantity(product, quantity, direction = 0) {
  const moq = Math.max(1, Number(product.moq) || 1)
  const step = Math.max(1, Number(product.step_quantity) || 1)
  const max = product.max_order_quantity ? Number(product.max_order_quantity) : Infinity
  let q = Number(quantity) || 0

  if (direction > 0) q = q < moq ? moq : q + step
  else if (direction < 0) q = q - step < moq ? moq : q - step
  else if (q <= moq) q = moq
  else q = moq + Math.ceil((q - moq) / step) * step

  return Math.min(q, max === Infinity ? q : moq + Math.floor((max - moq) / step) * step)
}

export function isValidQuantity(product, quantity) {
  const q = Number(quantity)
  const moq = Math.max(1, Number(product.moq) || 1)
  const step = Math.max(1, Number(product.step_quantity) || 1)
  if (!Number.isInteger(q) || q < moq) return false
  if (product.max_order_quantity && q > product.max_order_quantity) return false
  return (q - moq) % step === 0
}
