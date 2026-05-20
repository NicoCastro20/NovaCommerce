import axios from 'axios'
import { useProgreso } from '@/composables/useProgreso'

// ── Instancia base de axios ─────────────────────────────────────────────────
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

const progreso = useProgreso()

// ── Token persistente en localStorage ───────────────────────────────────────
// Se aplica al arrancar la app y al iniciar sesión.
const TOKEN_KEY = 'novacommerce.token'

export function obtenerToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function establecerToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
    api.defaults.headers.common.Authorization = `Bearer ${token}`
  } else {
    localStorage.removeItem(TOKEN_KEY)
    delete api.defaults.headers.common.Authorization
  }
}

// Cargar token en la primera importación.
const tokenInicial = obtenerToken()
if (tokenInicial) {
  api.defaults.headers.common.Authorization = `Bearer ${tokenInicial}`
}

// ── Interceptor de petición: añade el token actualizado y arranca la barra ──
api.interceptors.request.use((config) => {
  const token = obtenerToken()
  if (token && !config.headers.Authorization) {
    config.headers.Authorization = `Bearer ${token}`
  }
  if (config.mostrarProgreso !== false) {
    progreso.iniciar()
    config._progresoIniciado = true
  }
  return config
})

// ── Interceptor de respuesta: 401 -> limpiar sesión y redirigir ─────────────
// El router, el store y el toast se inyectan al arrancar la app para evitar
// dependencias circulares.
let refRouter = null
let refLogoutAuth = null
let refToastError = null

export function registrarManejador401({ router, logout, toastError }) {
  refRouter = router
  refLogoutAuth = logout
  refToastError = toastError ?? refToastError
}

api.interceptors.response.use(
  (respuesta) => {
    if (respuesta?.config?._progresoIniciado) progreso.terminar()
    return respuesta
  },
  (error) => {
    if (error?.config?._progresoIniciado) progreso.terminar()

    const status = error?.response?.status

    // Error de red: la API no respondió.
    if (!error?.response) {
      if (typeof refToastError === 'function') {
        refToastError(
          'No se pudo conectar con el servidor. Comprueba tu conexión e inténtalo de nuevo.',
        )
      }
      return Promise.reject(error)
    }

    if (status === 401) {
      establecerToken(null)
      if (typeof refLogoutAuth === 'function') {
        refLogoutAuth({ silencioso: true })
      }
      const rutaActual = refRouter?.currentRoute?.value?.fullPath
      if (refRouter && rutaActual !== '/login') {
        refRouter.push({
          path: '/login',
          query: rutaActual ? { redirect: rutaActual } : {},
        })
      }
    } else if (status >= 500) {
      if (typeof refToastError === 'function') {
        refToastError('El servidor ha tenido un problema. Inténtalo de nuevo en unos instantes.')
      }
    }

    return Promise.reject(error)
  },
)

export default api
