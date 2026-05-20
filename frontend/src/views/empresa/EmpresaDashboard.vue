<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/api'
import PanelLayout from '@/components/PanelLayout.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import { formatearEur } from '@/composables/useEnvio'
import { infoEstado } from '@/composables/useEstadosPedido'

const cargando = ref(true)
const error = ref(null)

const totalProductos = ref(0)
const pedidosPendientes = ref(0)
const ingresosEstimados = ref(0)
const ultimosPedidos = ref([])

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const productosResp = await api.get('/empresa/productos', { params: { per_page: 1 } })
    totalProductos.value = productosResp.data?.meta?.total ?? 0

    const pedidosResp = await api.get('/empresa/pedidos', { params: { per_page: 5 } })
    ultimosPedidos.value = pedidosResp.data?.data ?? []

    const todosPedidos = await api.get('/empresa/pedidos', { params: { per_page: 48 } })
    const lista = todosPedidos.data?.data ?? []

    pedidosPendientes.value = lista.filter((p) => p.status === 'pendiente').length

    ingresosEstimados.value = lista
      .filter((p) => p.status !== 'cancelado')
      .reduce(
        (acc, p) => acc + (p.items ?? []).reduce((s, it) => s + Number(it.subtotal ?? 0), 0),
        0,
      )
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudo cargar el resumen.'
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

const subtotalDePedido = (p) =>
  (p.items ?? []).reduce((acc, it) => acc + Number(it.subtotal ?? 0), 0)

onMounted(cargar)
</script>

<template>
  <PanelLayout
    tipo="empresa"
    titulo="Resumen de tu empresa"
    descripcion="Métricas y últimos pedidos de tus productos."
  >
    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando estadísticas..." />
    </div>

    <EstadoVacio
      v-else-if="error"
      icono="error"
      titulo="No se pudo cargar el resumen"
      :descripcion="error"
    >
      <button class="btn-primary" @click="cargar">Reintentar</button>
    </EstadoVacio>

    <template v-else>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Productos publicados
          </p>
          <p class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">{{ totalProductos }}</p>
          <RouterLink
            to="/empresa/productos"
            class="mt-1 inline-block text-xs font-medium text-primary-700 hover:underline dark:text-primary-400"
          >
            Ver mis productos →
          </RouterLink>
        </div>

        <div class="card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Pedidos pendientes
          </p>
          <p class="mt-2 text-3xl font-extrabold text-amber-600 dark:text-amber-400">{{ pedidosPendientes }}</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Pedidos con tus productos en estado "Pendiente".
          </p>
        </div>

        <div class="card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Ingresos estimados
          </p>
          <p class="mt-2 text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
            {{ formatearEur(ingresosEstimados) }}
          </p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Suma de tus items en pedidos no cancelados.
          </p>
        </div>
      </div>

      <section class="mt-8">
        <header class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Últimos pedidos</h2>
          <RouterLink
            to="/empresa/pedidos"
            class="text-sm font-medium text-primary-700 hover:underline dark:text-primary-400"
          >
            Ver todos →
          </RouterLink>
        </header>

        <EstadoVacio
          v-if="ultimosPedidos.length === 0"
          icono="inbox"
          titulo="Aún no tienes pedidos"
          descripcion="Cuando alguien compre tus productos aparecerán aquí."
        />

        <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
              <tr>
                <th class="px-4 py-3">Pedido</th>
                <th class="px-4 py-3">Fecha</th>
                <th class="px-4 py-3">Comprador</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3 text-right">Tu importe</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
              <tr v-for="p in ultimosPedidos" :key="p.id">
                <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-900 dark:text-white">
                  {{ p.order_number }}
                </td>
                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                  {{ formatearFecha(p.created_at) }}
                </td>
                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                  {{ p.buyer?.name ?? '—' }}
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
                  {{ formatearEur(subtotalDePedido(p)) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </PanelLayout>
</template>
