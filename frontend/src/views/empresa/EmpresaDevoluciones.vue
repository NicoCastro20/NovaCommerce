<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api'
import PanelLayout from '@/components/PanelLayout.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import Paginacion from '@/components/Paginacion.vue'
import { useNotificacionesStore } from '@/stores/notificaciones'
import {
  ESTADOS_DEVOLUCION,
  etiquetaMotivo,
  infoEstadoDevolucion,
} from '@/composables/useEstadosPedido'
import { formatearEur } from '@/composables/useEnvio'

const route = useRoute()
const router = useRouter()
const toast = useNotificacionesStore()

const devoluciones = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 })
const cargando = ref(true)
const error = ref(null)

const filtros = ref({ status: 'solicitada', page: 1 })

const notas = reactive({})
const procesando = reactive({})

const estadosDisponibles = Object.entries(ESTADOS_DEVOLUCION).map(([valor, info]) => ({
  valor,
  etiqueta: info.etiqueta,
}))

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const params = { page: filtros.value.page, per_page: 12 }
    if (filtros.value.status) params.status = filtros.value.status

    const { data } = await api.get('/empresa/devoluciones', { params })
    devoluciones.value = data?.data ?? []
    meta.value = data?.meta ?? meta.value
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudieron obtener las devoluciones.'
  } finally {
    cargando.value = false
  }
}

function alCambiarFiltro() {
  filtros.value.page = 1
  sincronizarRuta()
  cargar()
}

function sincronizarRuta() {
  const query = {}
  if (filtros.value.status) query.status = filtros.value.status
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

async function resolver(devolucion, accion) {
  procesando[devolucion.id] = true
  try {
    await api.put(`/empresa/devoluciones/${devolucion.id}`, {
      action: accion,
      admin_notes: notas[devolucion.id] || null,
    })
    toast.exito(accion === 'aprobar' ? 'Devolución aprobada. Stock devuelto.' : 'Devolución rechazada.')
    delete notas[devolucion.id]
    cargar()
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo procesar la devolución.')
  } finally {
    procesando[devolucion.id] = false
  }
}

watch(() => route.query, (nueva) => {
  filtros.value.status = nueva.status ?? 'solicitada'
  filtros.value.page = Number(nueva.page) || 1
  cargar()
})

onMounted(() => {
  filtros.value.status = route.query.status ?? 'solicitada'
  filtros.value.page = Number(route.query.page) || 1
  cargar()
})
</script>

<template>
  <PanelLayout
    tipo="empresa"
    titulo="Devoluciones"
    descripcion="Gestiona las solicitudes de devolución de pedidos que contienen tus productos."
  >
    <!-- Filtros -->
    <div class="card mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-3">
      <div>
        <label for="filtro-estado-dev" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Estado
        </label>
        <select id="filtro-estado-dev" v-model="filtros.status" class="input" @change="alCambiarFiltro">
          <option value="">Todas</option>
          <option v-for="e in estadosDisponibles" :key="e.valor" :value="e.valor">
            {{ e.etiqueta }}
          </option>
        </select>
      </div>
    </div>

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
      titulo="No hay devoluciones que coincidan"
      descripcion="Prueba a ajustar el filtro de estado."
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
              Solicitada el {{ formatearFecha(d.created_at) }}
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

        <div v-if="d.status === 'solicitada'" class="mt-4 space-y-3">
          <div>
            <label :for="`notas-${d.id}`" class="label">
              Notas <span class="text-slate-400">(opcional)</span>
            </label>
            <textarea
              :id="`notas-${d.id}`"
              v-model="notas[d.id]"
              class="input min-h-[80px]"
              maxlength="1000"
              placeholder="Añade información para el cliente..."
            ></textarea>
          </div>
          <div class="flex flex-wrap justify-end gap-2">
            <button
              type="button"
              class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60"
              :disabled="procesando[d.id]"
              @click="resolver(d, 'rechazar')"
            >
              {{ procesando[d.id] ? 'Procesando...' : 'Rechazar' }}
            </button>
            <button
              type="button"
              class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
              :disabled="procesando[d.id]"
              @click="resolver(d, 'aprobar')"
            >
              {{ procesando[d.id] ? 'Procesando...' : 'Aprobar' }}
            </button>
          </div>
        </div>

        <div
          v-else-if="d.admin_notes"
          class="mt-4 rounded-md bg-slate-50 p-3 dark:bg-slate-800/60"
        >
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Notas registradas
          </p>
          <p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">
            {{ d.admin_notes }}
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
  </PanelLayout>
</template>
