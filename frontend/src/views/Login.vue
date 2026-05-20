<script setup>
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useCarritoStore } from '@/stores/carrito'
import { useNotificacionesStore } from '@/stores/notificaciones'
import logoVertical from '@/assets/img/logo-NovaCommerce-Vertical.png'

const route = useRoute()
const router = useRouter()
const auth = useAutenticacionStore()
const carrito = useCarritoStore()
const toast = useNotificacionesStore()

const formulario = reactive({
  email: '',
  password: '',
})

const errores = ref({})
const verPassword = ref(false)

const RE_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function validar() {
  const nuevos = {}
  if (!formulario.email.trim()) nuevos.email = 'El correo electrónico es obligatorio.'
  else if (!RE_EMAIL.test(formulario.email.trim())) nuevos.email = 'Introduce un correo electrónico válido.'
  if (!formulario.password) nuevos.password = 'La contraseña es obligatoria.'
  errores.value = nuevos
  return Object.keys(nuevos).length === 0
}

async function iniciarSesion() {
  if (!validar()) return

  const ok = await auth.iniciarSesion({
    email: formulario.email.trim(),
    password: formulario.password,
  })

  if (!ok) {
    toast.error(auth.error || 'No se pudo iniciar sesión.')
    return
  }

  toast.exito(`Bienvenido, ${auth.usuario?.name ?? ''}`.trim() + '.')

  // Cargar carrito tras login (el backend guarda el carrito por usuario).
  carrito.obtenerCarrito()

  const destino = route.query.redirect && typeof route.query.redirect === 'string'
    ? route.query.redirect
    : '/'
  router.push(destino)
}
</script>

<template>
  <section class="mx-auto max-w-md py-8">
    <RouterLink to="/" class="mb-6 flex justify-center" aria-label="Inicio NovaCommerce">
      <img
        :src="logoVertical"
        alt="NovaCommerce"
        class="h-[170px] w-auto dark:brightness-0 dark:invert"
      />
    </RouterLink>
    <div class="card p-8">
      <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Iniciar sesión</h1>
      <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
        Accede con tu cuenta de NovaCommerce.
      </p>

      <form class="mt-6 space-y-4" novalidate @submit.prevent="iniciarSesion">
        <div>
          <label for="login-email" class="label">Correo electrónico</label>
          <input
            id="login-email"
            v-model="formulario.email"
            type="email"
            class="input"
            placeholder="Tu correo electrónico"
            autocomplete="email"
            required
          />
          <p v-if="errores.email" class="form-error">{{ errores.email }}</p>
        </div>

        <div>
          <label for="login-password" class="label">Contraseña</label>
          <div class="relative">
            <input
              id="login-password"
              v-model="formulario.password"
              :type="verPassword ? 'text' : 'password'"
              class="input pr-10"
              placeholder="Tu contraseña"
              autocomplete="current-password"
              required
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              :aria-label="verPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
              @click="verPassword = !verPassword"
            >
              <svg v-if="!verPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3" stroke-width="2" stroke="currentColor" fill="none"/></svg>
              <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M9.88 5.09A10.94 10.94 0 0112 5c6.5 0 10 7 10 7a17.81 17.81 0 01-3.17 4.19M6.61 6.61A17.69 17.69 0 002 12s3.5 7 10 7c1.39 0 2.7-.27 3.88-.74"/></svg>
            </button>
          </div>
          <p v-if="errores.password" class="form-error">{{ errores.password }}</p>
        </div>

        <button type="submit" class="btn-primary w-full !py-2.5" :disabled="auth.cargando">
          <svg v-if="auth.cargando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
          {{ auth.cargando ? 'Accediendo...' : 'Iniciar sesión' }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
        ¿No tienes cuenta?
        <RouterLink
          :to="{ path: '/registro', query: route.query.redirect ? { redirect: route.query.redirect } : {} }"
          class="font-semibold text-primary-700 hover:underline dark:text-primary-400"
        >
          Crear cuenta
        </RouterLink>
      </p>
    </div>
  </section>
</template>
