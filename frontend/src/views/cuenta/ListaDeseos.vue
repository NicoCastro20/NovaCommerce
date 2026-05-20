<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/api'
import EstadoVacio from '@/components/EstadoVacio.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import Paginacion from '@/components/Paginacion.vue'
import { useCarritoStore } from '@/stores/carrito'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { formatearEur } from '@/composables/useEnvio'

const route = useRoute()
const router = useRouter()
const carrito = useCarritoStore()
const toast = useNotificacionesStore()

const items = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 })
const cargando = ref(true)
const error = ref(null)

const idsAccion = ref(new Set())

async function cargar(pagina = 1) {
  cargando.value = true
  error.value = null
  try {
    const { data } = await api.get('/wishlist', { params: { page: pagina, per_page: 12 } })
    items.value = data?.data ?? []
    meta.value = data?.meta ?? meta.value
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudo obtener tu lista de deseos.'
  } finally {
    cargando.value = false
  }
}

async function quitar(item) {
  if (!item.product?.id) {
    items.value = items.value.filter((x) => x.id !== item.id)
    return
  }
  idsAccion.value.add(item.id)
  try {
    await api.post(`/wishlist/${item.product.id}`)
    items.value = items.value.filter((x) => x.id !== item.id)
    toast.exito('Producto eliminado de tu lista de deseos.')
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo eliminar el producto.')
  } finally {
    idsAccion.value.delete(item.id)
  }
}

async function moverAlCarrito(item) {
  if (!item.product?.id) return
  if (Number(item.product.stock) <= 0) {
    toast.advertencia('Este producto no tiene stock disponible.')
    return
  }
  idsAccion.value.add(item.id)
  const ok = await carrito.agregarArticulo({ productId: item.product.id, quantity: 1 })
  idsAccion.value.delete(item.id)
  if (ok) toast.exito('Producto añadido al carrito.')
  else toast.error(carrito.error || 'No se pudo añadir al carrito.')
}

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

function cambiarPagina(p) {
  router.replace({ query: { ...route.query, page: String(p) } })
  cargar(p)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => cargar(Number(route.query.page) || 1))
</script>

<template>
  <section class="py-4">
    <header class="mb-8">
      <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Lista de deseos</h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Tus productos guardados. Cuando quieras, añádelos al carrito.
      </p>
    </header>

    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando tu lista..." />
    </div>

    <EstadoVacio
      v-else-if="error"
      icono="error"
      titulo="No se pudo cargar la lista"
      :descripcion="error"
    >
      <button class="btn-primary" @click="cargar(paginaActual)">Reintentar</button>
    </EstadoVacio>

    <EstadoVacio
      v-else-if="items.length === 0"
      icono="inbox"
      titulo="Tu lista de deseos está vacía"
      descripcion="Guarda productos para volver a ellos más tarde."
    >
      <RouterLink to="/catalogo" class="btn-primary">Ver catálogo</RouterLink>
    </EstadoVacio>

    <div v-else class="space-y-6">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <article
          v-for="item in items"
          :key="item.id"
          class="card flex h-full flex-col overflow-hidden"
        >
          <RouterLink
            v-if="item.product?.slug"
            :to="{ name: 'producto', params: { slug: item.product.slug } }"
            class="block aspect-square overflow-hidden bg-slate-100 dark:bg-slate-800"
          >
            <img
              :src="item.product?.primary_image || 'https://placehold.co/400x400/eef2ff/64748b?text=NovaCommerce'"
              :alt="item.product?.name ?? 'Producto'"
              class="h-full w-full object-cover transition-transform duration-300 hover:scale-105"
            />
          </RouterLink>

          <div class="flex flex-1 flex-col p-4">
            <RouterLink
              v-if="item.product?.slug"
              :to="{ name: 'producto', params: { slug: item.product.slug } }"
              class="line-clamp-2 text-sm font-semibold text-slate-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-400"
            >
              {{ item.product?.name ?? 'Producto' }}
            </RouterLink>

            <p class="mt-2 text-lg font-bold text-slate-900 dark:text-white">
              {{ formatearEur(item.product?.price ?? 0) }}
            </p>

            <p
              v-if="item.product && Number(item.product.stock) <= 0"
              class="mt-1 text-xs font-semibold text-red-600 dark:text-red-400"
            >
              Sin stock
            </p>

            <div class="mt-auto flex flex-col gap-2 pt-4">
              <button
                type="button"
                class="btn-primary w-full !py-2 text-xs"
                :disabled="idsAccion.has(item.id) || Number(item.product?.stock ?? 0) <= 0"
                @click="moverAlCarrito(item)"
              >
                Añadir al carrito
              </button>
              <button
                type="button"
                class="text-xs font-medium text-red-600 hover:underline disabled:opacity-50 dark:text-red-400"
                :disabled="idsAccion.has(item.id)"
                @click="quitar(item)"
              >
                Eliminar de la lista
              </button>
            </div>
          </div>
        </article>
      </div>

      <Paginacion
        :pagina-actual="paginaActual"
        :total-paginas="totalPaginas"
        class="pt-4"
        @cambiar="cambiarPagina"
      />
    </div>
  </section>
</template>
