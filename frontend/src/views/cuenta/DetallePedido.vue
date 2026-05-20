<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '@/api'
import EstadoVacio from '@/components/EstadoVacio.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import {
  etiquetaMotivo,
  infoEstado,
  infoEstadoDevolucion,
} from '@/composables/useEstadosPedido'
import { formatearEur } from '@/composables/useEnvio'

const route = useRoute()

const pedido = ref(null)
const cargando = ref(true)
const error = ref(null)

const esConfirmacion = computed(() => route.query.confirmacion === '1')

async function cargar(numero) {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get(`/orders/${numero}`)
    pedido.value = data?.data?.order ?? null
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudo cargar el pedido.'
  } finally {
    cargando.value = false
  }
}

function formatearFecha(fecha) {
  if (!fecha) return ''
  try {
    return new Intl.DateTimeFormat('es-ES', {
      year: 'numeric', month: 'long', day: 'numeric',
      hour: '2-digit', minute: '2-digit',
    }).format(new Date(fecha))
  } catch {
    return ''
  }
}

watch(() => route.params.number, (nuevo) => {
  if (nuevo) cargar(nuevo)
})

onMounted(() => {
  if (route.params.number) cargar(route.params.number)
})
</script>

<template>
  <section class="py-4">
    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando el pedido..." />
    </div>

    <EstadoVacio
      v-else-if="error || !pedido"
      icono="error"
      titulo="Pedido no encontrado"
      :descripcion="error || 'No hemos podido encontrar este pedido.'"
    >
      <RouterLink to="/mi-cuenta/pedidos" class="btn-primary">Ver mis pedidos</RouterLink>
    </EstadoVacio>

    <template v-else>
      <!-- Banner de confirmación -->
      <div
        v-if="esConfirmacion"
        class="mb-8 rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-center dark:border-emerald-800 dark:bg-emerald-900/20"
      >
        <div class="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-emerald-900 dark:text-emerald-100">¡Pedido confirmado!</h2>
        <p class="mt-1 text-sm text-emerald-800 dark:text-emerald-200">
          Tu pedido ha sido recibido. Recibirás un correo con todos los detalles.
        </p>
      </div>

      <header class="mb-8 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-6 dark:border-slate-700">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pedido</p>
          <h1 class="font-mono text-2xl font-bold text-slate-900 dark:text-white">
            {{ pedido.order_number }}
          </h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Realizado el {{ formatearFecha(pedido.created_at) }}
          </p>
        </div>
        <span
          class="inline-flex rounded-full px-3 py-1 text-sm font-bold capitalize"
          :class="infoEstado(pedido.status).clases"
        >
          {{ infoEstado(pedido.status).etiqueta }}
        </span>
      </header>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_320px]">
        <!-- Items y dirección -->
        <div class="space-y-6">
          <section class="card p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Productos</h2>
            <ul class="mt-4 divide-y divide-slate-200 dark:divide-slate-700">
              <li
                v-for="item in pedido.items"
                :key="item.id"
                class="flex items-center gap-4 py-4"
              >
                <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                  <img
                    :src="`https://placehold.co/120x120/eef2ff/64748b?text=${encodeURIComponent((item.product_name ?? 'NC').charAt(0))}`"
                    :alt="item.product_name"
                    class="h-full w-full object-cover"
                  />
                </div>
                <div class="flex-1">
                  <RouterLink
                    v-if="item.product?.slug"
                    :to="{ name: 'producto', params: { slug: item.product.slug } }"
                    class="text-sm font-semibold text-slate-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-400"
                  >
                    {{ item.product_name }}
                  </RouterLink>
                  <p v-else class="text-sm font-semibold text-slate-900 dark:text-white">
                    {{ item.product_name }}
                  </p>
                  <p class="text-xs text-slate-500 dark:text-slate-400">
                    Cantidad: {{ item.quantity }} · {{ formatearEur(item.product_price) }}/u
                  </p>
                </div>
                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ formatearEur(item.subtotal) }}</p>
              </li>
            </ul>
          </section>

          <section v-if="pedido.return" class="card p-6">
            <header class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Devolución</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  Solicitada el {{ formatearFecha(pedido.return.created_at) }}
                </p>
              </div>
              <span
                class="inline-flex rounded-full px-3 py-0.5 text-xs font-bold"
                :class="infoEstadoDevolucion(pedido.return.status).clases"
              >
                {{ infoEstadoDevolucion(pedido.return.status).etiqueta }}
              </span>
            </header>

            <dl class="mt-4 space-y-3 text-sm">
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Motivo
                </dt>
                <dd class="text-slate-700 dark:text-slate-300">
                  {{ pedido.return.reason_label || etiquetaMotivo(pedido.return.reason) }}
                </dd>
              </div>
              <div v-if="pedido.return.description">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Descripción
                </dt>
                <dd class="whitespace-pre-line text-slate-700 dark:text-slate-300">
                  {{ pedido.return.description }}
                </dd>
              </div>
              <div v-if="pedido.return.admin_notes">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Notas del equipo
                </dt>
                <dd class="whitespace-pre-line text-slate-700 dark:text-slate-300">
                  {{ pedido.return.admin_notes }}
                </dd>
              </div>
            </dl>
          </section>

          <section class="card p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Dirección de envío</h2>
            <address class="mt-4 not-italic text-sm text-slate-700 dark:text-slate-300">
              <p>{{ pedido.shipping_address }}</p>
              <p>{{ pedido.shipping_postal_code }} {{ pedido.shipping_city }}</p>
              <p>{{ pedido.shipping_country }}</p>
            </address>
            <div v-if="pedido.payment_method || pedido.notes" class="mt-4 space-y-2 text-sm">
              <p v-if="pedido.payment_method" class="text-slate-600 dark:text-slate-400">
                <span class="font-medium text-slate-900 dark:text-white">Método de pago:</span>
                <span class="ml-1 capitalize">{{ pedido.payment_method }}</span>
              </p>
              <p v-if="pedido.notes" class="text-slate-600 dark:text-slate-400">
                <span class="font-medium text-slate-900 dark:text-white">Notas:</span>
                <span class="ml-1">{{ pedido.notes }}</span>
              </p>
            </div>
          </section>
        </div>

        <!-- Totales -->
        <aside class="lg:sticky lg:top-20 lg:self-start">
          <div class="card p-6">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Totales</h3>
            <dl class="mt-4 space-y-3 text-sm">
              <div class="flex justify-between">
                <dt class="text-slate-600 dark:text-slate-400">Subtotal</dt>
                <dd class="font-medium text-slate-900 dark:text-white">{{ formatearEur(pedido.subtotal) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-slate-600 dark:text-slate-400">Envío</dt>
                <dd class="font-medium text-slate-900 dark:text-white">
                  <span v-if="pedido.envio_premium" class="text-emerald-600 dark:text-emerald-400">
                    Envío gratuito Premium
                  </span>
                  <span v-else-if="Number(pedido.shipping_cost) === 0" class="text-emerald-600 dark:text-emerald-400">
                    Gratis
                  </span>
                  <span v-else>{{ formatearEur(pedido.shipping_cost) }}</span>
                </dd>
              </div>
              <div class="flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-700">
                <dt class="text-base font-semibold text-slate-900 dark:text-white">Total</dt>
                <dd class="text-xl font-extrabold text-slate-900 dark:text-white">{{ formatearEur(pedido.total) }}</dd>
              </div>
            </dl>

            <RouterLink
              to="/mi-cuenta/pedidos"
              class="mt-6 block text-center text-sm font-medium text-primary-700 hover:underline dark:text-primary-400"
            >
              ← Volver a mis pedidos
            </RouterLink>
          </div>
        </aside>
      </div>
    </template>
  </section>
</template>
