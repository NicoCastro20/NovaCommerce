<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api'
import { useProductoStore } from '@/stores/producto'
import TarjetaProducto from '@/components/TarjetaProducto.vue'
import ProductCardSkeleton from '@/components/ProductCardSkeleton.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'

const route = useRoute()
const router = useRouter()
const productoStore = useProductoStore()

const categorias = ref([])
const cargandoCategorias = ref(false)

const filtros = ref({
  type: 'nuevo', // 'nuevo' | 'segunda_mano' | 'todos'
  search: '',
  category: '',
  min_price: '',
  max_price: '',
  sort: 'newest',
  offers: false,
  page: 1,
  per_page: 12,
})

const filtrosMovilAbierto = ref(false)
let temporizadorBusqueda = null

const TIPOS_VALIDOS = ['nuevo', 'segunda_mano', 'todos']

const tabs = [
  { valor: 'nuevo',        etiqueta: 'Productos de tiendas',      descripcion: 'Tiendas verificadas con productos nuevos.' },
  { valor: 'segunda_mano', etiqueta: 'Productos de particulares', descripcion: 'Publicados por usuarios particulares.' },
  { valor: 'todos',        etiqueta: 'Todos',                     descripcion: 'Ver el catálogo completo mezclado.' },
]

const tabActual = computed(() => tabs.find((t) => t.valor === filtros.value.type) ?? tabs[0])

// Carga inicial de categorías para el sidebar.
async function cargarCategorias() {
  cargandoCategorias.value = true
  try {
    const { data } = await api.get('/categories')
    categorias.value = data?.data ?? []
  } catch {
    categorias.value = []
  } finally {
    cargandoCategorias.value = false
  }
}

async function cargarProductos() {
  productoStore.establecerFiltro({
    // 'todos' significa sin filtro de tipo: enviamos cadena vacía y el store la depura.
    type: filtros.value.type === 'todos' ? '' : filtros.value.type,
    search: filtros.value.search || '',
    category: filtros.value.category || '',
    min_price: filtros.value.min_price === '' ? null : Number(filtros.value.min_price),
    max_price: filtros.value.max_price === '' ? null : Number(filtros.value.max_price),
    sort: filtros.value.sort,
    offers: !!filtros.value.offers,
    page: filtros.value.page,
    per_page: filtros.value.per_page,
  })
  await productoStore.obtenerProductos()
}

// Sincronizar filtros desde la URL al cargar y cuando cambien (p. ej. desde la barra de búsqueda).
function leerFiltrosDeRuta() {
  const q = route.query
  // Sin parámetro de tipo, por defecto mostramos "Productos de tiendas".
  const tipoCrudo = q.type ?? 'nuevo'
  const tipo = TIPOS_VALIDOS.includes(tipoCrudo) ? tipoCrudo : 'nuevo'

  const ofertasActivas = q.offers === '1' || q.offers === 'true' || q.offers === true

  filtros.value = {
    type: tipo,
    search: q.search ?? '',
    category: q.category ?? '',
    min_price: q.min_price ?? '',
    max_price: q.max_price ?? '',
    sort: q.sort ?? 'newest',
    offers: ofertasActivas,
    page: Number(q.page) || 1,
    per_page: Number(q.per_page) || 12,
  }
}

function escribirFiltrosEnRuta() {
  const query = {}
  // Siempre exponemos el tipo en la URL para que se pueda compartir el enlace de la pestaña.
  if (filtros.value.type) query.type = filtros.value.type
  if (filtros.value.search) query.search = filtros.value.search
  if (filtros.value.category) query.category = filtros.value.category
  if (filtros.value.min_price !== '' && filtros.value.min_price !== null) query.min_price = String(filtros.value.min_price)
  if (filtros.value.max_price !== '' && filtros.value.max_price !== null) query.max_price = String(filtros.value.max_price)
  if (filtros.value.sort && filtros.value.sort !== 'newest') query.sort = filtros.value.sort
  if (filtros.value.offers) query.offers = 'true'
  if (filtros.value.page > 1) query.page = String(filtros.value.page)
  router.replace({ name: 'catalogo', query })
}

function cambiarTipo(nuevoTipo) {
  if (!TIPOS_VALIDOS.includes(nuevoTipo)) return
  if (filtros.value.type === nuevoTipo) return
  aplicarFiltro({ type: nuevoTipo })
}

function aplicarFiltro(parche, opciones = { resetearPagina: true }) {
  if (opciones.resetearPagina) parche.page = 1
  filtros.value = { ...filtros.value, ...parche }
  escribirFiltrosEnRuta()
  cargarProductos()
}

function alCambiarBuscador(evento) {
  const valor = evento.target.value
  if (temporizadorBusqueda) clearTimeout(temporizadorBusqueda)
  temporizadorBusqueda = setTimeout(() => {
    aplicarFiltro({ search: valor })
  }, 300)
}

function limpiarFiltros() {
  aplicarFiltro({
    search: '',
    category: '',
    min_price: '',
    max_price: '',
    sort: 'newest',
    offers: false,
  })
}

function cambiarPagina(nueva) {
  if (nueva < 1 || nueva > totalPaginas.value) return
  filtros.value.page = nueva
  escribirFiltrosEnRuta()
  cargarProductos()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const productos = computed(() => productoStore.productos)
const cargando = computed(() => productoStore.cargando)
const meta = computed(() => productoStore.meta)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)
const paginaActual = computed(() => meta.value?.current_page ?? 1)

const paginas = computed(() => {
  const total = totalPaginas.value
  const actual = paginaActual.value
  const ventana = []
  const inicio = Math.max(1, actual - 2)
  const fin = Math.min(total, actual + 2)
  for (let i = inicio; i <= fin; i++) ventana.push(i)
  return ventana
})

// Si la URL cambia (por ejemplo, click en el navbar al buscar), recargar.
watch(
  () => route.query,
  () => {
    leerFiltrosDeRuta()
    cargarProductos()
  },
)

onMounted(() => {
  leerFiltrosDeRuta()
  cargarCategorias()
  cargarProductos()
})
</script>

<template>
  <div class="grid grid-cols-1 gap-8 py-4 lg:grid-cols-[260px_1fr]">
    <!-- Sidebar de filtros (escritorio) -->
    <aside class="hidden lg:block">
      <div class="sticky top-20 space-y-6">
        <div>
          <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Categorías</h3>
          <ul class="space-y-1 text-sm">
            <li>
              <button
                type="button"
                class="w-full rounded-md px-2 py-1.5 text-left transition-colors"
                :class="filtros.category === ''
                  ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                  : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                @click="aplicarFiltro({ category: '' })"
              >
                Todas
              </button>
            </li>
            <li v-for="cat in categorias" :key="cat.id">
              <button
                type="button"
                class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left transition-colors"
                :class="filtros.category === cat.slug
                  ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                  : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                @click="aplicarFiltro({ category: cat.slug })"
              >
                <span>{{ cat.name }}</span>
              </button>
              <ul v-if="cat.children?.length" class="ml-3 mt-1 space-y-1 border-l border-slate-200 pl-3 dark:border-slate-700">
                <li v-for="sub in cat.children" :key="sub.id">
                  <button
                    type="button"
                    class="w-full rounded-md px-2 py-1 text-left text-xs transition-colors"
                    :class="filtros.category === sub.slug
                      ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                      : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
                    @click="aplicarFiltro({ category: sub.slug })"
                  >
                    {{ sub.name }}
                  </button>
                </li>
              </ul>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Precio</h3>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="label" for="precio-min">Mínimo</label>
              <input
                id="precio-min"
                v-model="filtros.min_price"
                type="number"
                min="0"
                step="0.01"
                placeholder="0"
                class="input"
              />
            </div>
            <div>
              <label class="label" for="precio-max">Máximo</label>
              <input
                id="precio-max"
                v-model="filtros.max_price"
                type="number"
                min="0"
                step="0.01"
                placeholder="∞"
                class="input"
              />
            </div>
          </div>
          <button
            type="button"
            class="btn-secondary mt-3 w-full"
            @click="aplicarFiltro({})"
          >
            Aplicar precio
          </button>
        </div>

        <div>
          <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Ofertas</h3>
          <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white p-3 text-sm hover:border-red-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-red-700">
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500"
              :checked="filtros.offers"
              @change="aplicarFiltro({ offers: $event.target.checked })"
            />
            <span class="font-medium text-slate-700 dark:text-slate-200">Solo productos en oferta</span>
          </label>
        </div>

        <div>
          <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Ordenar por</h3>
          <select
            v-model="filtros.sort"
            class="input"
            @change="aplicarFiltro({ sort: filtros.sort })"
          >
            <option value="newest">Más recientes</option>
            <option value="price_asc">Precio: menor a mayor</option>
            <option value="price_desc">Precio: mayor a menor</option>
            <option value="rating">Mejor valorados</option>
            <option value="discount">Mayor descuento</option>
          </select>
        </div>

        <button
          type="button"
          class="btn-ghost w-full justify-center"
          @click="limpiarFiltros"
        >
          Limpiar filtros
        </button>
      </div>
    </aside>

    <!-- Listado -->
    <div>
      <!-- Tabs por tipo de producto -->
      <div class="mb-6">
        <div
          class="grid grid-cols-1 gap-2 sm:grid-cols-3"
          role="tablist"
          aria-label="Tipo de producto"
        >
          <button
            v-for="tab in tabs"
            :key="tab.valor"
            type="button"
            role="tab"
            :aria-selected="filtros.type === tab.valor"
            class="group flex flex-col rounded-xl border-2 px-4 py-3 text-left transition-colors"
            :class="filtros.type === tab.valor
              ? 'border-primary-600 bg-primary-50 text-primary-900 shadow-sm dark:border-primary-400 dark:bg-primary-900/30 dark:text-primary-100'
              : 'border-slate-200 bg-white text-slate-700 hover:border-primary-300 hover:bg-primary-50/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-primary-700 dark:hover:bg-slate-800'"
            @click="cambiarTipo(tab.valor)"
          >
            <span class="text-sm font-semibold">{{ tab.etiqueta }}</span>
            <span class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
              {{ tab.descripcion }}
            </span>
          </button>
        </div>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
          Mostrando: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ tabActual.etiqueta }}</span>
        </p>
      </div>

      <div class="mb-6 flex flex-wrap items-center gap-3">
        <div class="flex-1">
          <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" /></svg>
            </span>
            <input
              :value="filtros.search"
              type="search"
              placeholder="Buscar productos..."
              class="input pl-9"
              aria-label="Buscar productos"
              @input="alCambiarBuscador"
            />
          </div>
        </div>

        <button
          type="button"
          class="btn-secondary lg:hidden"
          @click="filtrosMovilAbierto = true"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12M10 20h4" /></svg>
          Filtros
        </button>

        <p class="ml-auto text-sm text-slate-500 dark:text-slate-400">
          {{ meta.total ?? 0 }} resultado(s)
        </p>
      </div>

      <div v-if="cargando" class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4">
        <ProductCardSkeleton v-for="n in 8" :key="n" />
      </div>

      <EstadoVacio
        v-else-if="productoStore.error"
        icono="error"
        titulo="No se pudieron cargar los productos"
        :descripcion="productoStore.error"
      >
        <button class="btn-primary" @click="cargarProductos">Reintentar</button>
      </EstadoVacio>

      <EstadoVacio
        v-else-if="productos.length === 0"
        icono="search"
        titulo="No se encontraron productos"
        descripcion="Prueba a cambiar los filtros o el término de búsqueda."
      >
        <button class="btn-secondary" @click="limpiarFiltros">Limpiar filtros</button>
      </EstadoVacio>

      <div v-else class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4">
        <TarjetaProducto v-for="p in productos" :key="p.id" :product="p" />
      </div>

      <!-- Paginación -->
      <nav
        v-if="!cargando && totalPaginas > 1"
        class="mt-10 flex items-center justify-center gap-1"
        aria-label="Paginación"
      >
        <button
          type="button"
          class="btn-ghost"
          :disabled="paginaActual === 1"
          @click="cambiarPagina(paginaActual - 1)"
        >
          ← Anterior
        </button>
        <button
          v-for="p in paginas"
          :key="p"
          type="button"
          class="rounded-md px-3 py-2 text-sm font-medium transition-colors"
          :class="p === paginaActual
            ? 'bg-primary-600 text-white'
            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
          @click="cambiarPagina(p)"
        >
          {{ p }}
        </button>
        <button
          type="button"
          class="btn-ghost"
          :disabled="paginaActual === totalPaginas"
          @click="cambiarPagina(paginaActual + 1)"
        >
          Siguiente →
        </button>
      </nav>
    </div>

    <!-- Drawer de filtros (móvil) -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="filtrosMovilAbierto"
        class="fixed inset-0 z-50 bg-black/40 lg:hidden"
        @click="filtrosMovilAbierto = false"
      ></div>
    </Transition>
    <Transition
      enter-active-class="transition-transform duration-200"
      enter-from-class="translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-200"
      leave-from-class="translate-x-0"
      leave-to-class="translate-x-full"
    >
      <aside
        v-if="filtrosMovilAbierto"
        class="fixed inset-y-0 right-0 z-50 w-80 max-w-[90vw] overflow-y-auto bg-white p-6 shadow-xl dark:bg-slate-900 lg:hidden"
        role="dialog"
        aria-label="Filtros"
      >
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold">Filtros</h2>
          <button class="btn-ghost" @click="filtrosMovilAbierto = false" aria-label="Cerrar">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <div class="space-y-6">
          <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Categorías</h3>
            <ul class="space-y-1 text-sm">
              <li>
                <button
                  type="button"
                  class="w-full rounded-md px-2 py-1.5 text-left"
                  :class="filtros.category === '' ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : ''"
                  @click="aplicarFiltro({ category: '' }); filtrosMovilAbierto = false"
                >
                  Todas
                </button>
              </li>
              <li v-for="cat in categorias" :key="cat.id">
                <button
                  type="button"
                  class="w-full rounded-md px-2 py-1.5 text-left"
                  :class="filtros.category === cat.slug ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : ''"
                  @click="aplicarFiltro({ category: cat.slug }); filtrosMovilAbierto = false"
                >
                  {{ cat.name }}
                </button>
              </li>
            </ul>
          </div>

          <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Precio</h3>
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model="filtros.min_price"
                type="number"
                min="0"
                step="0.01"
                placeholder="Mínimo"
                class="input"
              />
              <input
                v-model="filtros.max_price"
                type="number"
                min="0"
                step="0.01"
                placeholder="Máximo"
                class="input"
              />
            </div>
            <button class="btn-secondary mt-3 w-full" @click="aplicarFiltro({}); filtrosMovilAbierto = false">
              Aplicar precio
            </button>
          </div>

          <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Ofertas</h3>
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-900">
              <input
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500"
                :checked="filtros.offers"
                @change="aplicarFiltro({ offers: $event.target.checked }); filtrosMovilAbierto = false"
              />
              <span class="font-medium text-slate-700 dark:text-slate-200">Solo productos en oferta</span>
            </label>
          </div>

          <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Ordenar por</h3>
            <select
              v-model="filtros.sort"
              class="input"
              @change="aplicarFiltro({ sort: filtros.sort }); filtrosMovilAbierto = false"
            >
              <option value="newest">Más recientes</option>
              <option value="price_asc">Precio: menor a mayor</option>
              <option value="price_desc">Precio: mayor a menor</option>
              <option value="rating">Mejor valorados</option>
              <option value="discount">Mayor descuento</option>
            </select>
          </div>

          <button class="btn-ghost w-full" @click="limpiarFiltros(); filtrosMovilAbierto = false">
            Limpiar filtros
          </button>
        </div>
      </aside>
    </Transition>
  </div>
</template>
