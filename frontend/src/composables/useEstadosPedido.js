// Mapeo de los estados de pedido del backend a etiquetas y estilos.
// Estados disponibles (migración orders): pendiente, pagado, enviado, entregado, cancelado, devuelto.
export const ESTADOS_PEDIDO = {
  pendiente: {
    etiqueta: 'Pendiente',
    clases: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
  },
  pagado: {
    etiqueta: 'Pagado',
    clases: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
  },
  enviado: {
    etiqueta: 'Enviado',
    clases: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
  },
  entregado: {
    etiqueta: 'Entregado',
    clases: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
  },
  cancelado: {
    etiqueta: 'Cancelado',
    clases: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
  },
  devuelto: {
    etiqueta: 'Devuelto',
    clases: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200',
  },
}

export function infoEstado(status) {
  return (
    ESTADOS_PEDIDO[status] || {
      etiqueta: status || 'Desconocido',
      clases: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
    }
  )
}

// ── Devoluciones ────────────────────────────────────────────────────────────
// Con el flujo automático de 14 días sólo existe el estado 'aprobada'.
export const ESTADOS_DEVOLUCION = {
  aprobada: {
    etiqueta: 'Aprobada',
    clases: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
  },
}

export function infoEstadoDevolucion(status) {
  return (
    ESTADOS_DEVOLUCION[status] || {
      etiqueta: status || 'Desconocido',
      clases: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
    }
  )
}

export const MOTIVOS_DEVOLUCION = [
  { valor: 'no_me_gusta',           etiqueta: 'No me gusta' },
  { valor: 'no_es_lo_que_esperaba', etiqueta: 'No es lo que esperaba' },
  { valor: 'cambio_opinion',        etiqueta: 'He cambiado de opinión' },
  { valor: 'defectuoso',            etiqueta: 'Defectuoso' },
  { valor: 'otro',                  etiqueta: 'Otro' },
]

export function etiquetaMotivo(reason) {
  return MOTIVOS_DEVOLUCION.find((m) => m.valor === reason)?.etiqueta ?? reason
}
