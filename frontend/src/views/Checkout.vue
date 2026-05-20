<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '@/api'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useCarritoStore } from '@/stores/carrito'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { calcularEnvio, formatearEur } from '@/composables/useEnvio'
import EstadoVacio from '@/components/EstadoVacio.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'

const router = useRouter()
const auth = useAutenticacionStore()
const carrito = useCarritoStore()
const toast = useNotificacionesStore()

const paso = ref(1)
const enviando = ref(false)
const errorStock = ref(null)

const direccion = reactive({
  shipping_address: '',
  shipping_city: '',
  shipping_postal_code: '',
  shipping_country: 'España',
  payment_method: 'tarjeta',
  notes: '',
})

const errores = ref({})

const esPremium = computed(() => Boolean(auth.usuario?.premium_active))
const subtotal = computed(() => Number(carrito.total ?? 0))
const envio = computed(() => calcularEnvio(subtotal.value, esPremium.value))
const total = computed(() => subtotal.value + envio.value)

const pasos = [
  { numero: 1, titulo: 'Dirección de envío' },
  { numero: 2, titulo: 'Resumen' },
  { numero: 3, titulo: 'Confirmación' },
]

function validarDireccion() {
  const e = {}
  if (!direccion.shipping_address.trim()) e.shipping_address = 'La dirección de envío es obligatoria.'
  else if (direccion.shipping_address.length > 500) e.shipping_address = 'La dirección no puede superar los 500 caracteres.'

  if (!direccion.shipping_city.trim()) e.shipping_city = 'La ciudad es obligatoria.'
  else if (direccion.shipping_city.length > 120) e.shipping_city = 'La ciudad no puede superar los 120 caracteres.'

  if (!direccion.shipping_postal_code.trim()) e.shipping_postal_code = 'El código postal es obligatorio.'
  else if (direccion.shipping_postal_code.length > 10) e.shipping_postal_code = 'El código postal no puede superar los 10 caracteres.'

  if (direccion.shipping_country && direccion.shipping_country.length > 60) e.shipping_country = 'El país no puede superar los 60 caracteres.'

  if (direccion.notes && direccion.notes.length > 1000) e.notes = 'Las notas no pueden superar los 1000 caracteres.'

  errores.value = e
  return Object.keys(e).length === 0
}

function siguiente() {
  if (paso.value === 1) {
    if (!validarDireccion()) {
      toast.error('Revisa los datos de envío.')
      return
    }
  }
  if (paso.value < 3) paso.value += 1
}

function anterior() {
  if (paso.value > 1) paso.value -= 1
}

async function confirmarPedido() {
  errorStock.value = null
  enviando.value = true
  try {
    const { data } = await api.post('/checkout', {
      shipping_address: direccion.shipping_address.trim(),
      shipping_city: direccion.shipping_city.trim(),
      shipping_postal_code: direccion.shipping_postal_code.trim(),
      shipping_country: direccion.shipping_country.trim() || 'España',
      payment_method: direccion.payment_method || null,
      notes: direccion.notes?.trim() || null,
    })

    const pedido = data?.data?.order
    if (pedido?.order_number) {
      carrito.reiniciar()
      toast.exito('Pedido confirmado correctamente.')
      router.replace({
        name: 'pedido-detalle',
        params: { number: pedido.order_number },
        query: { confirmacion: '1' },
      })
    } else {
      toast.error('No se pudo procesar el pedido.')
    }
  } catch (err) {
    const status = err?.response?.status
    const mensaje = err?.response?.data?.message ?? 'No se pudo procesar el pedido.'

    if (status === 422) {
      // Mensajes de stock vienen como 422 con un único `message`.
      const errs = err?.response?.data?.errors
      if (errs && typeof errs === 'object') {
        errores.value = errs
        toast.error('Revisa los datos de envío.')
        paso.value = 1
      } else {
        errorStock.value = mensaje
      }
    } else {
      toast.error(mensaje)
    }
  } finally {
    enviando.value = false
  }
}

onMounted(async () => {
  if (!auth.estaAutenticado) {
    router.replace({ path: '/login', query: { redirect: '/checkout' } })
    return
  }
  await carrito.obtenerCarrito()
  if (carrito.estaVacio) {
    // No bloqueamos al usuario; mostramos un EstadoVacio.
  }
})
</script>

<template>
  <section class="py-4">
    <header class="mb-8 text-center">
      <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        Finalizar compra
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Sigue los pasos para completar tu pedido.
      </p>
    </header>

    <!-- Stepper -->
    <ol class="mb-10 flex items-center justify-center gap-2 sm:gap-4">
      <li
        v-for="(p, i) in pasos"
        :key="p.numero"
        class="flex flex-1 items-center sm:flex-none"
      >
        <div class="flex items-center gap-2">
          <span
            class="grid h-8 w-8 place-items-center rounded-full text-xs font-bold transition-colors"
            :class="paso >= p.numero
              ? 'bg-primary-600 text-white'
              : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400'"
          >
            {{ p.numero }}
          </span>
          <span
            class="hidden text-sm font-medium sm:inline"
            :class="paso === p.numero
              ? 'text-slate-900 dark:text-white'
              : 'text-slate-500 dark:text-slate-400'"
          >
            {{ p.titulo }}
          </span>
        </div>
        <span
          v-if="i < pasos.length - 1"
          class="mx-2 h-px flex-1 bg-slate-300 dark:bg-slate-700 sm:w-12 sm:flex-none"
        ></span>
      </li>
    </ol>

    <!-- Vacío -->
    <EstadoVacio
      v-if="carrito.estaVacio && !carrito.cargando"
      icono="inbox"
      titulo="Tu carrito está vacío"
      descripcion="Añade productos antes de proceder al pago."
    >
      <RouterLink to="/catalogo" class="btn-primary">Ver catálogo</RouterLink>
    </EstadoVacio>

    <div v-else-if="carrito.cargando && carrito.articulos.length === 0" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando tu carrito..." />
    </div>

    <div v-else class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_360px]">
      <!-- Pasos -->
      <div class="space-y-6">
        <!-- Paso 1: Dirección -->
        <form
          v-if="paso === 1"
          class="card space-y-5 p-6"
          novalidate
          @submit.prevent="siguiente"
        >
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Dirección de envío</h2>

          <div>
            <label for="ck-address" class="label">Dirección</label>
            <input
              id="ck-address"
              v-model="direccion.shipping_address"
              type="text"
              class="input"
              placeholder="Calle, número, piso..."
              autocomplete="street-address"
            />
            <p v-if="errores.shipping_address" class="form-error">
              {{ Array.isArray(errores.shipping_address) ? errores.shipping_address[0] : errores.shipping_address }}
            </p>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="ck-city" class="label">Ciudad</label>
              <input
                id="ck-city"
                v-model="direccion.shipping_city"
                type="text"
                class="input"
                placeholder="Tu ciudad"
                autocomplete="address-level2"
              />
              <p v-if="errores.shipping_city" class="form-error">
                {{ Array.isArray(errores.shipping_city) ? errores.shipping_city[0] : errores.shipping_city }}
              </p>
            </div>
            <div>
              <label for="ck-postal" class="label">Código postal</label>
              <input
                id="ck-postal"
                v-model="direccion.shipping_postal_code"
                type="text"
                class="input"
                placeholder="28013"
                autocomplete="postal-code"
              />
              <p v-if="errores.shipping_postal_code" class="form-error">
                {{ Array.isArray(errores.shipping_postal_code) ? errores.shipping_postal_code[0] : errores.shipping_postal_code }}
              </p>
            </div>
          </div>

          <div>
            <label for="ck-country" class="label">País</label>
            <input
              id="ck-country"
              v-model="direccion.shipping_country"
              type="text"
              class="input"
              placeholder="España"
              autocomplete="country-name"
            />
            <p v-if="errores.shipping_country" class="form-error">{{ errores.shipping_country }}</p>
          </div>

          <div>
            <label for="ck-payment" class="label">Método de pago</label>
            <select id="ck-payment" v-model="direccion.payment_method" class="input">
              <option value="tarjeta">Tarjeta de crédito/débito</option>
              <option value="paypal">PayPal</option>
              <option value="transferencia">Transferencia bancaria</option>
              <option value="contrareembolso">Contra reembolso</option>
            </select>
          </div>

          <div>
            <label for="ck-notes" class="label">Notas <span class="text-slate-400">(opcional)</span></label>
            <textarea
              id="ck-notes"
              v-model="direccion.notes"
              rows="3"
              class="input"
              placeholder="Indicaciones para la entrega, horarios..."
            ></textarea>
            <p v-if="errores.notes" class="form-error">{{ errores.notes }}</p>
          </div>

          <div class="flex justify-end">
            <button type="submit" class="btn-primary">Siguiente</button>
          </div>
        </form>

        <!-- Paso 2: Resumen -->
        <section v-else-if="paso === 2" class="card space-y-5 p-6">
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Resumen del pedido</h2>

          <ul class="divide-y divide-slate-200 dark:divide-slate-700">
            <li
              v-for="item in carrito.articulos"
              :key="item.id"
              class="flex items-center gap-4 py-3"
            >
              <img
                :src="item.product?.primary_image || 'https://placehold.co/100x100/eef2ff/64748b?text=NC'"
                :alt="item.product?.name ?? 'Producto'"
                class="h-14 w-14 flex-shrink-0 rounded-lg object-cover"
              />
              <div class="flex-1">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ item.product?.name }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Cantidad: {{ item.quantity }} · {{ formatearEur(item.unit_price) }}/u
                </p>
              </div>
              <p class="text-sm font-bold text-slate-900 dark:text-white">{{ formatearEur(item.subtotal) }}</p>
            </li>
          </ul>

          <div class="rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-800/60">
            <h3 class="font-semibold text-slate-900 dark:text-white">Enviar a:</h3>
            <p class="mt-1 text-slate-700 dark:text-slate-300">{{ direccion.shipping_address }}</p>
            <p class="text-slate-700 dark:text-slate-300">
              {{ direccion.shipping_postal_code }} {{ direccion.shipping_city }}, {{ direccion.shipping_country }}
            </p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
              Método de pago: <span class="font-medium capitalize text-slate-700 dark:text-slate-300">{{ direccion.payment_method }}</span>
            </p>
          </div>

          <div class="flex justify-between">
            <button type="button" class="btn-secondary" @click="anterior">Atrás</button>
            <button type="button" class="btn-primary" @click="siguiente">Siguiente</button>
          </div>
        </section>

        <!-- Paso 3: Confirmación -->
        <section v-else class="card space-y-5 p-6">
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Confirmación</h2>
          <p class="text-sm text-slate-600 dark:text-slate-300">
            Al confirmar el pedido, se procesará el pago y se reservarán las unidades de cada producto.
            Recibirás un correo con el detalle de tu pedido.
          </p>

          <div
            v-if="errorStock"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200"
          >
            <p class="font-semibold">No se pudo confirmar el pedido</p>
            <p class="mt-1">{{ errorStock }}</p>
            <RouterLink to="/carrito" class="mt-3 inline-block text-sm font-semibold underline">
              Volver al carrito
            </RouterLink>
          </div>

          <div class="flex flex-col gap-2 sm:flex-row sm:justify-between">
            <button type="button" class="btn-secondary" :disabled="enviando" @click="anterior">Atrás</button>
            <button
              type="button"
              class="btn-primary !py-2.5"
              :disabled="enviando"
              @click="confirmarPedido"
            >
              <svg v-if="enviando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              {{ enviando ? 'Procesando pedido...' : 'Confirmar pedido' }}
            </button>
          </div>
        </section>
      </div>

      <!-- Aside totales -->
      <aside class="lg:sticky lg:top-20 lg:self-start">
        <div class="card p-6">
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">Totales</h3>
          <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between">
              <dt class="text-slate-600 dark:text-slate-400">Productos ({{ carrito.unidadesTotales }})</dt>
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
              v-if="!esPremium && envio > 0"
              class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
            >
              Con
              <RouterLink to="/premium" class="font-semibold underline">NovaCommerce Premium</RouterLink>
              tu envío sería gratis. Solo 50 €/año.
            </p>
            <div class="flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-700">
              <dt class="text-base font-semibold text-slate-900 dark:text-white">Total</dt>
              <dd class="text-xl font-extrabold text-slate-900 dark:text-white">{{ formatearEur(total) }}</dd>
            </div>
          </dl>
        </div>
      </aside>
    </div>
  </section>
</template>
