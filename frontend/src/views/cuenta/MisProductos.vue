<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/api'
import PanelLayout from '@/components/PanelLayout.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import Paginacion from '@/components/Paginacion.vue'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { formatearEur } from '@/composables/useEnvio'

const toast = useNotificacionesStore()

const productos = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 })
const cargando = ref(true)
const error = ref(null)

const dialogoEliminar = ref(false)
const productoAEliminar = ref(null)
const eliminando = ref(false)

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

const ETIQUETAS_CONDICION = {
  nuevo: 'Nuevo',
  como_nuevo: 'Como nuevo',
  buen_estado: 'Buen estado',
  usado: 'Usado',
}

async function cargar(pagina = 1) {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get('/mis-productos', { params: { page: pagina, per_page: 12 } })
    productos.value = data?.data ?? []
    meta.value = data?.meta ?? meta.value
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudieron obtener tus productos.'
  } finally {
    cargando.value = false
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
    await api.delete(`/mis-productos/${productoAEliminar.value.id}`)
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

function cambiarPagina(p) {
  cargar(p)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => cargar(1))
</script>

<template>
  <PanelLayout
    tipo="usuario"
    titulo="Mis productos en venta"
    descripcion="Publica artículos nuevos o de segunda mano y véndelos a otros usuarios de NovaCommerce."
  >
    <template #acciones>
      <RouterLink to="/mis-productos/nuevo" class="btn-primary">
        + Publicar producto
      </RouterLink>
    </template>

    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando tus productos..." />
    </div>

    <EstadoVacio
      v-else-if="error"
      icono="error"
      titulo="No se pudieron cargar los productos"
      :descripcion="error"
    >
      <button class="btn-primary" @click="cargar(paginaActual)">Reintentar</button>
    </EstadoVacio>

    <EstadoVacio
      v-else-if="productos.length === 0"
      icono="inbox"
      titulo="Aún no has publicado nada"
      descripcion="Publica tu primer artículo y empieza a vender."
    >
      <RouterLink to="/mis-productos/nuevo" class="btn-primary">+ Publicar producto</RouterLink>
    </EstadoVacio>

    <div v-else class="space-y-4">
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
            <tr>
              <th class="px-4 py-3"></th>
              <th class="px-4 py-3">Nombre</th>
              <th class="px-4 py-3">Categoría</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3 text-right">Precio</th>
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
              <td class="px-4 py-3">
                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                  {{ ETIQUETAS_CONDICION[p.condition] ?? '—' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-white">
                {{ formatearEur(p.price) }}
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <RouterLink
                    :to="`/mis-productos/${p.id}/editar`"
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
      :mensaje="`Se eliminará '${productoAEliminar?.name ?? ''}' de tus publicaciones.`"
      texto-confirmar="Eliminar"
      texto-cancelar="Cancelar"
      variante="danger"
      :cargando="eliminando"
      @confirmar="confirmarEliminar"
    />
  </PanelLayout>
</template>
