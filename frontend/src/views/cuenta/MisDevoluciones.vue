<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/api'
import EstadoVacio from '@/components/EstadoVacio.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import Paginacion from '@/components/Paginacion.vue'
import {
  etiquetaMotivo,
  infoEstadoDevolucion,
} from '@/composables/useEstadosPedido'
import { formatearEur } from '@/composables/useEnvio'

const route = useRoute()
const router = useRouter()

const devoluciones = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 })
const cargando = ref(true)
const error = ref(null)

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

async function cargar(pagina = 1) {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get('/mis-devoluciones', { params: { page: pagina, per_page: 12 } })
    devoluciones.value = data?.data ?? []
    meta.value = data?.meta ?? meta.value
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudieron obtener las devoluciones.'
  } finally {
    cargando.value = false
  }
}

function formatearFecha(fecha) {
  if (!fecha) return ''
  try {
    return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' }).format(new Date(fecha))
  } catch {
    return ''
  }
}

function cambiarPagina(p) {
  router.replace({ query: { ...route.query, page: String(p) } })
  cargar(p)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => cargar(Number(route.query.page) || 1))
</script>

<template>
  <section class="py-4">
    <header class="mb-8 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
          Mis devoluciones
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Consulta el estado de tus solicitudes de devolución.
        </p>
      </div>
      <RouterLink
        to="/mi-cuenta/pedidos"
        class="text-sm font-medium text-primary-700 hover:underline dark:text-primary-400"
      >
        ← Volver a mis pedidos
      </RouterLink>
    </header>

    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando tus devoluciones..." />
    </div>

    <EstadoVacio
      v-else-if="error"
      icono="error"
      titulo="No se pudieron cargar las devoluciones"
      :descripcion="error"
    >
      <button class="btn-primary" @click="cargar(paginaActual)">Reintentar</button>
    </EstadoVacio>

    <EstadoVacio
      v-else-if="devoluciones.length === 0"
      icono="inbox"
      titulo="Aún no tienes devoluciones"
      descripcion="Cuando solicites una devolución aparecerá aquí."
    >
      <RouterLink to="/mi-cuenta/pedidos" class="btn-primary">Ver mis pedidos</RouterLink>
    </EstadoVacio>

    <div v-else class="space-y-3">
      <article
        v-for="d in devoluciones"
        :key="d.id"
        class="card p-5"
      >
        <header class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Pedido
            </p>
            <RouterLink
              v-if="d.order"
              :to="{ name: 'pedido-detalle', params: { number: d.order.order_number } }"
              class="font-mono text-sm font-bold text-slate-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-400"
            >
              {{ d.order.order_number }}
            </RouterLink>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              Aprobada el {{ formatearFecha(d.resolved_at || d.created_at) }}
            </p>
          </div>
          <span
            class="inline-flex rounded-full px-3 py-0.5 text-xs font-bold"
            :class="infoEstadoDevolucion(d.status).clases"
          >
            {{ infoEstadoDevolucion(d.status).etiqueta }}
          </span>
        </header>

        <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Motivo
            </dt>
            <dd class="text-slate-700 dark:text-slate-300">
              {{ d.reason_label || etiquetaMotivo(d.reason) }}
            </dd>
          </div>
          <div v-if="d.order">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Total del pedido
            </dt>
            <dd class="font-bold text-slate-900 dark:text-white">
              {{ formatearEur(d.order.total) }}
            </dd>
          </div>
        </dl>

        <div v-if="d.description" class="mt-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Mi descripción
          </p>
          <p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">
            {{ d.description }}
          </p>
        </div>

        <div
          v-if="d.admin_notes"
          class="mt-4 rounded-md bg-slate-50 p-3 dark:bg-slate-800/60"
        >
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Notas del equipo
          </p>
          <p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">
            {{ d.admin_notes }}
          </p>
          <p v-if="d.resolved_at" class="mt-2 text-[0.7rem] text-slate-400 dark:text-slate-500">
            Resuelta el {{ formatearFecha(d.resolved_at) }}
          </p>
        </div>
      </article>

      <Paginacion
        :pagina-actual="paginaActual"
        :total-paginas="totalPaginas"
        class="pt-4"
        @cambiar="cambiarPagina"
      />
    </div>
  </section>
</template>
