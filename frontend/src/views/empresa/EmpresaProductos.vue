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
const filtros = ref({ search: '', category: '', page: 1 })
let temporizadorBusqueda = null

const dialogoEliminar = ref(false)
const productoAEliminar = ref(null)
const eliminando = ref(false)

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

async function cargarCategorias() {
  try {
    const { data } = await api.get('/categories')
    categorias.value = data?.data ?? []
  } catch {
    categorias.value = []
  }
}

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const params = {
      page: filtros.value.page,
      per_page: 12,
    }
    if (filtros.value.search) params.search = filtros.value.search
    if (filtros.value.category) params.category = filtros.value.category

    const { data } = await api.get('/empresa/productos', { params })
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

function alCambiarCategoria(evento) {
  filtros.value.category = evento.target.value
  filtros.value.page = 1
  sincronizarRuta()
  cargar()
}

function sincronizarRuta() {
  const query = {}
  if (filtros.value.search) query.search = filtros.value.search
  if (filtros.value.category) query.category = filtros.value.category
  if (filtros.value.page > 1) query.page = String(filtros.value.page)
  router.replace({ query })
}

function cambiarPagina(p) {
  filtros.value.page = p
  sincronizarRuta()
  cargar()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function pedirEliminar(p) {
  productoAEliminar.value = p
  dialogoEliminar.value = true
}

async function confirmarEliminar() {
  if (!productoAEliminar.value) return
  eliminando.value = true
  try {
    await api.delete(`/empresa/productos/${productoAEliminar.value.id}`)
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
  filtros.value.page = Number(nueva.page) || 1
  cargar()
})

onMounted(() => {
  filtros.value.search = route.query.search ?? ''
  filtros.value.category = route.query.category ?? ''
  filtros.value.page = Number(route.query.page) || 1
  cargarCategorias()
  cargar()
})
</script>

<template>
  <PanelLayout
    tipo="empresa"
    titulo="Mis productos"
    descripcion="Crea, edita y elimina los productos de tu catálogo."
  >
    <template #acciones>
      <RouterLink to="/empresa/productos/nuevo" class="btn-primary">
        + Nuevo producto
      </RouterLink>
    </template>

    <div class="card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
      <div class="flex-1">
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
      </div>
      <div class="sm:w-64">
        <select :value="filtros.category" class="input" aria-label="Filtrar por categoría" @change="alCambiarCategoria">
          <option value="">Todas las categorías</option>
          <template v-for="cat in categorias" :key="cat.id">
            <option :value="cat.slug">{{ cat.name }}</option>
            <option v-for="sub in cat.children ?? []" :key="sub.id" :value="sub.slug">
              &nbsp;&nbsp;↳ {{ sub.name }}
            </option>
          </template>
        </select>
      </div>
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
      icono="inbox"
      titulo="Aún no tienes productos"
      descripcion="Crea tu primer producto y empieza a vender."
    >
      <RouterLink to="/empresa/productos/nuevo" class="btn-primary">+ Nuevo producto</RouterLink>
    </EstadoVacio>

    <div v-else class="space-y-4">
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
            <tr>
              <th class="px-4 py-3"></th>
              <th class="px-4 py-3">Nombre</th>
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
                  <RouterLink
                    :to="`/empresa/productos/${p.id}/editar`"
                    class="text-xs font-medium text-primary-700 hover:underline dark:text-primary-400"
                  >
                    Editar
                  </RouterLink>
                  <button
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
