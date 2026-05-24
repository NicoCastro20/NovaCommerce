<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api'
import PanelLayout from '@/components/PanelLayout.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
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

const filtros = ref({ page: 1 })

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const params = { page: filtros.value.page, per_page: 12 }
    const { data } = await api.get('/empresa/devoluciones', { params })
    devoluciones.value = data?.data ?? []
    meta.value = data?.meta ?? meta.value
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudieron obtener las devoluciones.'
  } finally {
    cargando.value = false
  }
}

function sincronizarRuta() {
  const query = {}
  if (filtros.value.page > 1) query.page = String(filtros.value.page)
  router.replace({ query })
}

function cambiarPagina(p) {
  filtros.value.page = p
  sincronizarRuta()
  cargar()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function formatearFecha(fecha) {
  if (!fecha) return ''
  try {
    return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(fecha))
  } catch {
    return ''
  }
}

watch(() => route.query, (nueva) => {
  filtros.value.page = Number(nueva.page) || 1
  cargar()
})

onMounted(() => {
  filtros.value.page = Number(route.query.page) || 1
  cargar()
})
</script>

<template>
  <PanelLayout
    tipo="empresa"
    titulo="Devoluciones"
    descripcion="Histórico informativo de devoluciones aprobadas automáticamente sobre pedidos que contienen tus productos."
  >
    <p class="card mb-4 p-4 text-sm text-slate-600 dark:text-slate-300">
      Las devoluciones se aprueban de forma automática dentro del plazo legal de 14 días desde la entrega.
      Cuando se aprueba una, el pedido pasa a estado <span class="font-semibold">devuelto</span> y el stock
      se restaura. Esta pantalla es solo informativa.
    </p>

    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando devoluciones..." />
    </div>

    <EstadoVacio
      v-else-if="error"
      icono="error"
      titulo="No se pudieron cargar las devoluciones"
      :descripcion="error"
    >
      <button class="btn-primary" @click="cargar">Reintentar</button>
    </EstadoVacio>

    <EstadoVacio
      v-else-if="devoluciones.length === 0"
      icono="inbox"
      titulo="Aún no hay devoluciones"
      descripcion="Cuando un cliente devuelva un pedido con tus productos, aparecerá aquí."
    />

    <div v-else class="space-y-4">
      <article
        v-for="d in devoluciones"
        :key="d.id"
        class="card p-5"
      >
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-700">
          <div>
            <p class="font-mono text-sm font-bold text-slate-900 dark:text-white">
              {{ d.order?.order_number }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              Cliente: <span class="font-medium text-slate-700 dark:text-slate-300">{{ d.customer?.name }}</span>
              · {{ d.customer?.email }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400">
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

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Motivo
            </p>
            <p class="text-sm text-slate-700 dark:text-slate-300">
              {{ d.reason_label || etiquetaMotivo(d.reason) }}
            </p>
            <p
              v-if="d.description"
              class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-400"
            >
              {{ d.description }}
            </p>
          </div>

          <div v-if="d.order?.items?.length">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Productos del pedido (los tuyos)
            </p>
            <ul class="mt-1 divide-y divide-slate-200 rounded-md border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
              <li
                v-for="item in d.order.items"
                :key="item.id"
                class="flex items-center justify-between gap-2 px-3 py-2 text-sm"
              >
                <span class="truncate text-slate-700 dark:text-slate-300">{{ item.product_name }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                  {{ item.quantity }} × {{ formatearEur(item.product_price) }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </article>

      <Paginacion
        :pagina-actual="paginaActual"
        :total-paginas="totalPaginas"
        class="pt-4"
        @cambiar="cambiarPagina"
      />
    </div>
  </PanelLayout>
</template>
