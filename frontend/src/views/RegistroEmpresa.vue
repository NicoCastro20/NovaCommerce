<script setup>
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useNotificacionesStore } from '@/stores/notificaciones'
import logoVertical from '@/assets/img/logo-NovaCommerce-Vertical.png'

const route = useRoute()
const router = useRouter()
const auth = useAutenticacionStore()
const toast = useNotificacionesStore()

const formulario = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  nif_cif: '',
  company_name: '',
  acepta: false,
})

const errores = ref({})
const verPassword = ref(false)

const RE_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function validar() {
  const nuevos = {}

  if (!formulario.name.trim()) nuevos.name = 'El nombre del responsable es obligatorio.'
  else if (formulario.name.trim().length < 2) nuevos.name = 'El nombre debe tener al menos 2 caracteres.'

  if (!formulario.email.trim()) nuevos.email = 'El correo electrónico es obligatorio.'
  else if (!RE_EMAIL.test(formulario.email.trim())) nuevos.email = 'Introduce un correo electrónico válido.'

  if (formulario.phone && formulario.phone.length > 20) {
    nuevos.phone = 'El teléfono no puede superar los 20 caracteres.'
  }

  if (!formulario.password) {
    nuevos.password = 'La contraseña es obligatoria.'
  } else if (formulario.password.length < 8) {
    nuevos.password = 'La contraseña debe tener al menos 8 caracteres.'
  } else if (!/[A-Za-z]/.test(formulario.password) || !/\d/.test(formulario.password)) {
    nuevos.password = 'La contraseña debe contener al menos una letra y un número.'
  }

  if (formulario.password !== formulario.password_confirmation) {
    nuevos.password_confirmation = 'Las contraseñas no coinciden.'
  }

  if (!formulario.nif_cif.trim()) nuevos.nif_cif = 'El NIF/CIF de la empresa es obligatorio.'
  else if (formulario.nif_cif.trim().length > 20) nuevos.nif_cif = 'El NIF/CIF no puede superar los 20 caracteres.'

  if (!formulario.company_name.trim()) nuevos.company_name = 'El nombre de la empresa es obligatorio.'

  if (!formulario.acepta) nuevos.acepta = 'Debes aceptar los términos y condiciones.'

  errores.value = nuevos
  return Object.keys(nuevos).length === 0
}

async function registrarse() {
  if (!validar()) return

  const payload = {
    name: formulario.name.trim(),
    email: formulario.email.trim(),
    phone: formulario.phone.trim() || undefined,
    password: formulario.password,
    password_confirmation: formulario.password_confirmation,
    nif_cif: formulario.nif_cif.trim(),
    company_name: formulario.company_name.trim(),
  }

  const ok = await auth.registrarEmpresa(payload)

  if (!ok) {
    toast.error(auth.error || 'No se pudo registrar la empresa.')
    return
  }

  toast.exito('Empresa registrada correctamente.')

  const destino = route.query.redirect && typeof route.query.redirect === 'string'
    ? route.query.redirect
    : '/empresa'
  router.push(destino)
}
</script>

<template>
  <section class="mx-auto max-w-2xl py-8">
    <RouterLink to="/" class="mb-6 flex justify-center" aria-label="Inicio NovaCommerce">
      <img
        :src="logoVertical"
        alt="NovaCommerce"
        class="h-[170px] w-auto dark:brightness-0 dark:invert"
      />
    </RouterLink>
    <div class="card p-8">
      <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Registrar mi empresa</h1>
      <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
        Crea una cuenta de empresa para vender productos nuevos en NovaCommerce.
      </p>

      <form class="mt-6 space-y-5" novalidate @submit.prevent="registrarse">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label for="reg-name" class="label">Nombre del responsable</label>
            <input
              id="reg-name"
              v-model="formulario.name"
              type="text"
              class="input"
              placeholder="Persona de contacto"
              autocomplete="name"
            />
            <p v-if="errores.name" class="form-error">{{ errores.name }}</p>
          </div>

          <div>
            <label for="reg-email" class="label">Correo electrónico</label>
            <input
              id="reg-email"
              v-model="formulario.email"
              type="email"
              class="input"
              placeholder="empresa@dominio.com"
              autocomplete="email"
            />
            <p v-if="errores.email" class="form-error">{{ errores.email }}</p>
          </div>

          <div>
            <label for="reg-phone" class="label">Teléfono <span class="text-slate-400">(opcional)</span></label>
            <input
              id="reg-phone"
              v-model="formulario.phone"
              type="tel"
              class="input"
              placeholder="+34 600 000 000"
              autocomplete="tel"
            />
            <p v-if="errores.phone" class="form-error">{{ errores.phone }}</p>
          </div>

          <div>
            <label for="reg-password" class="label">Contraseña</label>
            <div class="relative">
              <input
                id="reg-password"
                v-model="formulario.password"
                :type="verPassword ? 'text' : 'password'"
                class="input pr-10"
                placeholder="Mínimo 8 caracteres"
                autocomplete="new-password"
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

          <div class="sm:col-span-2">
            <label for="reg-password2" class="label">Confirmar contraseña</label>
            <input
              id="reg-password2"
              v-model="formulario.password_confirmation"
              :type="verPassword ? 'text' : 'password'"
              class="input"
              placeholder="Repite la contraseña"
              autocomplete="new-password"
            />
            <p v-if="errores.password_confirmation" class="form-error">
              {{ errores.password_confirmation }}
            </p>
          </div>
        </div>

        <div class="rounded-lg border border-primary-200 bg-primary-50/50 p-4 dark:border-primary-900/40 dark:bg-primary-900/10">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Datos de la empresa</h3>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="reg-nif" class="label">NIF / CIF</label>
              <input
                id="reg-nif"
                v-model="formulario.nif_cif"
                type="text"
                class="input"
                placeholder="B12345678"
              />
              <p v-if="errores.nif_cif" class="form-error">{{ errores.nif_cif }}</p>
            </div>
            <div>
              <label for="reg-company" class="label">Nombre de la empresa</label>
              <input
                id="reg-company"
                v-model="formulario.company_name"
                type="text"
                class="input"
                placeholder="Mi Empresa S.L."
              />
              <p v-if="errores.company_name" class="form-error">{{ errores.company_name }}</p>
            </div>
          </div>
        </div>

        <label class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
          <input
            v-model="formulario.acepta"
            type="checkbox"
            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
          />
          <span>
            Acepto los
            <a href="#" class="text-primary-700 hover:underline dark:text-primary-400">términos y condiciones</a>
            y la
            <a href="#" class="text-primary-700 hover:underline dark:text-primary-400">política de privacidad</a>.
          </span>
        </label>
        <p v-if="errores.acepta" class="form-error -mt-3">{{ errores.acepta }}</p>

        <button type="submit" class="btn-primary w-full !py-2.5" :disabled="auth.cargando">
          <svg v-if="auth.cargando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
          {{ auth.cargando ? 'Creando cuenta...' : 'Registrar empresa' }}
        </button>
      </form>

      <div class="mt-6 space-y-1 text-center text-sm text-slate-600 dark:text-slate-400">
        <p>
          ¿No eres una empresa?
          <RouterLink
            to="/registro"
            class="font-semibold text-primary-700 hover:underline dark:text-primary-400"
          >
            Crear cuenta de usuario
          </RouterLink>
        </p>
        <p>
          ¿Ya tienes cuenta?
          <RouterLink
            to="/login"
            class="font-semibold text-primary-700 hover:underline dark:text-primary-400"
          >
            Iniciar sesión
          </RouterLink>
        </p>
      </div>
    </div>
  </section>
</template>
