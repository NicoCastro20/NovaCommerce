<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/api'
import EstrellasValoracion from '@/components/EstrellasValoracion.vue'
import TarjetaProducto from '@/components/TarjetaProducto.vue'
import ProductCardSkeleton from '@/components/ProductCardSkeleton.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useCarritoStore } from '@/stores/carrito'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { useEtiquetasMeta } from '@/composables/useEtiquetasMeta'

const route = useRoute()
const router = useRouter()
const auth = useAutenticacionStore()
const carrito = useCarritoStore()
const toast = useNotificacionesStore()

const producto = ref(null)
const cargando = ref(true)
const error = ref(null)

const imagenSeleccionada = ref(0)
const cantidad = ref(1)
const anadiendo = ref(false)

const relacionados = ref([])
const cargandoRelacionados = ref(false)

// Formulario de reseña
const formResena = ref({ rating: 5, comment: '' })
const enviandoResena = ref(false)
const erroresResena = ref({})

const imagenes = computed(() => {
  if (!producto.value) return []
  if (producto.value.images?.length) return producto.value.images
  if (producto.value.primary_image) return [{ url: producto.value.primary_image, id: 'p' }]
  return [{ url: 'https://placehold.co/800x800/eef2ff/64748b?text=NovaCommerce', id: 'ph' }]
})

const formatoEur = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })
const precio = computed(() =>
  producto.value ? formatoEur.format(Number(producto.value.price ?? 0)) : '',
)

const enOferta = computed(() => Boolean(producto.value?.is_on_offer))
const precioOriginalFmt = computed(() =>
  enOferta.value && producto.value?.original_price != null
    ? formatoEur.format(Number(producto.value.original_price))
    : null,
)
const ahorroFmt = computed(() => {
  if (!enOferta.value || producto.value?.original_price == null) return null
  const ahorro = Number(producto.value.original_price) - Number(producto.value.price)
  return ahorro > 0 ? formatoEur.format(ahorro) : null
})
const descuento = computed(() =>
  enOferta.value && producto.value?.discount_percentage != null
    ? Number(producto.value.discount_percentage)
    : null,
)
const etiquetaOferta = computed(() =>
  enOferta.value && producto.value?.offer_label ? String(producto.value.offer_label) : null,
)

const ahora = ref(Date.now())
let intervaloAhora = null

const diasParaFin = computed(() => {
  if (!enOferta.value || !producto.value?.offer_ends_at) return null
  const fin = new Date(producto.value.offer_ends_at).getTime()
  if (Number.isNaN(fin)) return null
  const diff = fin - ahora.value
  if (diff <= 0) return null
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
})

const countdownOferta = computed(() => {
  if (!enOferta.value || !producto.value?.offer_ends_at) return null
  const fin = new Date(producto.value.offer_ends_at).getTime()
  if (Number.isNaN(fin)) return null
  const diff = fin - ahora.value
  if (diff <= 0) return null
  const dias = Math.floor(diff / (1000 * 60 * 60 * 24))
  const horas = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
  const minutos = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
  if (dias > 0) return `${dias} día${dias === 1 ? '' : 's'} y ${horas} h`
  if (horas > 0) return `${horas} h ${minutos} min`
  return `${minutos} min`
})

const sinStock = computed(() => Number(producto.value?.stock ?? 0) <= 0)

useEtiquetasMeta(() => {
  if (!producto.value) return null
  const descripcion = (producto.value.description ?? '')
    .toString()
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 160)
  return {
    titulo: producto.value.name,
    descripcion: descripcion || `Compra ${producto.value.name} en NovaCommerce.`,
    imagen: producto.value.primary_image || producto.value.images?.[0]?.url,
  }
})

async function cargar(slug) {
  cargando.value = true
  error.value = null
  producto.value = null
  imagenSeleccionada.value = 0
  cantidad.value = 1
  try {
    const { data } = await api.get(`/products/${slug}`)
    producto.value = data?.data?.product ?? null
    if (producto.value?.category?.slug) {
      cargarRelacionados(producto.value.category.slug, producto.value.id)
    }
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudo cargar el producto.'
  } finally {
    cargando.value = false
  }
}

async function cargarRelacionados(slugCategoria, idProductoActual) {
  cargandoRelacionados.value = true
  try {
    const { data } = await api.get('/products', {
      params: { category: slugCategoria, per_page: 8, sort: 'newest' },
    })
    const lista = data?.data ?? []
    relacionados.value = lista
      .filter((p) => p.id !== idProductoActual)
      .slice(0, 4)
  } catch {
    relacionados.value = []
  } finally {
    cargandoRelacionados.value = false
  }
}

function ajustarCantidad(delta) {
  const stock = Number(producto.value?.stock ?? 0)
  const nueva = cantidad.value + delta
  if (nueva < 1) return
  if (nueva > stock) {
    toast.advertencia(`Solo hay ${stock} unidades disponibles.`)
    return
  }
  cantidad.value = nueva
}

async function anadirAlCarrito() {
  if (!auth.estaAutenticado) {
    toast.info('Inicia sesión para añadir productos al carrito.')
    router.push({ path: '/login', query: { redirect: route.fullPath } })
    return
  }

  if (sinStock.value) return

  anadiendo.value = true
  const ok = await carrito.agregarArticulo({
    productId: producto.value.id,
    quantity: cantidad.value,
  })
  anadiendo.value = false

  if (ok) {
    toast.exito(`Se añadieron ${cantidad.value} unidad(es) al carrito.`)
  } else {
    toast.error(carrito.error || 'No se pudo añadir el producto.')
  }
}

async function enviarResena() {
  erroresResena.value = {}
  if (!formResena.value.rating || formResena.value.rating < 1 || formResena.value.rating > 5) {
    erroresResena.value.rating = 'Selecciona una valoración entre 1 y 5 estrellas.'
    return
  }
  if (!formResena.value.comment.trim()) {
    erroresResena.value.comment = 'El comentario es obligatorio.'
    return
  }

  enviandoResena.value = true
  try {
    const { data } = await api.post(`/products/${producto.value.slug}/reviews`, {
      rating: formResena.value.rating,
      comment: formResena.value.comment.trim(),
    })
    const nueva = data?.data?.review
    if (nueva && producto.value) {
      const lista = [nueva, ...(producto.value.reviews ?? [])]
      producto.value.reviews = lista
      producto.value.reviews_count = lista.length
      const suma = lista.reduce((acc, r) => acc + Number(r.rating || 0), 0)
      producto.value.rating_average = suma / lista.length
    }
    formResena.value = { rating: 5, comment: '' }
    toast.exito('Reseña publicada correctamente.')
  } catch (err) {
    const status = err?.response?.status
    const mensaje = err?.response?.data?.message ?? 'No se pudo publicar la reseña.'
    if (status === 422 && err?.response?.data?.errors) {
      erroresResena.value = err.response.data.errors
    }
    toast.error(mensaje)
  } finally {
    enviandoResena.value = false
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

watch(() => route.params.slug, (nuevo) => {
  if (nuevo) cargar(nuevo)
})

onMounted(() => {
  if (route.params.slug) cargar(route.params.slug)
  intervaloAhora = setInterval(() => {
    ahora.value = Date.now()
  }, 60_000)
})

onBeforeUnmount(() => {
  if (intervaloAhora) {
    clearInterval(intervaloAhora)
    intervaloAhora = null
  }
})
</script>

<template>
  <section class="space-y-12 py-4">
    <!-- Cargando -->
    <div v-if="cargando" class="grid grid-cols-1 gap-8 lg:grid-cols-2">
      <div class="aspect-square animate-pulse rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
      <div class="space-y-4">
        <div class="h-8 w-3/4 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
        <div class="h-4 w-1/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
        <div class="h-20 w-full animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
        <div class="h-12 w-1/2 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
      </div>
    </div>

    <EstadoVacio
      v-else-if="error || !producto"
      icono="error"
      titulo="Producto no encontrado"
      :descripcion="error || 'No hemos podido encontrar este producto.'"
    >
      <RouterLink to="/catalogo" class="btn-primary">Ir al catálogo</RouterLink>
    </EstadoVacio>

    <template v-else>
      <!-- Cabecera del producto -->
      <div class="grid grid-cols-1 gap-10 lg:grid-cols-2">
        <!-- Galería -->
        <div>
          <div class="aspect-square overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <img
              :src="imagenes[imagenSeleccionada]?.url"
              :alt="producto.name"
              class="h-full w-full object-contain"
            />
          </div>
          <div v-if="imagenes.length > 1" class="mt-4 flex flex-wrap gap-3">
            <button
              v-for="(img, i) in imagenes"
              :key="img.id"
              type="button"
              :aria-label="`Ver imagen ${i + 1}`"
              class="h-20 w-20 overflow-hidden rounded-lg border-2 transition-colors"
              :class="i === imagenSeleccionada
                ? 'border-primary-500'
                : 'border-slate-200 hover:border-slate-400 dark:border-slate-700 dark:hover:border-slate-500'"
              @click="imagenSeleccionada = i"
            >
              <img :src="img.url" :alt="`Miniatura ${i + 1}`" class="h-full w-full object-cover" />
            </button>
          </div>
        </div>

        <!-- Información -->
        <div>
          <p
            v-if="producto.category?.name"
            class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400"
          >
            <RouterLink :to="{ name: 'catalogo', query: { category: producto.category.slug } }" class="hover:underline">
              {{ producto.category.name }}
            </RouterLink>
          </p>

          <div class="mt-1 flex flex-wrap items-start gap-3">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
              {{ producto.name }}
            </h1>
            <span
              v-if="enOferta && descuento !== null"
              class="inline-flex shrink-0 items-center rounded-lg bg-red-600 px-3 py-1 text-base font-extrabold tracking-wide text-white shadow"
            >
              −{{ descuento }}%
            </span>
          </div>

          <div class="mt-3 flex items-center gap-3">
            <EstrellasValoracion :rating="Number(producto.rating_average ?? 0)" />
            <span class="text-sm text-slate-500 dark:text-slate-400">
              {{ producto.reviews_count ?? 0 }} reseña(s)
            </span>
          </div>

          <div class="mt-6">
            <div v-if="enOferta && precioOriginalFmt" class="flex items-center gap-3">
              <span class="text-lg text-slate-400 line-through dark:text-slate-500">
                {{ precioOriginalFmt }}
              </span>
              <span class="inline-flex items-center rounded-md bg-red-100 px-2 py-0.5 text-xs font-bold uppercase text-red-700 dark:bg-red-900/40 dark:text-red-300">
                Antes
              </span>
            </div>
            <p
              class="text-4xl font-extrabold"
              :class="enOferta ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'"
            >
              {{ precio }}
            </p>
            <p v-if="enOferta && ahorroFmt" class="mt-1 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
              Te ahorras {{ ahorroFmt }}
            </p>
            <p
              v-if="enOferta && countdownOferta"
              class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span v-if="diasParaFin !== null && diasParaFin > 1">
                ¡La oferta termina en {{ diasParaFin }} días!
              </span>
              <span v-else>
                ¡Última oportunidad! Quedan {{ countdownOferta }}
              </span>
            </p>
          </div>

          <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ producto.description }}
          </p>

          <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-slate-200 pt-6 text-sm dark:border-slate-700">
            <div>
              <dt class="text-slate-500 dark:text-slate-400">Disponibilidad</dt>
              <dd
                class="mt-1 font-semibold"
                :class="sinStock ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'"
              >
                {{ sinStock ? 'Sin stock' : `${producto.stock} unidades` }}
              </dd>
            </div>
            <div v-if="producto.seller">
              <dt class="text-slate-500 dark:text-slate-400">Vendedor</dt>
              <dd class="mt-1 font-semibold text-slate-900 dark:text-white">
                {{ producto.seller.company_name || producto.seller.name }}
              </dd>
            </div>
          </dl>

          <!-- Selector de cantidad y CTA -->
          <div class="mt-8 flex flex-wrap items-center gap-4">
            <div class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700">
              <button
                type="button"
                class="px-3 py-2 text-lg font-bold text-slate-600 hover:bg-slate-100 disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
                :disabled="cantidad <= 1 || sinStock"
                aria-label="Disminuir cantidad"
                @click="ajustarCantidad(-1)"
              >−</button>
              <span class="min-w-[3rem] px-2 text-center text-sm font-semibold">{{ cantidad }}</span>
              <button
                type="button"
                class="px-3 py-2 text-lg font-bold text-slate-600 hover:bg-slate-100 disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
                :disabled="sinStock"
                aria-label="Aumentar cantidad"
                @click="ajustarCantidad(1)"
              >+</button>
            </div>

            <button
              type="button"
              class="btn-primary flex-1 !py-3 text-base sm:flex-none"
              :disabled="anadiendo || sinStock"
              @click="anadirAlCarrito"
            >
              <svg v-if="!anadiendo" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293A1 1 0 005.414 17H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              <svg v-else class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              {{ sinStock ? 'Sin stock' : 'Añadir al carrito' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Reseñas -->
      <div class="border-t border-slate-200 pt-10 dark:border-slate-700">
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Reseñas</h2>

        <div v-if="!producto.reviews?.length" class="mt-6">
          <EstadoVacio
            titulo="Aún no hay reseñas"
            descripcion="Sé el primero en valorar este producto cuando lo recibas."
          />
        </div>

        <ul v-else class="mt-6 space-y-4">
          <li v-for="r in producto.reviews" :key="r.id" class="card p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                  {{ r.user?.name ?? 'Usuario' }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ formatearFecha(r.created_at) }}</p>
              </div>
              <EstrellasValoracion :rating="Number(r.rating)" />
            </div>
            <p class="mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ r.comment }}</p>
          </li>
        </ul>

        <!-- Formulario de reseña -->
        <div class="mt-10">
          <div v-if="!auth.estaAutenticado" class="card p-6 text-sm text-slate-600 dark:text-slate-300">
            <RouterLink :to="{ path: '/login', query: { redirect: route.fullPath } }" class="text-primary-700 hover:underline dark:text-primary-400">
              Inicia sesión
            </RouterLink>
            para dejar una reseña. Solo puedes reseñar productos que hayas comprado y recibido.
          </div>

          <form v-else class="card space-y-4 p-6" @submit.prevent="enviarResena">
            <h3 class="text-lg font-semibold">Escribe tu reseña</h3>

            <div>
              <label class="label">Tu valoración</label>
              <EstrellasValoracion
                :model-value="formResena.rating"
                :interactive="true"
                size="lg"
                @update:model-value="formResena.rating = $event"
              />
              <p v-if="erroresResena.rating" class="form-error">
                {{ Array.isArray(erroresResena.rating) ? erroresResena.rating[0] : erroresResena.rating }}
              </p>
            </div>

            <div>
              <label for="comentario" class="label">Comentario</label>
              <textarea
                id="comentario"
                v-model="formResena.comment"
                rows="4"
                class="input"
                placeholder="Cuéntanos qué te ha parecido el producto..."
              ></textarea>
              <p v-if="erroresResena.comment" class="form-error">
                {{ Array.isArray(erroresResena.comment) ? erroresResena.comment[0] : erroresResena.comment }}
              </p>
            </div>

            <button type="submit" class="btn-primary" :disabled="enviandoResena">
              {{ enviandoResena ? 'Enviando...' : 'Publicar reseña' }}
            </button>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Solo puedes publicar una reseña por producto y necesitas haberlo comprado y recibido.
            </p>
          </form>
        </div>
      </div>

      <!-- Productos relacionados -->
      <div class="border-t border-slate-200 pt-10 dark:border-slate-700">
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Productos relacionados</h2>

        <div v-if="cargandoRelacionados" class="mt-6 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
          <ProductCardSkeleton v-for="n in 4" :key="n" />
        </div>

        <div v-else-if="relacionados.length === 0" class="mt-6">
          <EstadoVacio titulo="No hay productos relacionados" />
        </div>

        <div v-else class="mt-6 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
          <TarjetaProducto v-for="p in relacionados" :key="p.id" :product="p" />
        </div>
      </div>
    </template>
  </section>
</template>
