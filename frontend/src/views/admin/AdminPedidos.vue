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

const filtros = ref({ status: '', date_from: '', date_to: '', page: 1 })

const cambiandoEstado = ref({})
const detalleAbierto = ref({})

const estadosDisponibles = Object.entries(ESTADOS_PEDIDO).map(([valor, info]) => ({
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
    if (filtros.value.date_from) params.date_from = filtros.value.date_from
    if (filtros.value.date_to) params.date_to = filtros.value.date_to

    const { data } = await api.get('/admin/orders', { params })
    pedidos.value = data?.data ?? []
    meta.value = data?.meta ?? meta.value
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudieron obtener los pedidos.'
  } finally {
    cargando.value = false
  }
}

function alCambiarFiltro() {
  filtros.value.page = 1
  sincronizarRuta()
  cargar()
}

function limpiarFiltros() {
  filtros.value = { status: '', date_from: '', date_to: '', page: 1 }
  sincronizarRuta()
  cargar()
}

function sincronizarRuta() {
  const query = {}
  if (filtros.value.status) query.status = filtros.value.status
  if (filtros.value.date_from) query.date_from = filtros.value.date_from
  if (filtros.value.date_to) query.date_to = filtros.value.date_to
  if (filtros.value.page > 1) query.page = String(filtros.value.page)
  router.replace({ query })
}

function cambiarPagina(p) {
  filtros.value.page = p
  sincronizarRuta()
  cargar()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function alternarDetalle(id) {
  detalleAbierto.value = { ...detalleAbierto.value, [id]: !detalleAbierto.value[id] }
}

async function cambiarEstado(pedido, nuevoEstado) {
  if (!nuevoEstado || nuevoEstado === pedido.status) return
  cambiandoEstado.value = { ...cambiandoEstado.value, [pedido.id]: true }
  try {
    await api.put(`/admin/orders/${pedido.id}/status`, { status: nuevoEstado })
    pedido.status = nuevoEstado
    toast.exito('Estado del pedido actualizado.')
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo actualizar el estado.')
  } finally {
    cambiandoEstado.value = { ...cambiandoEstado.value, [pedido.id]: false }
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

watch(() => route.query, (nueva) => {
  filtros.value.status = nueva.status ?? ''
  filtros.value.date_from = nueva.date_from ?? ''
  filtros.value.date_to = nueva.date_to ?? ''
  filtros.value.page = Number(nueva.page) || 1
  cargar()
})

onMounted(() => {
  filtros.value.status = route.query.status ?? ''
  filtros.value.date_from = route.query.date_from ?? ''
  filtros.value.date_to = route.query.date_to ?? ''
  filtros.value.page = Number(route.query.page) || 1
  cargar()
})
</script>

<template>
  <PanelLayout
    tipo="admin"
    titulo="Gestión de pedidos"
    descripcion="Listado global de pedidos. Puedes filtrar y cambiar el estado de cualquiera."
  >
    <!-- Filtros -->
    <div class="card mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <label for="filtro-estado" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Estado
        </label>
        <select id="filtro-estado" v-model="filtros.status" class="input" @change="alCambiarFiltro">
          <option value="">Todos</option>
          <option v-for="e in estadosDisponibles" :key="e.valor" :value="e.valor">
            {{ e.etiqueta }}
          </option>
        </select>
      </div>

      <div>
        <label for="filtro-desde" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Desde
        </label>
        <input
          id="filtro-desde"
          v-model="filtros.date_from"
          type="date"
          class="input"
          @change="alCambiarFiltro"
        />
      </div>

      <div>
        <label for="filtro-hasta" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Hasta
        </label>
        <input
          id="filtro-hasta"
          v-model="filtros.date_to"
          type="date"
          class="input"
          @change="alCambiarFiltro"
        />
      </div>

      <div class="flex items-end">
        <button type="button" class="btn-secondary w-full" @click="limpiarFiltros">
          Limpiar filtros
        </button>
      </div>
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
      <button class="btn-primary" @click="cargar">Reintentar</button>
    </EstadoVacio>

    <EstadoVacio
      v-else-if="pedidos.length === 0"
      icono="search"
      titulo="No hay pedidos que coincidan"
      descripcion="Prueba a ajustar los filtros."
    />

    <div v-else class="space-y-4">
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
            <tr>
              <th class="px-4 py-3 w-8"></th>
              <th class="px-4 py-3">Número</th>
              <th class="px-4 py-3">Comprador</th>
              <th class="px-4 py-3">Fecha</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3 text-right">Total</th>
              <th class="px-4 py-3">Cambiar estado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <template v-for="p in pedidos" :key="p.id">
              <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="px-4 py-3">
                  <button
                    type="button"
                    class="grid h-6 w-6 place-items-center rounded text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                    :aria-label="detalleAbierto[p.id] ? 'Ocultar detalle' : 'Ver detalle'"
                    @click="alternarDetalle(p.id)"
                  >
                    <svg
                      class="h-4 w-4 transition-transform"
                      :class="{ 'rotate-90': detalleAbierto[p.id] }"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                  </button>
                </td>
                <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-900 dark:text-white">
                  {{ p.order_number }}
                </td>
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-900 dark:text-white">{{ p.buyer?.name ?? '—' }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">{{ p.buyer?.email }}</div>
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                  {{ formatearFecha(p.created_at) }}
                </td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold"
                    :class="infoEstado(p.status).clases"
                  >
                    {{ infoEstado(p.status).etiqueta }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">
                  {{ formatearEur(p.total) }}
                </td>
                <td class="px-4 py-3">
                  <select
                    :value="p.status"
                    class="input h-8 py-0 text-xs"
                    :disabled="cambiandoEstado[p.id]"
                    :aria-label="`Cambiar estado del pedido ${p.order_number}`"
                    @change="cambiarEstado(p, $event.target.value)"
                  >
                    <option v-for="e in estadosDisponibles" :key="e.valor" :value="e.valor">
                      {{ e.etiqueta }}
                    </option>
                  </select>
                </td>
              </tr>

              <tr v-if="detalleAbierto[p.id]" class="bg-slate-50/50 dark:bg-slate-800/20">
                <td colspan="7" class="px-4 py-4">
                  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                      <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Items del pedido
                      </p>
                      <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white text-sm dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                        <li
                          v-for="item in p.items ?? []"
                          :key="item.id"
                          class="flex items-center justify-between gap-3 px-3 py-2"
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
                    </div>

                    <div class="space-y-2 text-sm">
                      <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Datos de envío
                      </p>
                      <div class="rounded-lg border border-slate-200 bg-white p-3 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        <p>{{ p.shipping_address }}</p>
                        <p>{{ p.shipping_postal_code }} {{ p.shipping_city }}</p>
                        <p>{{ p.shipping_country }}</p>
                      </div>
                      <p class="text-xs text-slate-500 dark:text-slate-400">
                        Método de pago: <strong class="text-slate-700 dark:text-slate-300">{{ p.payment_method ?? '—' }}</strong>
                      </p>
                      <p v-if="p.notes" class="text-xs text-slate-500 dark:text-slate-400">
                        Notas: <span class="text-slate-700 dark:text-slate-300">{{ p.notes }}</span>
                      </p>
                      <div class="border-t border-slate-200 pt-2 dark:border-slate-700">
                        <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
                          <span>Subtotal</span><span>{{ formatearEur(p.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
                          <span>Envío</span><span>{{ formatearEur(p.shipping_cost) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-extrabold text-slate-900 dark:text-white">
                          <span>Total</span><span>{{ formatearEur(p.total) }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <Paginacion
        :pagina-actual="paginaActual"
        :total-paginas="totalPaginas"
        @cambiar="cambiarPagina"
      />
    </div>
  </PanelLayout>
</template>
