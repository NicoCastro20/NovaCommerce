<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '@/api'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { PRECIO_PREMIUM, formatearEur } from '@/composables/useEnvio'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import Revelar from '@/components/Revelar.vue'

const auth = useAutenticacionStore()
const toast = useNotificacionesStore()
const router = useRouter()

const suscripcion = ref(null)
const cargando = ref(false)
const activando = ref(false)
const cancelando = ref(false)
const dialogoCancelar = ref(false)

const esPremium = computed(() => Boolean(auth.usuario?.premium_active || suscripcion.value?.activa))
const sigueRenovando = computed(() => Boolean(auth.usuario?.is_premium ?? suscripcion.value?.is_premium))

const beneficios = [
  {
    titulo: 'Envíos gratuitos ilimitados',
    descripcion: 'Sin importe mínimo. Todos tus pedidos llegan sin gastos de envío.',
    icono: 'truck',
  },
  {
    titulo: 'Entrega prioritaria en 24-48 h',
    descripcion: 'Tus pedidos pasan a la cola rápida y salen el mismo día.',
    icono: 'rayo',
  },
  {
    titulo: 'Sin pedido mínimo',
    descripcion: 'Da igual lo que compres: nunca pagas envío como suscriptor.',
    icono: 'carrito',
  },
  {
    titulo: 'Cancela cuando quieras',
    descripcion: 'Sin permanencia. Cancelas y conservas los beneficios hasta el vencimiento.',
    icono: 'escudo',
  },
]

async function cargarEstado() {
  if (!auth.estaAutenticado) return
  cargando.value = true
  try {
    const { data } = await api.get('/suscripcion')
    suscripcion.value = data?.data?.suscripcion ?? null
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo obtener tu suscripción.')
  } finally {
    cargando.value = false
  }
}

async function suscribir() {
  if (!auth.estaAutenticado) {
    router.push({ path: '/login', query: { redirect: '/premium' } })
    return
  }
  activando.value = true
  try {
    const { data } = await api.post('/suscripcion/activar')
    suscripcion.value = data?.data?.suscripcion ?? null
    // Sincronizamos el usuario para que la navbar refleje el badge Premium.
    auth.usuario = {
      ...auth.usuario,
      is_premium: true,
      premium_active: true,
      premium_since: suscripcion.value?.premium_since,
      premium_until: suscripcion.value?.premium_until,
    }
    toast.exito(data?.message ?? '¡Bienvenido a NovaCommerce Premium!')
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo activar la suscripción.')
  } finally {
    activando.value = false
  }
}

async function cancelar() {
  cancelando.value = true
  try {
    const { data } = await api.post('/suscripcion/cancelar')
    suscripcion.value = data?.data?.suscripcion ?? suscripcion.value
    auth.usuario = {
      ...auth.usuario,
      is_premium: false,
      premium_active: suscripcion.value?.activa ?? auth.usuario?.premium_active,
    }
    toast.exito(data?.message ?? 'Suscripción cancelada.')
    dialogoCancelar.value = false
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo cancelar la suscripción.')
  } finally {
    cancelando.value = false
  }
}

onMounted(cargarEstado)
</script>

<template>
  <section class="space-y-16 py-4">
    <!-- Hero -->
    <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 px-6 py-16 text-white shadow-lg sm:px-12 sm:py-20">
      <div class="absolute inset-0 opacity-20" aria-hidden="true">
        <div class="absolute -left-20 -top-20 h-72 w-72 rounded-full bg-amber-200 blur-3xl"></div>
        <div class="absolute -bottom-24 -right-10 h-80 w-80 rounded-full bg-primary-300 blur-3xl"></div>
      </div>

      <Revelar class="relative mx-auto max-w-3xl text-center">
        <span class="inline-flex items-center gap-2 rounded-full bg-amber-400/90 px-3 py-1 text-xs font-bold uppercase tracking-wide text-amber-900">
          <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
          Suscripción anual
        </span>
        <h1 class="mt-3 text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
          NovaCommerce Premium
        </h1>
        <p class="mx-auto mt-4 max-w-2xl text-base text-primary-100 sm:text-lg">
          Envíos gratis y prioritarios por solo {{ formatearEur(PRECIO_PREMIUM) }}/año.
        </p>

        <!-- CTA principal -->
        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <template v-if="esPremium">
            <span class="inline-flex items-center gap-2 rounded-lg bg-white/15 px-5 py-3 text-base font-semibold">
              <svg class="h-5 w-5 text-amber-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
              Ya eres Premium
            </span>
          </template>
          <template v-else>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-base font-bold text-primary-700 shadow-md transition-transform hover:scale-105 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="activando"
              @click="suscribir"
            >
              <svg v-if="activando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              Suscríbete por {{ formatearEur(PRECIO_PREMIUM) }}/año
            </button>
          </template>
        </div>
        <p v-if="!auth.estaAutenticado" class="mt-3 text-xs text-primary-100/80">
          Inicia sesión o regístrate para activar tu suscripción.
        </p>
      </Revelar>
    </header>

    <!-- Estado actual del suscriptor -->
    <section v-if="esPremium" class="mx-auto max-w-3xl">
      <div class="card border-2 border-amber-300 bg-amber-50/50 p-6 dark:border-amber-700 dark:bg-amber-900/20">
        <div class="flex items-start gap-4">
          <div class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-full bg-amber-400 text-amber-900">
            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
          </div>
          <div class="flex-1">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">
              Eres miembro Premium
            </h2>
            <p v-if="suscripcion?.premium_until_legible" class="mt-1 text-sm text-slate-700 dark:text-slate-300">
              Tu suscripción expira el <strong>{{ suscripcion.premium_until_legible }}</strong>
              <span v-if="suscripcion.dias_restantes > 0">
                ({{ suscripcion.dias_restantes }} {{ suscripcion.dias_restantes === 1 ? 'día' : 'días' }} restantes).
              </span>
            </p>
            <p v-if="!sigueRenovando" class="mt-2 text-sm text-amber-700 dark:text-amber-300">
              La renovación automática está desactivada. Disfrutarás de los beneficios hasta la fecha de expiración.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
              <button
                v-if="sigueRenovando"
                type="button"
                class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30"
                @click="dialogoCancelar = true"
              >
                Cancelar suscripción
              </button>
              <RouterLink
                to="/catalogo"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-700"
              >
                Ir al catálogo
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Beneficios -->
    <section>
      <Revelar class="mb-10 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
          Lo que incluye tu suscripción
        </h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
          Una cuota anual única. Sin sorpresas, sin permanencia.
        </p>
      </Revelar>

      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <Revelar v-for="(b, i) in beneficios" :key="b.titulo" :delay="i * 100">
          <div class="card h-full p-6 text-center">
            <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
              <svg v-if="b.icono === 'truck'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h13v8H3V7zm13 3h4l1 3v2h-5v-5zM5 19a2 2 0 100-4 2 2 0 000 4zm12 0a2 2 0 100-4 2 2 0 000 4z"/></svg>
              <svg v-else-if="b.icono === 'rayo'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/></svg>
              <svg v-else-if="b.icono === 'carrito'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293A1 1 0 005.414 17H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ b.titulo }}</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ b.descripcion }}</p>
          </div>
        </Revelar>
      </div>
    </section>

    <!-- Comparativa -->
    <section>
      <Revelar class="mb-10 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
          Sin Premium vs. con Premium
        </h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
          Diferencias prácticas en cada compra que hagas.
        </p>
      </Revelar>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <Revelar>
          <div class="card flex h-full flex-col p-6">
            <header class="border-b border-slate-200 pb-4 dark:border-slate-700">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Cuenta estándar
              </p>
              <h3 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Sin Premium</h3>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Gratis</p>
            </header>
            <ul class="mt-4 flex-1 space-y-3 text-sm text-slate-700 dark:text-slate-300">
              <li class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Envío: <strong>5 € por pedido</strong>
              </li>
              <li class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Gratis a partir de 50 €
              </li>
              <li class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Entrega en 3-5 días
              </li>
            </ul>
          </div>
        </Revelar>

        <Revelar :delay="120">
          <div class="card relative flex h-full flex-col overflow-hidden border-2 border-amber-300 p-6 dark:border-amber-700">
            <span class="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-amber-400 px-2 py-0.5 text-[0.65rem] font-bold uppercase text-amber-900">
              <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
              Recomendado
            </span>
            <header class="border-b border-amber-200 pb-4 dark:border-amber-800">
              <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                NovaCommerce Premium
              </p>
              <h3 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Con Premium</h3>
              <p class="mt-1 text-sm font-bold text-amber-700 dark:text-amber-300">
                {{ formatearEur(PRECIO_PREMIUM) }}/año
              </p>
            </header>
            <ul class="mt-4 flex-1 space-y-3 text-sm text-slate-700 dark:text-slate-300">
              <li class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Envío <strong>siempre gratis</strong>
              </li>
              <li class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Sin pedido mínimo
              </li>
              <li class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Entrega <strong>prioritaria 24-48 h</strong>
              </li>
            </ul>
            <div v-if="!esPremium" class="mt-6">
              <button
                type="button"
                class="btn-primary w-full justify-center !py-2.5"
                :disabled="activando"
                @click="suscribir"
              >
                <svg v-if="activando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                Suscríbete por {{ formatearEur(PRECIO_PREMIUM) }}/año
              </button>
            </div>
          </div>
        </Revelar>
      </div>
    </section>

    <DialogoConfirmacion
      v-model:abierto="dialogoCancelar"
      titulo="¿Cancelar tu suscripción Premium?"
      mensaje="Dejarás de renovar automáticamente, pero seguirás disfrutando de envíos gratuitos hasta la fecha de expiración."
      texto-confirmar="Cancelar suscripción"
      texto-cancelar="Mantener Premium"
      variante="danger"
      :cargando="cancelando"
      @confirmar="cancelar"
    />
  </section>
</template>
