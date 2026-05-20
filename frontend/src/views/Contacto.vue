<script setup>
import { reactive, ref } from 'vue'
import { useNotificacionesStore } from '@/stores/notificaciones'

const toast = useNotificacionesStore()

const formulario = reactive({
  nombre: '',
  email: '',
  mensaje: '',
})

const errores = ref({})
const enviando = ref(false)

const RE_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function validar() {
  const nuevos = {}
  if (!formulario.nombre.trim()) nuevos.nombre = 'El nombre es obligatorio.'
  if (!formulario.email.trim()) nuevos.email = 'El correo electrónico es obligatorio.'
  else if (!RE_EMAIL.test(formulario.email.trim())) nuevos.email = 'Introduce un correo electrónico válido.'
  if (!formulario.mensaje.trim()) nuevos.mensaje = 'El mensaje es obligatorio.'
  else if (formulario.mensaje.trim().length < 10) nuevos.mensaje = 'El mensaje debe tener al menos 10 caracteres.'

  errores.value = nuevos
  return Object.keys(nuevos).length === 0
}

async function enviar() {
  if (!validar()) return
  enviando.value = true
  // Simulación de envío (no hay backend para contacto todavía).
  await new Promise((resolve) => setTimeout(resolve, 700))
  enviando.value = false

  toast.exito('Mensaje enviado. Te responderemos pronto.')
  formulario.nombre = ''
  formulario.email = ''
  formulario.mensaje = ''
  errores.value = {}
}

const mapaSrc =
  'https://www.openstreetmap.org/export/embed.html?bbox=-3.7095%2C40.4135%2C-3.6905%2C40.4225&layer=mapnik&marker=40.4180%2C-3.7000'
</script>

<template>
  <section class="py-4">
    <header class="mb-10 text-center">
      <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
        Contacto
      </h1>
      <p class="mx-auto mt-2 max-w-xl text-sm text-slate-600 dark:text-slate-400">
        ¿Tienes una duda o sugerencia? Cuéntanoslo y te responderemos lo antes posible.
      </p>
    </header>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-[1fr_1.2fr]">
      <!-- Información -->
      <aside class="space-y-6">
        <div class="card p-6">
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Información de contacto</h2>
          <ul class="mt-4 space-y-4 text-sm text-slate-600 dark:text-slate-300">
            <li class="flex items-start gap-3">
              <span class="mt-0.5 grid h-9 w-9 place-items-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </span>
              <div>
                <p class="font-medium text-slate-900 dark:text-white">Dirección</p>
                <p>Calle de la Innovación, 123 · 28013 Madrid</p>
              </div>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-0.5 grid h-9 w-9 place-items-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              </span>
              <div>
                <p class="font-medium text-slate-900 dark:text-white">Correo electrónico</p>
                <p>hola@novacommerce.es</p>
              </div>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-0.5 grid h-9 w-9 place-items-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h2l3 7-1.5 3a11.5 11.5 0 006 6L15 19l7 3v-4a2 2 0 00-1-1.7L17 14"/></svg>
              </span>
              <div>
                <p class="font-medium text-slate-900 dark:text-white">Teléfono</p>
                <p>+34 910 000 000</p>
              </div>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-0.5 grid h-9 w-9 place-items-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </span>
              <div>
                <p class="font-medium text-slate-900 dark:text-white">Horario</p>
                <p>Lunes a viernes · 9:00 - 18:00</p>
              </div>
            </li>
          </ul>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
          <iframe
            :src="mapaSrc"
            title="Mapa con la ubicación de NovaCommerce"
            class="h-72 w-full"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
          ></iframe>
        </div>
      </aside>

      <!-- Formulario -->
      <form class="card space-y-5 p-6" novalidate @submit.prevent="enviar">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Envíanos un mensaje</h2>

        <div>
          <label for="contacto-nombre" class="label">Nombre</label>
          <input
            id="contacto-nombre"
            v-model="formulario.nombre"
            type="text"
            class="input"
            placeholder="Tu nombre"
            autocomplete="name"
          />
          <p v-if="errores.nombre" class="form-error">{{ errores.nombre }}</p>
        </div>

        <div>
          <label for="contacto-email" class="label">Correo electrónico</label>
          <input
            id="contacto-email"
            v-model="formulario.email"
            type="email"
            class="input"
            placeholder="Tu correo electrónico"
            autocomplete="email"
          />
          <p v-if="errores.email" class="form-error">{{ errores.email }}</p>
        </div>

        <div>
          <label for="contacto-mensaje" class="label">Mensaje</label>
          <textarea
            id="contacto-mensaje"
            v-model="formulario.mensaje"
            rows="6"
            class="input"
            placeholder="Escribe aquí tu consulta..."
          ></textarea>
          <p v-if="errores.mensaje" class="form-error">{{ errores.mensaje }}</p>
        </div>

        <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="enviando">
          {{ enviando ? 'Enviando...' : 'Enviar mensaje' }}
        </button>
      </form>
    </div>
  </section>
</template>
