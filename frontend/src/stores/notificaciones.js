import { defineStore } from 'pinia'

let contadorIds = 0

// Pequeño store de notificaciones flotantes (toasts).
// Las acciones devuelven el id de la notificación por si se quiere cerrar manualmente.
export const useNotificacionesStore = defineStore('notificaciones', {
  state: () => ({
    notificaciones: [],
  }),

  actions: {
    agregar({ tipo = 'info', mensaje = '', duracion = 4000 }) {
      const id = ++contadorIds
      this.notificaciones.push({ id, tipo, mensaje })
      if (duracion > 0) {
        window.setTimeout(() => this.cerrar(id), duracion)
      }
      return id
    },

    exito(mensaje, duracion) {
      return this.agregar({ tipo: 'success', mensaje, duracion })
    },

    error(mensaje, duracion) {
      return this.agregar({ tipo: 'error', mensaje, duracion: duracion ?? 5000 })
    },

    info(mensaje, duracion) {
      return this.agregar({ tipo: 'info', mensaje, duracion })
    },

    advertencia(mensaje, duracion) {
      return this.agregar({ tipo: 'warning', mensaje, duracion })
    },

    cerrar(id) {
      this.notificaciones = this.notificaciones.filter((t) => t.id !== id)
    },
  },
})
