<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/api'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import Paginacion from '@/components/Paginacion.vue'
import {
  MOTIVOS_DEVOLUCION,
  infoEstado,
  infoEstadoDevolucion,
} from '@/composables/useEstadosPedido'
import { formatearEur } from '@/composables/useEnvio'
import { useNotificacionesStore } from '@/stores/notificaciones'

const route = useRoute()
const router = useRouter()
const toast = useNotificacionesStore()

const pedidos = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 })
const cargando = ref(true)
const error = ref(null)

// Diálogo de cancelación
const dialogoCancelar = reactive({ abierto: false, pedido: null, cargando: false })

// Modal de devolución
const modalDevolver = reactive({
  abierto: false,
  pedido: null,
  reason: '',
  description: '',
  errores: {},
  enviando: false,
})

async function cargar(pagina = 1) {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get('/orders', { params: { page: pagina, per_page: 12 } })
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
    return new Intl.DateTimeFormat('es-ES', {
      year: 'numeric', month: 'short', day: 'numeric',
    }).format(new Date(fecha))
  } catch {
    return ''
  }
}

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

function cambiarPagina(p) {
  router.replace({ query: { ...route.query, page: String(p) } })
  cargar(p)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// ── Cancelar pedido ─────────────────────────────────────────────────────────
function abrirCancelar(pedido) {
  dialogoCancelar.pedido = pedido
  dialogoCancelar.abierto = true
}

async function confirmarCancelar() {
  const pedido = dialogoCancelar.pedido
  if (!pedido) return
  dialogoCancelar.cargando = true
  try {
    const { data } = await api.post(`/orders/${pedido.order_number}/cancel`)
    const actualizado = data?.data?.order
    if (actualizado) {
      const idx = pedidos.value.findIndex((p) => p.id === actualizado.id)
      if (idx !== -1) pedidos.value[idx] = actualizado
    }
    toast.exito(data?.message ?? 'Pedido cancelado correctamente. Se ha devuelto el stock.')
    dialogoCancelar.abierto = false
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo cancelar el pedido.')
  } finally {
    dialogoCancelar.cargando = false
  }
}

// ── Solicitar devolución ────────────────────────────────────────────────────
function abrirDevolver(pedido) {
  modalDevolver.pedido = pedido
  modalDevolver.reason = ''
  modalDevolver.description = ''
  modalDevolver.errores = {}
  modalDevolver.abierto = true
}

function cerrarDevolver() {
  if (modalDevolver.enviando) return
  modalDevolver.abierto = false
}

async function enviarDevolucion() {
  modalDevolver.errores = {}
  if (!modalDevolver.reason) {
    modalDevolver.errores.reason = 'Debes seleccionar un motivo.'
    return
  }

  const pedido = modalDevolver.pedido
  if (!pedido) return

  modalDevolver.enviando = true
  try {
    const { data } = await api.post(`/orders/${pedido.order_number}/return`, {
      reason: modalDevolver.reason,
      description: modalDevolver.description || null,
    })
    toast.exito(data?.message ?? 'Devolución aprobada automáticamente. El pedido pasa a estado devuelto.')
    modalDevolver.abierto = false
    // Refresca para reflejar el nuevo estado 'devuelto' y el badge de la devolución.
    cargar(paginaActual.value)
  } catch (err) {
    if (err?.response?.status === 422 && err?.response?.data?.errors) {
      modalDevolver.errores = err.response.data.errors
    }
    toast.error(err?.response?.data?.message ?? 'No se pudo enviar la solicitud.')
  } finally {
    modalDevolver.enviando = false
  }
}

// ── Helpers de UI ───────────────────────────────────────────────────────────
function plazoExpirado(pedido) {
  // El pedido está entregado pero no se puede devolver (no por ya tener devolución).
  return pedido.status === 'entregado' && !pedido.can_be_returned && !pedido.return
}

function diasRestantesTexto(pedido) {
  const dias = pedido?.return_days_left
  if (typeof dias !== 'number') return ''
  if (dias <= 0) return 'Último día para solicitar la devolución'
  if (dias === 1) return 'Te queda 1 día para solicitar la devolución'
  return `Te quedan ${dias} días para solicitar la devolución`
}

function valor(err) {
  return Array.isArray(err) ? err[0] : err
}

onMounted(() => cargar(Number(route.query.page) || 1))
</script>

<template>
  <section class="py-4">
    <header class="mb-8 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Mis pedidos</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          Consulta el historial y el estado de tus pedidos.
        </p>
      </div>
      <RouterLink
        to="/mi-cuenta/devoluciones"
        class="text-sm font-medium text-primary-700 hover:underline dark:text-primary-400"
      >
        Mis devoluciones →
      </RouterLink>
    </header>

    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando tus pedidos..." />
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
      titulo="Aún no tienes pedidos"
      descripcion="Cuando hagas tu primer pedido aparecerá aquí."
    >
      <RouterLink to="/catalogo" class="btn-primary">Ver catálogo</RouterLink>
    </EstadoVacio>

    <div v-else class="space-y-4">
      <!-- Tabla en escritorio -->
      <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 md:block">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
            <tr>
              <th class="px-4 py-3">Pedido</th>
              <th class="px-4 py-3">Fecha</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3 text-right">Total</th>
              <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr
              v-for="p in pedidos"
              :key="p.id"
              class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40"
            >
              <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-900 dark:text-white">
                {{ p.order_number }}
              </td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                {{ formatearFecha(p.created_at) }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold capitalize"
                  :class="infoEstado(p.status).clases"
                >
                  {{ infoEstado(p.status).etiqueta }}
                </span>
                <span
                  v-if="p.return"
                  class="ml-2 inline-flex rounded-full px-2 py-0.5 text-[0.65rem] font-bold"
                  :class="infoEstadoDevolucion(p.return.status).clases"
                  :title="`Devolución ${infoEstadoDevolucion(p.return.status).etiqueta.toLowerCase()}`"
                >
                  Dev: {{ infoEstadoDevolucion(p.return.status).etiqueta }}
                </span>
              </td>
              <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">
                {{ formatearEur(p.total) }}
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex flex-col items-end gap-1">
                  <div class="flex flex-wrap items-center justify-end gap-2">
                    <RouterLink
                      :to="{ name: 'pedido-detalle', params: { number: p.order_number } }"
                      class="text-sm font-medium text-primary-700 hover:underline dark:text-primary-400"
                    >
                      Ver detalle
                    </RouterLink>

                    <button
                      v-if="p.can_be_cancelled"
                      type="button"
                      class="rounded-md bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
                      @click="abrirCancelar(p)"
                    >
                      Cancelar pedido
                    </button>

                    <button
                      v-else-if="p.can_be_returned"
                      type="button"
                      class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                      @click="abrirDevolver(p)"
                    >
                      Solicitar devolución
                    </button>

                    <span
                      v-else-if="plazoExpirado(p)"
                      class="text-xs italic text-slate-400 dark:text-slate-500"
                    >
                      El plazo de devolución de 14 días ha expirado
                    </span>
                  </div>
                  <p
                    v-if="p.can_be_returned"
                    class="text-[0.7rem] text-slate-500 dark:text-slate-400"
                  >
                    {{ diasRestantesTexto(p) }}
                  </p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Cards en móvil -->
      <div class="space-y-3 md:hidden">
        <div
          v-for="p in pedidos"
          :key="p.id"
          class="card block p-4"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="font-mono text-sm font-bold text-slate-900 dark:text-white">{{ p.order_number }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ formatearFecha(p.created_at) }}</p>
            </div>
            <div class="flex flex-col items-end gap-1">
              <span
                class="inline-flex rounded-full px-2 py-0.5 text-[0.65rem] font-bold capitalize"
                :class="infoEstado(p.status).clases"
              >
                {{ infoEstado(p.status).etiqueta }}
              </span>
              <span
                v-if="p.return"
                class="inline-flex rounded-full px-2 py-0.5 text-[0.6rem] font-bold"
                :class="infoEstadoDevolucion(p.return.status).clases"
              >
                Dev: {{ infoEstadoDevolucion(p.return.status).etiqueta }}
              </span>
            </div>
          </div>
          <p class="mt-3 text-base font-extrabold text-slate-900 dark:text-white">{{ formatearEur(p.total) }}</p>

          <div class="mt-3 flex flex-wrap items-center gap-2">
            <RouterLink
              :to="{ name: 'pedido-detalle', params: { number: p.order_number } }"
              class="text-xs font-medium text-primary-700 hover:underline dark:text-primary-400"
            >
              Ver detalle
            </RouterLink>
            <button
              v-if="p.can_be_cancelled"
              type="button"
              class="rounded-md bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
              @click="abrirCancelar(p)"
            >
              Cancelar pedido
            </button>
            <button
              v-else-if="p.can_be_returned"
              type="button"
              class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200"
              @click="abrirDevolver(p)"
            >
              Solicitar devolución
            </button>
            <span
              v-else-if="plazoExpirado(p)"
              class="text-xs italic text-slate-400 dark:text-slate-500"
            >
              El plazo de devolución de 14 días ha expirado
            </span>
          </div>
          <p
            v-if="p.can_be_returned"
            class="mt-1 text-[0.7rem] text-slate-500 dark:text-slate-400"
          >
            {{ diasRestantesTexto(p) }}
          </p>
        </div>
      </div>

      <Paginacion
        :pagina-actual="paginaActual"
        :total-paginas="totalPaginas"
        class="pt-4"
        @cambiar="cambiarPagina"
      />
    </div>

    <!-- Diálogo de cancelación -->
    <DialogoConfirmacion
      v-model:abierto="dialogoCancelar.abierto"
      titulo="¿Seguro que quieres cancelar este pedido?"
      mensaje="Se devolverá el stock y el pedido pasará a estado cancelado. Esta acción no se puede deshacer."
      texto-confirmar="Sí, cancelar"
      texto-cancelar="Volver"
      variante="danger"
      :cargando="dialogoCancelar.cargando"
      @confirmar="confirmarCancelar"
    />

    <!-- Modal de devolución -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="modalDevolver.abierto"
          class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 px-4"
          role="dialog"
          aria-modal="true"
          @click.self="cerrarDevolver"
        >
          <div class="card w-full max-w-lg p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
              Solicitar devolución
            </h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Pedido <span class="font-mono">{{ modalDevolver.pedido?.order_number }}</span>
            </p>
            <p
              v-if="modalDevolver.pedido"
              class="mt-2 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200"
            >
              Dentro del plazo legal de 14 días la devolución se aprueba al instante: no
              hace falta esperar a que la empresa o el equipo lo revisen.
              <span v-if="modalDevolver.pedido.can_be_returned" class="block mt-1 font-medium">
                {{ diasRestantesTexto(modalDevolver.pedido) }}.
              </span>
            </p>

            <form class="mt-5 space-y-4" novalidate @submit.prevent="enviarDevolucion">
              <div>
                <label for="dev-motivo" class="label">Motivo de la devolución</label>
                <select id="dev-motivo" v-model="modalDevolver.reason" class="input">
                  <option value="">Selecciona un motivo...</option>
                  <option v-for="m in MOTIVOS_DEVOLUCION" :key="m.valor" :value="m.valor">
                    {{ m.etiqueta }}
                  </option>
                </select>
                <p v-if="modalDevolver.errores.reason" class="form-error">
                  {{ valor(modalDevolver.errores.reason) }}
                </p>
              </div>

              <div>
                <label for="dev-desc" class="label">
                  Comentarios <span class="text-slate-400">(opcional)</span>
                </label>
                <textarea
                  id="dev-desc"
                  v-model="modalDevolver.description"
                  class="input min-h-[100px]"
                  maxlength="1000"
                  placeholder="Cuéntanos algo más si quieres..."
                ></textarea>
                <p v-if="modalDevolver.errores.description" class="form-error">
                  {{ valor(modalDevolver.errores.description) }}
                </p>
              </div>

              <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                  type="button"
                  class="btn-secondary"
                  :disabled="modalDevolver.enviando"
                  @click="cerrarDevolver"
                >
                  Cancelar
                </button>
                <button type="submit" class="btn-primary" :disabled="modalDevolver.enviando">
                  {{ modalDevolver.enviando ? 'Procesando...' : 'Solicitar devolución' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>
