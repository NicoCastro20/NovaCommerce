<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/api'
import PanelLayout from '@/components/PanelLayout.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import Paginacion from '@/components/Paginacion.vue'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { formatearEur } from '@/composables/useEnvio'

const route = useRoute()
const router = useRouter()
const toast = useNotificacionesStore()

const productos = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 })
const cargando = ref(true)
const error = ref(null)

const categorias = ref([])
const vendedores = ref([])

const filtros = ref({
  search: '',
  category: '',
  seller_id: '',
  status: '',
  page: 1,
})
let temporizadorBusqueda = null

const dialogoEliminar = ref(false)
const productoAEliminar = ref(null)
const eliminando = ref(false)
const togglingId = ref(null)

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

async function cargarCategorias() {
  try {
    const { data } = await api.get('/categories')
    categorias.value = data?.data?.categories ?? []
  } catch {
    categorias.value = []
  }
}

async function cargarVendedores() {
  try {
    const { data } = await api.get('/admin/users', { params: { role: 'empresa', per_page: 100 } })
    vendedores.value = data?.data ?? []
  } catch {
    vendedores.value = []
  }
}

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const params = { page: filtros.value.page, per_page: 12 }
    if (filtros.value.search) params.search = filtros.value.search
    if (filtros.value.category) params.category = filtros.value.category
    if (filtros.value.seller_id) params.seller_id = filtros.value.seller_id
    if (filtros.value.status) params.status = filtros.value.status

    const { data } = await api.get('/admin/products', { params })
    productos.value = data?.data ?? []
    meta.value = data?.meta ?? meta.value
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudieron obtener los productos.'
  } finally {
    cargando.value = false
  }
}

function alBuscar(evento) {
  const valor = evento.target.value
  if (temporizadorBusqueda) clearTimeout(temporizadorBusqueda)
  temporizadorBusqueda = setTimeout(() => {
    filtros.value.search = valor
    filtros.value.page = 1
    sincronizarRuta()
    cargar()
  }, 300)
}

function alCambiarFiltro() {
  filtros.value.page = 1
  sincronizarRuta()
  cargar()
}

function sincronizarRuta() {
  const query = {}
  if (filtros.value.search) query.search = filtros.value.search
  if (filtros.value.category) query.category = filtros.value.category
  if (filtros.value.seller_id) query.seller_id = filtros.value.seller_id
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

async function alternar(p) {
  togglingId.value = p.id
  try {
    const { data } = await api.put(`/admin/products/${p.id}/toggle`)
    p.is_active = data?.data?.is_active ?? !p.is_active
    toast.exito(p.is_active ? 'Producto activado.' : 'Producto desactivado.')
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo cambiar el estado.')
  } finally {
    togglingId.value = null
  }
}

function pedirEliminar(p) {
  productoAEliminar.value = p
  dialogoEliminar.value = true
}

async function confirmarEliminar() {
  if (!productoAEliminar.value) return
  eliminando.value = true
  try {
    await api.delete(`/admin/products/${productoAEliminar.value.id}`)
    productos.value = productos.value.filter((x) => x.id !== productoAEliminar.value.id)
    toast.exito('Producto eliminado correctamente.')
    dialogoEliminar.value = false
    productoAEliminar.value = null
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo eliminar el producto.')
  } finally {
    eliminando.value = false
  }
}

watch(() => route.query, (nueva) => {
  filtros.value.search = nueva.search ?? ''
  filtros.value.category = nueva.category ?? ''
  filtros.value.seller_id = nueva.seller_id ?? ''
  filtros.value.status = nueva.status ?? ''
  filtros.value.page = Number(nueva.page) || 1
  cargar()
})

onMounted(() => {
  filtros.value.search = route.query.search ?? ''
  filtros.value.category = route.query.category ?? ''
  filtros.value.seller_id = route.query.seller_id ?? ''
  filtros.value.status = route.query.status ?? ''
  filtros.value.page = Number(route.query.page) || 1
  cargarCategorias()
  cargarVendedores()
  cargar()
})
</script>

<template>
  <PanelLayout
    tipo="admin"
    titulo="Gestión de productos"
    descripcion="Listado de todos los productos del sistema, incluidos inactivos y eliminados."
  >
    <!-- Filtros -->
    <div class="card mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
        </span>
        <input
          :value="filtros.search"
          type="search"
          class="input pl-9"
          placeholder="Buscar por nombre..."
          aria-label="Buscar productos"
          @input="alBuscar"
        />
      </div>

      <select
        v-model="filtros.category"
        class="input"
        aria-label="Filtrar por categoría"
        @change="alCambiarFiltro"
      >
        <option value="">Todas las categorías</option>
        <template v-for="cat in categorias" :key="cat.id">
          <option :value="cat.slug">{{ cat.name }}</option>
          <option v-for="sub in cat.children ?? []" :key="sub.id" :value="sub.slug">
            &nbsp;&nbsp;↳ {{ sub.name }}
          </option>
        </template>
      </select>

      <select
        v-model="filtros.seller_id"
        class="input"
        aria-label="Filtrar por vendedor"
        @change="alCambiarFiltro"
      >
        <option value="">Todos los vendedores</option>
        <option v-for="v in vendedores" :key="v.id" :value="v.id">
          {{ v.company_name || v.name }}
        </option>
      </select>

      <select
        v-model="filtros.status"
        class="input"
        aria-label="Filtrar por estado"
        @change="alCambiarFiltro"
      >
        <option value="">Cualquier estado</option>
        <option value="active">Activos</option>
        <option value="inactive">Inactivos</option>
        <option value="trashed">Eliminados</option>
      </select>
    </div>

    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando productos..." />
    </div>

    <EstadoVacio
      v-else-if="error"
      icono="error"
      titulo="No se pudieron cargar los productos"
      :descripcion="error"
    >
      <button class="btn-primary" @click="cargar">Reintentar</button>
    </EstadoVacio>

    <EstadoVacio
      v-else-if="productos.length === 0"
      icono="search"
      titulo="No hay productos que coincidan"
      descripcion="Prueba a ajustar los filtros o limpiar la búsqueda."
    />

    <div v-else class="space-y-4">
      <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
            <tr>
              <th class="px-4 py-3"></th>
              <th class="px-4 py-3">Nombre</th>
              <th class="px-4 py-3">Vendedor</th>
              <th class="px-4 py-3">Categoría</th>
              <th class="px-4 py-3 text-right">Precio</th>
              <th class="px-4 py-3 text-right">Stock</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr
              v-for="p in productos"
              :key="p.id"
              class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40"
            >
              <td class="px-4 py-3">
                <div class="h-12 w-12 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800">
                  <img
                    :src="p.primary_image || 'https://placehold.co/100x100/eef2ff/64748b?text=NC'"
                    :alt="p.name"
                    class="h-full w-full object-cover"
                  />
                </div>
              </td>
              <td class="px-4 py-3">
                <RouterLink
                  :to="{ name: 'producto', params: { slug: p.slug } }"
                  class="font-medium text-slate-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-400"
                >
                  {{ p.name }}
                </RouterLink>
              </td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                {{ p.seller?.company_name || p.seller?.name || '—' }}
              </td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                {{ p.category?.name ?? '—' }}
              </td>
              <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-white">
                {{ formatearEur(p.price) }}
              </td>
              <td class="px-4 py-3 text-right">
                <span :class="Number(p.stock) <= 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'">
                  {{ p.stock }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span
                  v-if="p.deleted_at"
                  class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-800 dark:bg-red-900/40 dark:text-red-200"
                >
                  Eliminado
                </span>
                <span
                  v-else
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold"
                  :class="p.is_active
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                    : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300'"
                >
                  {{ p.is_active ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <button
                    type="button"
                    class="text-xs font-medium text-primary-700 hover:underline dark:text-primary-400 disabled:opacity-50"
                    :disabled="togglingId === p.id || !!p.deleted_at"
                    @click="alternar(p)"
                  >
                    {{ p.is_active ? 'Desactivar' : 'Activar' }}
                  </button>
                  <button
                    v-if="!p.deleted_at"
                    type="button"
                    class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"
                    @click="pedirEliminar(p)"
                  >
                    Eliminar
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Paginacion
        :pagina-actual="paginaActual"
        :total-paginas="totalPaginas"
        @cambiar="cambiarPagina"
      />
    </div>

    <DialogoConfirmacion
      v-model:abierto="dialogoEliminar"
      titulo="¿Eliminar este producto?"
      :mensaje="`Se eliminará '${productoAEliminar?.name ?? ''}'. Podrás recuperarlo desde la base de datos si fuera necesario.`"
      texto-confirmar="Eliminar"
      texto-cancelar="Cancelar"
      variante="danger"
      :cargando="eliminando"
      @confirmar="confirmarEliminar"
    />
  </PanelLayout>
</template>
