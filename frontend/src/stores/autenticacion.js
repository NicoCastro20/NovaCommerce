import { defineStore } from 'pinia'
import api, { establecerToken, obtenerToken } from '@/api'

// Store de autenticación. Persiste el token en localStorage y lo aplica al header
// Authorization de axios en cada arranque.
export const useAutenticacionStore = defineStore('autenticacion', {
  state: () => ({
    usuario: null,
    token: obtenerToken(),
    cargando: false,
    error: null,
  }),

  getters: {
    estaAutenticado: (state) => Boolean(state.token && state.usuario),
    rol: (state) => state.usuario?.role ?? null,
    esAdmin: (state) => state.usuario?.role === 'admin',
    esEmpresa: (state) => state.usuario?.role === 'empresa',
    esUsuario: (state) => state.usuario?.role === 'usuario',
  },

  actions: {
    /**
     * Inicia sesión con email/contraseña. Devuelve true si tuvo éxito.
     */
    async iniciarSesion(credenciales) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.post('/login', credenciales)
        const payload = data?.data ?? {}
        this.token = payload.token ?? null
        this.usuario = payload.user ?? null
        establecerToken(this.token)
        return true
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'No se pudo iniciar sesión.'
        return false
      } finally {
        this.cargando = false
      }
    },

    /**
     * Registra un usuario particular nuevo.
     */
    async registrar(datos) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.post('/register', datos)
        const payload = data?.data ?? {}
        this.token = payload.token ?? null
        this.usuario = payload.user ?? null
        establecerToken(this.token)
        return true
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'No se pudo crear la cuenta.'
        return false
      } finally {
        this.cargando = false
      }
    },

    /**
     * Registra una empresa nueva (con NIF/CIF y nombre comercial).
     */
    async registrarEmpresa(datos) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.post('/register/empresa', datos)
        const payload = data?.data ?? {}
        this.token = payload.token ?? null
        this.usuario = payload.user ?? null
        establecerToken(this.token)
        return true
      } catch (err) {
        this.error = err?.response?.data?.message ?? 'No se pudo registrar la empresa.'
        return false
      } finally {
        this.cargando = false
      }
    },

    /**
     * Cierra sesión. Si `silencioso` es true no llama al backend (lo invoca el
     * interceptor 401 cuando ya sabemos que el token caducó).
     */
    async cerrarSesion({ silencioso = false } = {}) {
      if (!silencioso && this.token) {
        try {
          await api.post('/logout')
        } catch {
          // Ignorar errores: aunque la API falle vamos a limpiar el estado local.
        }
      }
      this.token = null
      this.usuario = null
      establecerToken(null)
    },

    /**
     * Recupera el perfil del usuario autenticado. Útil tras recargar la página
     * cuando solo tenemos el token en localStorage.
     */
    async obtenerUsuario() {
      if (!this.token) return null
      try {
        const { data } = await api.get('/user')
        this.usuario = data?.data?.user ?? null
        return this.usuario
      } catch {
        // Si falla, descartar la sesión local.
        this.token = null
        this.usuario = null
        establecerToken(null)
        return null
      }
    },
  },
})
