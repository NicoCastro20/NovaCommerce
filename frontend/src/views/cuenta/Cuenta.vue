<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '@/api'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useNotificacionesStore } from '@/stores/notificaciones'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'

const auth = useAutenticacionStore()
const toast = useNotificacionesStore()
const router = useRouter()

// ── Suscripción Premium ─────────────────────────────────────────────────────
const suscripcion = ref(null)
const cargandoSuscripcion = ref(false)
const cancelandoSuscripcion = ref(false)
const dialogoCancelarPremium = ref(false)

const esPremium = computed(() => Boolean(auth.usuario?.premium_active || suscripcion.value?.activa))
const sigueRenovando = computed(() => Boolean(auth.usuario?.is_premium ?? suscripcion.value?.is_premium))

async function cargarSuscripcion() {
  if (!auth.estaAutenticado) return
  cargandoSuscripcion.value = true
  try {
    const { data } = await api.get('/suscripcion')
    suscripcion.value = data?.data?.suscripcion ?? null
  } catch {
    // Silencioso: el usuario verá la opción de hacerse Premium si la API falla.
  } finally {
    cargandoSuscripcion.value = false
  }
}

async function cancelarPremium() {
  cancelandoSuscripcion.value = true
  try {
    const { data } = await api.post('/suscripcion/cancelar')
    suscripcion.value = data?.data?.suscripcion ?? suscripcion.value
    auth.usuario = {
      ...auth.usuario,
      is_premium: false,
      premium_active: suscripcion.value?.activa ?? auth.usuario?.premium_active,
    }
    toast.exito(data?.message ?? 'Suscripción cancelada.')
    dialogoCancelarPremium.value = false
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo cancelar la suscripción.')
  } finally {
    cancelandoSuscripcion.value = false
  }
}

const perfil = reactive({
  name: '',
  phone: '',
  avatar: '',
})
const erroresPerfil = ref({})
const guardandoPerfil = ref(false)

const passwords = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})
const erroresPassword = ref({})
const guardandoPassword = ref(false)

function sincronizarDesdeAuth() {
  perfil.name = auth.usuario?.name ?? ''
  perfil.phone = auth.usuario?.phone ?? ''
  perfil.avatar = auth.usuario?.avatar ?? ''
}

watch(() => auth.usuario, sincronizarDesdeAuth, { immediate: true })

async function actualizarPerfil() {
  erroresPerfil.value = {}

  if (!perfil.name.trim()) {
    erroresPerfil.value.name = 'El nombre es obligatorio.'
    return
  }
  if (perfil.phone && perfil.phone.length > 20) {
    erroresPerfil.value.phone = 'El teléfono no puede superar los 20 caracteres.'
    return
  }

  guardandoPerfil.value = true
  try {
    const { data } = await api.put('/user', {
      name: perfil.name.trim(),
      phone: perfil.phone?.trim() || null,
      avatar: perfil.avatar?.trim() || null,
    })
    if (data?.data?.user) {
      auth.usuario = { ...auth.usuario, ...data.data.user }
    }
    toast.exito('Perfil actualizado correctamente.')
  } catch (err) {
    const status = err?.response?.status
    if (status === 422 && err?.response?.data?.errors) {
      erroresPerfil.value = err.response.data.errors
    }
    toast.error(err?.response?.data?.message ?? 'No se pudo actualizar el perfil.')
  } finally {
    guardandoPerfil.value = false
  }
}

async function cambiarPassword() {
  erroresPassword.value = {}

  const e = {}
  if (!passwords.current_password) e.current_password = 'Debes introducir tu contraseña actual.'
  if (!passwords.new_password) e.new_password = 'Debes introducir la nueva contraseña.'
  else if (passwords.new_password.length < 8) e.new_password = 'La nueva contraseña debe tener al menos 8 caracteres.'
  else if (!/[A-Za-z]/.test(passwords.new_password) || !/\d/.test(passwords.new_password)) {
    e.new_password = 'La nueva contraseña debe contener al menos una letra y un número.'
  }
  if (passwords.new_password !== passwords.new_password_confirmation) {
    e.new_password_confirmation = 'Las contraseñas no coinciden.'
  }
  if (passwords.new_password && passwords.new_password === passwords.current_password) {
    e.new_password = 'La nueva contraseña debe ser distinta a la actual.'
  }

  if (Object.keys(e).length > 0) {
    erroresPassword.value = e
    return
  }

  guardandoPassword.value = true
  try {
    await api.put('/user/password', {
      current_password: passwords.current_password,
      new_password: passwords.new_password,
      new_password_confirmation: passwords.new_password_confirmation,
    })
    toast.exito('Contraseña actualizada. Vuelve a iniciar sesión.')
    // El backend revoca todos los tokens. Limpiar y redirigir.
    await auth.cerrarSesion({ silencioso: true })
    router.replace({ path: '/login' })
  } catch (err) {
    const status = err?.response?.status
    if (status === 422 && err?.response?.data?.errors) {
      erroresPassword.value = err.response.data.errors
    }
    toast.error(err?.response?.data?.message ?? 'No se pudo cambiar la contraseña.')
  } finally {
    guardandoPassword.value = false
  }
}

function valor(err) {
  return Array.isArray(err) ? err[0] : err
}

onMounted(() => {
  if (!auth.usuario && auth.token) auth.obtenerUsuario().then(sincronizarDesdeAuth)
  cargarSuscripcion()
})
</script>

<template>
  <section class="py-4">
    <header class="mb-8">
      <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Mi cuenta</h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Gestiona tus datos personales y la seguridad de tu cuenta.
      </p>
    </header>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-[260px_1fr]">
      <!-- Resumen lateral -->
      <aside>
        <div class="card p-6 text-center">
          <div class="mx-auto grid h-20 w-20 place-items-center overflow-hidden rounded-full bg-primary-600 text-2xl font-bold text-white">
            <img
              v-if="auth.usuario?.avatar"
              :src="auth.usuario.avatar"
              :alt="auth.usuario.name"
              class="h-full w-full object-cover"
            />
            <span v-else>{{ (auth.usuario?.name ?? '?').charAt(0).toUpperCase() }}</span>
          </div>
          <p class="mt-3 text-base font-semibold text-slate-900 dark:text-white">
            {{ auth.usuario?.name }}
          </p>
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ auth.usuario?.email }}</p>
          <div class="mt-3 flex flex-wrap justify-center gap-1">
            <span class="inline-block rounded-full bg-primary-100 px-2 py-0.5 text-[0.7rem] font-bold uppercase tracking-wide text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
              {{ auth.usuario?.role }}
            </span>
            <span
              v-if="esPremium"
              class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[0.7rem] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
            >
              <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
              Premium
            </span>
          </div>
        </div>

        <nav class="mt-4 space-y-2 text-sm">
          <RouterLink
            to="/mi-cuenta/pedidos"
            class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            Mis pedidos
          </RouterLink>
          <RouterLink
            to="/mi-cuenta/devoluciones"
            class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            Mis devoluciones
          </RouterLink>
          <RouterLink
            to="/mi-cuenta/wishlist"
            class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            Lista de deseos
          </RouterLink>
          <RouterLink
            v-if="auth.esUsuario || auth.esAdmin"
            to="/mis-productos"
            class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            Mis productos en venta
          </RouterLink>
          <RouterLink
            v-if="auth.esEmpresa || auth.esAdmin"
            to="/empresa"
            class="block rounded-md px-3 py-2 font-semibold text-primary-700 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/30"
          >
            Panel de empresa →
          </RouterLink>
          <RouterLink
            v-if="auth.esAdmin"
            to="/admin"
            class="block rounded-md px-3 py-2 font-semibold text-primary-700 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/30"
          >
            Panel administrador →
          </RouterLink>
        </nav>
      </aside>

      <div class="space-y-8">
        <!-- Suscripción Premium -->
        <section
          v-if="esPremium"
          class="card overflow-hidden border-2 border-amber-300 bg-amber-50/50 p-6 dark:border-amber-700 dark:bg-amber-900/20"
        >
          <header class="flex items-start gap-4">
            <div class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-full bg-amber-400 text-amber-900">
              <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
            </div>
            <div class="flex-1">
              <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white">
                NovaCommerce Premium
                <span class="rounded-full bg-amber-400 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide text-amber-900">
                  Activa
                </span>
              </h2>
              <p v-if="suscripcion?.premium_until_legible" class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                Tu suscripción expira el <strong>{{ suscripcion.premium_until_legible }}</strong>.
              </p>
              <p v-if="suscripcion?.dias_restantes > 0" class="text-xs text-slate-500 dark:text-slate-400">
                {{ suscripcion.dias_restantes }} {{ suscripcion.dias_restantes === 1 ? 'día restante' : 'días restantes' }}.
              </p>
              <p v-if="!sigueRenovando" class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                La renovación automática está desactivada. Mantendrás los beneficios hasta la expiración.
              </p>
            </div>
          </header>
          <div class="mt-4 flex flex-wrap gap-2">
            <button
              v-if="sigueRenovando"
              type="button"
              class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30"
              :disabled="cancelandoSuscripcion"
              @click="dialogoCancelarPremium = true"
            >
              Cancelar suscripción
            </button>
            <RouterLink
              to="/premium"
              class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-700"
            >
              Ver detalle
            </RouterLink>
          </div>
        </section>

        <section
          v-else
          class="card overflow-hidden border-2 border-primary-200 bg-gradient-to-br from-primary-50 to-white p-6 dark:border-primary-800 dark:from-primary-900/30 dark:to-slate-900"
        >
          <header class="flex items-start gap-4">
            <div class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-full bg-primary-600 text-white">
              <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
            </div>
            <div class="flex-1">
              <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                Hazte Premium
              </h2>
              <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                Envíos gratis ilimitados, entrega prioritaria 24-48 h y sin pedido mínimo. Solo 50 €/año.
              </p>
            </div>
          </header>
          <div class="mt-4">
            <RouterLink to="/premium" class="btn-primary">
              Descubre Premium
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </RouterLink>
          </div>
        </section>

        <!-- Datos del perfil -->
        <form class="card space-y-5 p-6" novalidate @submit.prevent="actualizarPerfil">
          <header>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Datos del perfil</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Actualiza tu nombre, teléfono y avatar.
            </p>
          </header>

          <div>
            <label for="acc-name" class="label">Nombre</label>
            <input
              id="acc-name"
              v-model="perfil.name"
              type="text"
              class="input"
              autocomplete="name"
            />
            <p v-if="erroresPerfil.name" class="form-error">{{ valor(erroresPerfil.name) }}</p>
          </div>

          <div>
            <label for="acc-phone" class="label">Teléfono</label>
            <input
              id="acc-phone"
              v-model="perfil.phone"
              type="tel"
              class="input"
              placeholder="+34 600 000 000"
              autocomplete="tel"
            />
            <p v-if="erroresPerfil.phone" class="form-error">{{ valor(erroresPerfil.phone) }}</p>
          </div>

          <div>
            <label for="acc-avatar" class="label">URL del avatar <span class="text-slate-400">(opcional)</span></label>
            <input
              id="acc-avatar"
              v-model="perfil.avatar"
              type="url"
              class="input"
              placeholder="https://..."
            />
            <p v-if="erroresPerfil.avatar" class="form-error">{{ valor(erroresPerfil.avatar) }}</p>
          </div>

          <div class="flex justify-end">
            <button type="submit" class="btn-primary" :disabled="guardandoPerfil">
              {{ guardandoPerfil ? 'Guardando...' : 'Guardar cambios' }}
            </button>
          </div>
        </form>

        <!-- Cambio de contraseña -->
        <form class="card space-y-5 p-6" novalidate @submit.prevent="cambiarPassword">
          <header>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Cambiar contraseña</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Al cambiarla cerraremos todas tus sesiones y tendrás que iniciar sesión de nuevo.
            </p>
          </header>

          <div>
            <label for="pwd-current" class="label">Contraseña actual</label>
            <input
              id="pwd-current"
              v-model="passwords.current_password"
              type="password"
              class="input"
              autocomplete="current-password"
            />
            <p v-if="erroresPassword.current_password" class="form-error">
              {{ valor(erroresPassword.current_password) }}
            </p>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="pwd-new" class="label">Nueva contraseña</label>
              <input
                id="pwd-new"
                v-model="passwords.new_password"
                type="password"
                class="input"
                autocomplete="new-password"
              />
              <p v-if="erroresPassword.new_password" class="form-error">
                {{ valor(erroresPassword.new_password) }}
              </p>
            </div>
            <div>
              <label for="pwd-confirm" class="label">Confirmar nueva contraseña</label>
              <input
                id="pwd-confirm"
                v-model="passwords.new_password_confirmation"
                type="password"
                class="input"
                autocomplete="new-password"
              />
              <p v-if="erroresPassword.new_password_confirmation" class="form-error">
                {{ valor(erroresPassword.new_password_confirmation) }}
              </p>
            </div>
          </div>

          <div class="flex justify-end">
            <button type="submit" class="btn-primary" :disabled="guardandoPassword">
              {{ guardandoPassword ? 'Cambiando...' : 'Cambiar contraseña' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <DialogoConfirmacion
      v-model:abierto="dialogoCancelarPremium"
      titulo="¿Cancelar tu suscripción Premium?"
      mensaje="Dejarás de renovar automáticamente, pero conservarás los envíos gratuitos hasta la fecha de expiración."
      texto-confirmar="Cancelar suscripción"
      texto-cancelar="Mantener Premium"
      variante="danger"
      :cargando="cancelandoSuscripcion"
      @confirmar="cancelarPremium"
    />
  </section>
</template>
