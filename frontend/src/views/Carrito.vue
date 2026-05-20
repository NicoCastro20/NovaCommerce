<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useCarritoStore } from '@/stores/carrito'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { calcularEnvio, ENVIO_GRATIS_DESDE, formatearEur } from '@/composables/useEnvio'
import SelectorCantidad from '@/components/SelectorCantidad.vue'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'

const router = useRouter()
const auth = useAutenticacionStore()
const carrito = useCarritoStore()
const toast = useNotificacionesStore()

const cargandoIds = ref(new Set())
const dialogoVaciar = ref(false)
const vaciando = ref(false)

const esPremium = computed(() => Boolean(auth.usuario?.premium_active))
const subtotal = computed(() => Number(carrito.total ?? 0))
const envio = computed(() => calcularEnvio(subtotal.value, esPremium.value))
const total = computed(() => subtotal.value + envio.value)

const faltaParaEnvioGratis = computed(() =>
  Math.max(0, ENVIO_GRATIS_DESDE - subtotal.value),
)

async function actualizarCantidad(item, nueva) {
  if (nueva === item.quantity) return
  cargandoIds.value.add(item.id)
  const ok = await carrito.actualizarArticulo({ itemId: item.id, quantity: nueva })
  cargandoIds.value.delete(item.id)
  if (!ok) toast.error(carrito.error || 'No se pudo actualizar la cantidad.')
}

async function eliminar(item) {
  cargandoIds.value.add(item.id)
  const ok = await carrito.eliminarArticulo(item.id)
  cargandoIds.value.delete(item.id)
  if (ok) toast.exito('Producto eliminado del carrito.')
  else toast.error(carrito.error || 'No se pudo eliminar el producto.')
}

async function confirmarVaciar() {
  vaciando.value = true
  const ok = await carrito.vaciarCarrito()
  vaciando.value = false
  dialogoVaciar.value = false
  if (ok) toast.exito('Carrito vaciado correctamente.')
  else toast.error(carrito.error || 'No se pudo vaciar el carrito.')
}

function procederPago() {
  if (!auth.estaAutenticado) {
    toast.info('Inicia sesión para continuar con la compra.')
    router.push({ path: '/login', query: { redirect: '/checkout' } })
    return
  }
  router.push('/checkout')
}

onMounted(() => {
  if (auth.estaAutenticado) carrito.obtenerCarrito()
})
</script>

<template>
  <section class="py-4">
    <header class="mb-8">
      <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        Tu carrito
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Revisa los productos antes de proceder al pago.
      </p>
    </header>

    <!-- No autenticado -->
    <EstadoVacio
      v-if="!auth.estaAutenticado"
      icono="inbox"
      titulo="Inicia sesión para ver tu carrito"
      descripcion="Tu carrito se guarda asociado a tu cuenta. Inicia sesión o crea una para empezar a comprar."
    >
      <div class="flex flex-wrap justify-center gap-2">
        <RouterLink to="/login" class="btn-primary">Iniciar sesión</RouterLink>
        <RouterLink to="/registro" class="btn-secondary">Crear cuenta</RouterLink>
      </div>
    </EstadoVacio>

    <!-- Cargando -->
    <div v-else-if="carrito.cargando && carrito.articulos.length === 0" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando tu carrito..." />
    </div>

    <!-- Vacío -->
    <EstadoVacio
      v-else-if="carrito.estaVacio"
      icono="inbox"
      titulo="Tu carrito está vacío"
      descripcion="Cuando añadas productos los verás aquí."
    >
      <RouterLink to="/catalogo" class="btn-primary">Ver catálogo</RouterLink>
    </EstadoVacio>

    <!-- Lista + resumen -->
    <div v-else class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_360px]">
      <div class="space-y-3">
        <article
          v-for="item in carrito.articulos"
          :key="item.id"
          class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center"
        >
          <RouterLink
            :to="{ name: 'producto', params: { slug: item.product?.slug ?? '' } }"
            class="block h-28 w-28 flex-shrink-0 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800"
          >
            <img
              :src="item.product?.primary_image || 'https://placehold.co/200x200/eef2ff/64748b?text=NovaCommerce'"
              :alt="item.product?.name ?? 'Producto'"
              class="h-full w-full object-cover"
            />
          </RouterLink>

          <div class="flex-1">
            <RouterLink
              :to="{ name: 'producto', params: { slug: item.product?.slug ?? '' } }"
              class="text-base font-semibold text-slate-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-400"
            >
              {{ item.product?.name ?? 'Producto eliminado' }}
            </RouterLink>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Precio unitario: {{ formatearEur(item.unit_price) }}
            </p>
            <p
              v-if="item.product && item.product.stock < item.quantity"
              class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400"
            >
              Solo quedan {{ item.product.stock }} unidades disponibles.
            </p>
          </div>

          <div class="flex items-center gap-4 sm:flex-col sm:items-end">
            <SelectorCantidad
              :model-value="item.quantity"
              :min="1"
              :max="Number(item.product?.stock ?? 99)"
              :disabled="cargandoIds.has(item.id)"
              @update:model-value="actualizarCantidad(item, $event)"
            />

            <p class="min-w-[6rem] text-right text-base font-bold text-slate-900 dark:text-white">
              {{ formatearEur(item.subtotal) }}
            </p>

            <button
              type="button"
              class="text-sm text-red-600 hover:underline disabled:opacity-50 dark:text-red-400"
              :disabled="cargandoIds.has(item.id)"
              @click="eliminar(item)"
            >
              Eliminar
            </button>
          </div>
        </article>

        <div class="pt-2">
          <button
            type="button"
            class="text-sm text-red-600 hover:underline dark:text-red-400"
            @click="dialogoVaciar = true"
          >
            Vaciar carrito
          </button>
        </div>
      </div>

      <!-- Resumen -->
      <aside class="lg:sticky lg:top-20 lg:self-start">
        <div class="card p-6">
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Resumen del pedido</h2>

          <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between">
              <dt class="text-slate-600 dark:text-slate-400">Subtotal</dt>
              <dd class="font-medium text-slate-900 dark:text-white">{{ formatearEur(subtotal) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-600 dark:text-slate-400">Envío</dt>
              <dd class="font-medium text-slate-900 dark:text-white">
                <span v-if="esPremium" class="text-emerald-600 dark:text-emerald-400">
                  Gratis (Premium)
                </span>
                <span v-else-if="envio === 0" class="text-emerald-600 dark:text-emerald-400">
                  Gratis
                </span>
                <span v-else>{{ formatearEur(envio) }}</span>
              </dd>
            </div>
            <p
              v-if="!esPremium && envio > 0 && faltaParaEnvioGratis > 0"
              class="rounded-md bg-primary-50 px-3 py-2 text-xs text-primary-800 dark:bg-primary-900/20 dark:text-primary-300"
            >
              Te faltan {{ formatearEur(faltaParaEnvioGratis) }} para conseguir envío gratis.
            </p>
            <p
              v-if="!esPremium && envio > 0"
              class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
            >
              ¿Quieres envío gratis siempre?
              <RouterLink to="/premium" class="font-semibold underline">
                Hazte Premium
              </RouterLink>
              por solo 50 €/año.
            </p>
            <div class="border-t border-slate-200 pt-3 dark:border-slate-700">
              <div class="flex items-center justify-between">
                <dt class="text-base font-semibold text-slate-900 dark:text-white">Total</dt>
                <dd class="text-xl font-extrabold text-slate-900 dark:text-white">{{ formatearEur(total) }}</dd>
              </div>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Impuestos incluidos.</p>
            </div>
          </dl>

          <button
            type="button"
            class="btn-primary mt-6 w-full !py-2.5"
            :disabled="carrito.cargando"
            @click="procederPago"
          >
            Proceder al pago
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
            </svg>
          </button>

          <RouterLink
            to="/catalogo"
            class="mt-3 block text-center text-sm font-medium text-primary-700 hover:underline dark:text-primary-400"
          >
            Seguir comprando
          </RouterLink>
        </div>
      </aside>
    </div>

    <DialogoConfirmacion
      v-model:abierto="dialogoVaciar"
      titulo="¿Vaciar el carrito?"
      mensaje="Se eliminarán todos los productos de tu carrito. Esta acción no se puede deshacer."
      texto-confirmar="Vaciar carrito"
      texto-cancelar="Cancelar"
      variante="danger"
      :cargando="vaciando"
      @confirmar="confirmarVaciar"
    />
  </section>
</template>
