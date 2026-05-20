<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api'
import PanelLayout from '@/components/PanelLayout.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import Paginacion from '@/components/Paginacion.vue'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { ESTADOS_PEDIDO, infoEstado } from '@/composables/useEstadosPedido'
import { formatearEur } from '@/composables/useEnvio'

const route = useRoute()
const router = useRouter()
const toast = useNotificacionesStore()

const pedidos = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 })
const cargando = ref(true)
const error = ref(null)

const filtroEstado = ref('')
const cambiandoEstado = ref({}) // { [orderId]: bool }

const estadosDisponibles = Object.entries(ESTADOS_PEDIDO).map(([valor, info]) => ({
  valor,
  etiqueta: info.etiqueta,
}))

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

async function cargar(pagina = 1) {
  cargando.value = true
  error.value = null
  try {
    const params = { page: pagina, per_page: 12 }
    if (filtroEstado.value) params.status = filtroEstado.value
    const { data } = await api.get('/empresa/pedidos', { params })
    pedidos.value = data?.data ?? []
    meta.value = data?.meta ?? meta.value
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudieron obtener los pedidos.'
  } finally {
    cargando.value = false
  }
}

function formatearFecha(fecha) {
  if (!fecha) return ''
  try {
    return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(fecha))
  } catch {
    return ''
  }
}

const subtotalDePedido = (p) =>
  (p.items ?? []).reduce((acc, it) => acc + Number(it.subtotal ?? 0), 0)

async function cambiarEstado(pedido, nuevoEstado) {
  if (!nuevoEstado || nuevoEstado === pedido.status) return
  cambiandoEstado.value = { ...cambiandoEstado.value, [pedido.id]: true }
  try {
    await api.put(`/empresa/pedidos/${pedido.id}/status`, { status: nuevoEstado })
    pedido.status = nuevoEstado
    toast.exito('Estado del pedido actualizado.')
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo actualizar el estado.')
  } finally {
    cambiandoEstado.value = { ...cambiandoEstado.value, [pedido.id]: false }
  }
}

function sincronizarRuta() {
  const query = {}
  if (filtroEstado.value) query.status = filtroEstado.value
  if (paginaActual.value > 1) query.page = String(paginaActual.value)
  router.replace({ query })
}

function alCambiarFiltro() {
  sincronizarRuta()
  cargar(1)
}

function cambiarPagina(p) {
  cargar(p)
  router.replace({ query: { ...route.query, page: String(p) } })
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

watch(() => route.query, () => {
  filtroEstado.value = route.query.status ?? ''
})

onMounted(() => {
  filtroEstado.value = route.query.status ?? ''
  cargar(Number(route.query.page) || 1)
})
</script>

<template>
  <PanelLayout
    tipo="empresa"
    titulo="Pedidos recibidos"
    descripcion="Pedidos que contienen productos tuyos. Puedes actualizar su estado."
  >
    <!-- Filtros -->
    <div class="card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
      <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="filtro-estado">
        Filtrar por estado:
      </label>
      <select
        id="filtro-estado"
        v-model="filtroEstado"
        class="input sm:w-60"
        @change="alCambiarFiltro"
      >
        <option value="">Todos los estados</option>
        <option v-for="e in estadosDisponibles" :key="e.valor" :value="e.valor">
          {{ e.etiqueta }}
        </option>
      </select>
    </div>

    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando pedidos..." />
    </div>

    <EstadoVacio
      v-else-if="error"
      icono="error"
      titulo="No se pudieron cargar los pedidos"
      :descripcion="error"
    >
      <button class="btn-primary" @click="cargar(paginaActual)">Reintentar</button>
    </EstadoVacio>

    <EstadoVacio
      v-else-if="pedidos.length === 0"
      icono="inbox"
      titulo="No hay pedidos"
      descripcion="Aún no has recibido pedidos con tus productos."
    />

    <div v-else class="space-y-4">
      <article
        v-for="pedido in pedidos"
        :key="pedido.id"
        class="card overflow-hidden"
      >
        <header class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/40 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="font-mono text-sm font-bold text-slate-900 dark:text-white">
              {{ pedido.order_number }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              {{ formatearFecha(pedido.created_at) }} · Comprador: {{ pedido.buyer?.name ?? '—' }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold"
              :class="infoEstado(pedido.status).clases"
            >
              {{ infoEstado(pedido.status).etiqueta }}
            </span>
            <select
              :value="pedido.status"
              class="input h-9 py-0 text-sm"
              :disabled="cambiandoEstado[pedido.id]"
              :aria-label="`Cambiar estado del pedido ${pedido.order_number}`"
              @change="cambiarEstado(pedido, $event.target.value)"
            >
              <option v-for="e in estadosDisponibles" :key="e.valor" :value="e.valor">
                {{ e.etiqueta }}
              </option>
            </select>
          </div>
        </header>

        <div class="px-5 py-4">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Tus productos en este pedido
          </p>
          <ul class="divide-y divide-slate-200 dark:divide-slate-700">
            <li
              v-for="item in pedido.items ?? []"
              :key="item.id"
              class="flex items-center justify-between gap-3 py-2 text-sm"
            >
              <div class="min-w-0">
                <p class="truncate font-medium text-slate-900 dark:text-white">
                  {{ item.product_name }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  {{ item.quantity }} × {{ formatearEur(item.product_price) }}
                </p>
              </div>
              <span class="font-semibold text-slate-900 dark:text-white">
                {{ formatearEur(item.subtotal) }}
              </span>
            </li>
          </ul>

          <div class="mt-3 flex items-center justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
            <span class="text-xs text-slate-500 dark:text-slate-400">Importe de tus items:</span>
            <span class="text-base font-extrabold text-slate-900 dark:text-white">
              {{ formatearEur(subtotalDePedido(pedido)) }}
            </span>
          </div>
        </div>
      </article>

      <Paginacion
        :pagina-actual="paginaActual"
        :total-paginas="totalPaginas"
        class="pt-2"
        @cambiar="cambiarPagina"
      />
    </div>
  </PanelLayout>
</template>
