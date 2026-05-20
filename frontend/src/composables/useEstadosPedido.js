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
export const ESTADOS_DEVOLUCION = {
  solicitada: {
    etiqueta: 'Solicitada',
    clases: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
  },
  aprobada: {
    etiqueta: 'Aprobada',
    clases: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
  },
  rechazada: {
    etiqueta: 'Rechazada',
    clases: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
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
  { valor: 'producto_defectuoso',     etiqueta: 'Producto defectuoso' },
  { valor: 'no_coincide_descripcion', etiqueta: 'No coincide con la descripción' },
  { valor: 'producto_dañado',         etiqueta: 'Producto dañado en el transporte' },
  { valor: 'error_en_pedido',         etiqueta: 'Error en el pedido' },
  { valor: 'otro',                    etiqueta: 'Otro motivo' },
]

export function etiquetaMotivo(reason) {
  return MOTIVOS_DEVOLUCION.find((m) => m.valor === reason)?.etiqueta ?? reason
}
