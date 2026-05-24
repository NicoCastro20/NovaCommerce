<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/api'
import PanelLayout from '@/components/PanelLayout.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import { formatearEur } from '@/composables/useEnvio'
import { infoEstado } from '@/composables/useEstadosPedido'

const cargando = ref(true)
const error = ref(null)
const datos = ref(null)

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get('/admin/dashboard', { timeout: 15000 })
    const payload = data?.data ?? null
    if (!payload) {
      throw new Error('La respuesta del servidor no contiene datos.')
    }
    datos.value = payload
  } catch (err) {
    datos.value = null
    if (err?.code === 'ECONNABORTED') {
      error.value = 'La carga ha tardado demasiado. Comprueba tu conexión e inténtalo de nuevo.'
    } else {
      error.value = err?.response?.data?.message ?? err?.message ?? 'No se pudo cargar el panel.'
    }
  } finally {
    cargando.value = false
  }
}

function formatearFecha(fecha, formato = 'medium') {
  if (!fecha) return ''
  try {
    return new Intl.DateTimeFormat('es-ES', { dateStyle: formato }).format(new Date(fecha))
  } catch {
    return ''
  }
}

function formatearFechaCorta(fecha) {
  if (!fecha) return ''
  try {
    const d = typeof fecha === 'string' ? new Date(fecha) : fecha
    return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'short' }).format(d)
  } catch {
    return ''
  }
}

// ── Datos derivados de la gráfica de ventas (SVG) ───────────────────────────
const series = computed(() => datos.value?.sales_last_30_days ?? [])

const totalVentasGrafica = computed(() =>
  series.value.reduce((acc, p) => acc + Number(p.total ?? 0), 0),
)

const maximoVentas = computed(() => {
  const valores = series.value.map((p) => Number(p.total ?? 0))
  const max = Math.max(0, ...valores)
  return max === 0 ? 1 : max
})

// Coordenadas de la gráfica (SVG en viewBox 600x180 con padding).
const ANCHO_SVG = 600
const ALTO_SVG = 180
const PADDING_X = 24
const PADDING_Y = 16

const graficaPuntos = computed(() => {
  const puntos = series.value
  if (puntos.length === 0) return []
  const n = puntos.length
  const xPaso = (ANCHO_SVG - PADDING_X * 2) / Math.max(1, n - 1)
  const altoUtil = ALTO_SVG - PADDING_Y * 2
  return puntos.map((p, i) => {
    const x = PADDING_X + xPaso * i
    const y = PADDING_Y + altoUtil - (Number(p.total ?? 0) / maximoVentas.value) * altoUtil
    return { x, y, total: Number(p.total ?? 0), date: p.date, count: Number(p.count ?? 0) }
  })
})

const graficaPath = computed(() => {
  if (graficaPuntos.value.length === 0) return ''
  return graficaPuntos.value
    .map((pt, i) => `${i === 0 ? 'M' : 'L'}${pt.x.toFixed(1)},${pt.y.toFixed(1)}`)
    .join(' ')
})

const graficaArea = computed(() => {
  if (graficaPuntos.value.length === 0) return ''
  const baseY = ALTO_SVG - PADDING_Y
  const inicio = `M${graficaPuntos.value[0].x.toFixed(1)},${baseY}`
  const linea = graficaPuntos.value
    .map((pt) => `L${pt.x.toFixed(1)},${pt.y.toFixed(1)}`)
    .join(' ')
  const fin = `L${graficaPuntos.value[graficaPuntos.value.length - 1].x.toFixed(1)},${baseY} Z`
  return `${inicio} ${linea} ${fin}`
})

onMounted(cargar)
</script>

<template>
  <PanelLayout
    tipo="admin"
    titulo="Resumen de NovaCommerce"
    descripcion="Métricas globales, ventas recientes y actividad."
  >
    <template #acciones>
      <button class="btn-secondary" :disabled="cargando" @click="cargar">
        Actualizar
      </button>
    </template>

    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando estadísticas..." />
    </div>

    <EstadoVacio
      v-else-if="error"
      icono="error"
      titulo="No se pudo cargar el panel"
      :descripcion="error"
    >
      <button class="btn-primary" @click="cargar">Reintentar</button>
    </EstadoVacio>

    <template v-else-if="datos">
      <!-- Cards de métricas -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Usuarios totales
          </p>
          <p class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">
            {{ datos.total_users }}
          </p>
          <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
            +{{ datos.new_users_last_30_days }} en los últimos 30 días
          </p>
        </div>

        <div class="card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Productos
          </p>
          <p class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">
            {{ datos.total_products }}
          </p>
          <RouterLink
            to="/admin/productos"
            class="mt-1 inline-block text-xs font-medium text-primary-700 hover:underline dark:text-primary-400"
          >
            Gestionar productos →
          </RouterLink>
        </div>

        <div class="card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Pedidos
          </p>
          <p class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">
            {{ datos.total_orders }}
          </p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            <span v-for="(n, estado) in datos.orders_by_status" :key="estado">
              <span class="mr-2">{{ infoEstado(estado).etiqueta }}: <strong>{{ n }}</strong></span>
            </span>
          </p>
        </div>

        <div class="card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Ingresos totales
          </p>
          <p class="mt-2 text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
            {{ formatearEur(datos.total_revenue) }}
          </p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Excluye pedidos cancelados y devueltos.
          </p>
        </div>

        <div class="card p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Devoluciones aprobadas
          </p>
          <p class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">
            {{ datos.total_returns ?? 0 }}
          </p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Aprobadas automáticamente dentro del plazo de 14 días.
          </p>
          <RouterLink
            to="/admin/devoluciones"
            class="mt-1 inline-block text-xs font-medium text-primary-700 hover:underline dark:text-primary-400"
          >
            Ver devoluciones →
          </RouterLink>
        </div>

        <div class="card p-5 sm:col-span-2 xl:col-span-1">
          <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">
            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
            Suscriptores Premium
          </p>
          <p class="mt-2 text-3xl font-extrabold text-amber-600 dark:text-amber-400">
            {{ datos.premium_subscribers ?? 0 }}
          </p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Activos con suscripción vigente.
          </p>
        </div>
      </div>

      <!-- Gráfica de ventas últimos 30 días -->
      <section class="mt-8">
        <header class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
              Ventas últimos 30 días
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Total del periodo: {{ formatearEur(totalVentasGrafica) }}
            </p>
          </div>
        </header>

        <div class="card p-5">
          <div v-if="series.length === 0" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">
            No hay ventas registradas en este periodo.
          </div>

          <div v-else>
            <svg
              :viewBox="`0 0 ${ANCHO_SVG} ${ALTO_SVG}`"
              class="h-48 w-full"
              preserveAspectRatio="none"
              role="img"
              aria-label="Evolución de las ventas de los últimos 30 días"
            >
              <defs>
                <linearGradient id="gradienteVentas" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#2563eb" stop-opacity="0.35" />
                  <stop offset="100%" stop-color="#2563eb" stop-opacity="0" />
                </linearGradient>
              </defs>

              <!-- Líneas guía horizontales -->
              <line
                v-for="i in 4"
                :key="i"
                :x1="PADDING_X"
                :x2="ANCHO_SVG - PADDING_X"
                :y1="PADDING_Y + ((ALTO_SVG - PADDING_Y * 2) * i) / 4"
                :y2="PADDING_Y + ((ALTO_SVG - PADDING_Y * 2) * i) / 4"
                stroke="currentColor"
                stroke-opacity="0.08"
                stroke-dasharray="2 4"
              />

              <!-- Área bajo la curva -->
              <path :d="graficaArea" fill="url(#gradienteVentas)" />

              <!-- Línea principal -->
              <path :d="graficaPath" fill="none" stroke="#2563eb" stroke-width="2" stroke-linejoin="round" />

              <!-- Puntos -->
              <g>
                <circle
                  v-for="(pt, i) in graficaPuntos"
                  :key="i"
                  :cx="pt.x"
                  :cy="pt.y"
                  r="2.5"
                  fill="#2563eb"
                >
                  <title>
                    {{ formatearFechaCorta(pt.date) }} — {{ formatearEur(pt.total) }} ({{ pt.count }} pedidos)
                  </title>
                </circle>
              </g>
            </svg>

            <div class="mt-3 flex justify-between text-[0.7rem] text-slate-500 dark:text-slate-400">
              <span>{{ formatearFechaCorta(series[0]?.date) }}</span>
              <span>{{ formatearFechaCorta(series[series.length - 1]?.date) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Top 5 productos y últimos pedidos -->
      <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section>
          <header class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
              Top 5 productos más vendidos
            </h2>
          </header>

          <div class="card overflow-x-auto">
            <EstadoVacio
              v-if="(datos.top_products ?? []).length === 0"
              icono="inbox"
              titulo="Sin ventas registradas"
              descripcion="Cuando haya pedidos aparecerán aquí los productos más vendidos."
            />
            <table v-else class="w-full min-w-[480px] text-sm">
              <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                <tr>
                  <th class="px-4 py-3">#</th>
                  <th class="px-4 py-3">Producto</th>
                  <th class="px-4 py-3 text-right">Unidades</th>
                  <th class="px-4 py-3 text-right">Ingresos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <tr v-for="(p, i) in datos.top_products" :key="p.product_id">
                  <td class="px-4 py-3 font-bold text-slate-500 dark:text-slate-400">
                    {{ i + 1 }}
                  </td>
                  <td class="px-4 py-3">
                    <RouterLink
                      v-if="p.slug"
                      :to="{ name: 'producto', params: { slug: p.slug } }"
                      class="font-medium text-slate-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-400"
                    >
                      {{ p.name }}
                    </RouterLink>
                    <span v-else class="text-slate-500 italic">Producto eliminado</span>
                  </td>
                  <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-white">
                    {{ p.total_units }}
                  </td>
                  <td class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">
                    {{ formatearEur(p.total_revenue) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section>
          <header class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
              Últimos 10 pedidos
            </h2>
            <RouterLink
              to="/admin/pedidos"
              class="text-sm font-medium text-primary-700 hover:underline dark:text-primary-400"
            >
              Ver todos →
            </RouterLink>
          </header>

          <div class="card overflow-x-auto">
            <EstadoVacio
              v-if="(datos.recent_orders ?? []).length === 0"
              icono="inbox"
              titulo="Sin pedidos recientes"
              descripcion="Aquí aparecerán los últimos pedidos del sistema."
            />
            <table v-else class="w-full min-w-[560px] text-sm">
              <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                <tr>
                  <th class="px-4 py-3">Pedido</th>
                  <th class="px-4 py-3">Comprador</th>
                  <th class="px-4 py-3">Estado</th>
                  <th class="px-4 py-3 text-right">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <tr v-for="p in datos.recent_orders" :key="p.id">
                  <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-900 dark:text-white">
                    {{ p.order_number }}
                    <span class="block text-[0.7rem] font-normal text-slate-500 dark:text-slate-400">
                      {{ formatearFecha(p.created_at) }}
                    </span>
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
                    {{ formatearEur(p.total) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <p class="mt-6 text-xs text-slate-400 dark:text-slate-500">
        Datos en tiempo real, generados {{ formatearFecha(datos.generated_at, 'long') }}.
      </p>
    </template>

    <EstadoVacio
      v-else
      icono="error"
      titulo="No hay datos para mostrar"
      descripcion="No se ha recibido información del servidor."
    >
      <button class="btn-primary" @click="cargar">Reintentar</button>
    </EstadoVacio>
  </PanelLayout>
</template>
