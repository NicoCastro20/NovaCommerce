import { useAutenticacionStore } from '@/stores/autenticacion'

/**
 * Exige usuario autenticado. Si no lo está, redirige a /login conservando la
 * ruta original como query `redirect`.
 */
export async function authGuard(to) {
  const auth = useAutenticacionStore()

  // Si tenemos token pero aún no cargamos el usuario, intentar recuperarlo.
  if (auth.token && !auth.usuario) {
    await auth.obtenerUsuario()
  }

  if (!auth.estaAutenticado) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
  return true
}

/**
 * Solo accesible para usuarios NO autenticados (login, registro).
 */
export async function guestGuard() {
  const auth = useAutenticacionStore()
  if (auth.token && !auth.usuario) {
    await auth.obtenerUsuario()
  }
  if (auth.estaAutenticado) {
    return { path: '/' }
  }
  return true
}

/**
 * Verifica que el usuario tenga uno de los roles permitidos. Asume que el
 * authGuard ya ha pasado, pero por seguridad lo vuelve a comprobar.
 */
export function roleGuard(rolesPermitidos) {
  const lista = Array.isArray(rolesPermitidos) ? rolesPermitidos : [rolesPermitidos]

  return async (to) => {
    const auth = useAutenticacionStore()
    if (auth.token && !auth.usuario) {
      await auth.obtenerUsuario()
    }
    if (!auth.estaAutenticado) {
      return { path: '/login', query: { redirect: to.fullPath } }
    }
    // Admin siempre puede entrar a cualquier área restringida por rol.
    if (auth.esAdmin) return true
    if (!lista.includes(auth.rol)) {
      return { path: '/', query: { error: 'no_autorizado' } }
    }
    return true
  }
}
