import { defineStore } from 'pinia'
import api from '@/api'

// Store del carrito. Solo opera con un usuario autenticado: el backend usa el
// token Sanctum para vincular el carrito al usuario.
export const useCarritoStore = defineStore('carrito', {
  state: () => ({
    articulos: [],
    total: 0,
    cantidadArticulos: 0,
    unidadesTotales: 0,
    cargando: false,
    error: null,
  }),

  getters: {
    cantidad: (state) => state.cantidadArticulos,
    estaVacio: (state) => state.articulos.length === 0,
  },

  actions: {
    /**
     * Aplica los datos devueltos por la API a este store.
     */
    aplicarRespuesta(payload) {
      const carrito = payload?.data?.cart ?? null
      if (!carrito) return
      this.articulos = carrito.items ?? []
      this.total = carrito.total ?? 0
      this.cantidadArticulos = carrito.items_count ?? this.articulos.length
      this.unidadesTotales = carrito.total_units ?? 0
    },

    reiniciar() {
      this.articulos = []
      this.total = 0
      this.cantidadArticulos = 0
      this.unidadesTotales = 0
      this.error = null
    },

    async obtenerCarrito() {
      this.cargando = true
      try {
        const { data } = await api.get('/cart')
        this.aplicarRespuesta(data)
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'No se pudo obtener el carrito.'
      } finally {
        this.cargando = false
      }
    },

    async agregarArticulo({ productId, quantity = 1 }) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.post('/cart/items', {
          product_id: productId,
          quantity,
        })
        this.aplicarRespuesta(data)
        return true
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'No se pudo añadir el producto.'
        return false
      } finally {
        this.cargando = false
      }
    },

    async actualizarArticulo({ itemId, quantity }) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.put(`/cart/items/${itemId}`, { quantity })
        this.aplicarRespuesta(data)
        return true
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'No se pudo actualizar el artículo.'
        return false
      } finally {
        this.cargando = false
      }
    },

    async eliminarArticulo(itemId) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.delete(`/cart/items/${itemId}`)
        this.aplicarRespuesta(data)
        return true
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'No se pudo eliminar el artículo.'
        return false
      } finally {
        this.cargando = false
      }
    },

    async vaciarCarrito() {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.delete('/cart')
        this.aplicarRespuesta(data)
        return true
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'No se pudo vaciar el carrito.'
        return false
      } finally {
        this.cargando = false
      }
    },
  },
})
