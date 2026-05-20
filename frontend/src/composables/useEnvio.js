// Reglas de envío del frontend.
// El backend aplica las mismas reglas al crear el pedido (y además guarda
// envio_premium=true cuando el envío fue gratis por suscripción Premium).
export const ENVIO_GRATIS_DESDE = 50
export const COSTE_ENVIO = 5
export const PRECIO_PREMIUM = 50

export function calcularEnvio(subtotal, esPremium = false) {
  if (esPremium) return 0
  if (Number(subtotal) >= ENVIO_GRATIS_DESDE) return 0
  return COSTE_ENVIO
}

export function formatearEur(valor) {
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(
    Number(valor || 0),
  )
}
