import { defineStore } from 'pinia'
import api from '@/api'

// Store de productos del catálogo público.
export const useProductoStore = defineStore('producto', {
  state: () => ({
    productos: [],
    producto: null,
    meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
    filtros: {
      search: '',
      category: '',
      type: '',
      min_price: null,
      max_price: null,
      sort: 'newest',
      page: 1,
      per_page: 12,
    },
    cargando: false,
    error: null,
  }),

  getters: {
    estaVacio: (state) => state.productos.length === 0,
  },

  actions: {
    establecerFiltro(parche) {
      this.filtros = { ...this.filtros, ...parche }
    },

    async obtenerProductos(extras = {}) {
      this.cargando = true
      this.error = null
      try {
        const params = { ...this.filtros, ...extras }
        // Limpiar valores vacíos para no ensuciar la query string.
        Object.keys(params).forEach((k) => {
          if (params[k] === null || params[k] === '' || params[k] === undefined) {
            delete params[k]
          }
        })

        const { data } = await api.get('/products', { params })
        this.productos = data?.data ?? []
        this.meta = data?.meta ?? this.meta
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'No se pudieron obtener los productos.'
      } finally {
        this.cargando = false
      }
    },

    async obtenerProducto(slug) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.get(`/products/${slug}`)
        this.producto = data?.data?.product ?? null
        return this.producto
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'Producto no encontrado.'
        this.producto = null
        return null
      } finally {
        this.cargando = false
      }
    },
  },
})
