<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import EstrellasValoracion from '@/components/EstrellasValoracion.vue'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useCarritoStore } from '@/stores/carrito'
import { useNotificacionesStore } from '@/stores/notificaciones'

const props = defineProps({
  product: { type: Object, required: true },
})

const router = useRouter()
const auth = useAutenticacionStore()
const carrito = useCarritoStore()
const toast = useNotificacionesStore()

const anadiendo = ref(false)

const precio = computed(() =>
  new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(
    Number(props.product.price ?? 0),
  ),
)

const imagen = computed(
  () =>
    props.product.primary_image
      || props.product.images?.[0]?.url
      || 'https://placehold.co/600x600/eef2ff/64748b?text=NovaCommerce',
)

const sinStock = computed(() => Number(props.product.stock ?? 0) <= 0)
const ratingMedio = computed(() => Number(props.product.rating_average ?? 0))

const esSegundaMano = computed(() => props.product.type === 'segunda_mano')
const esEmpresa = computed(() => props.product.type === 'nuevo')

const vendedor = computed(() => props.product.seller ?? null)

// Para empresas mostramos el nombre comercial completo. Para particulares
// abreviamos el apellido (Juan Pérez → Juan P.) para preservar privacidad.
const nombreVendedor = computed(() => {
  const v = vendedor.value
  if (!v) return null

  if (esEmpresa.value) {
    return v.company_name || v.name || null
  }

  const nombre = (v.name || '').trim()
  if (!nombre) return null
  const partes = nombre.split(/\s+/)
  if (partes.length >= 2 && partes[1].length > 0) {
    return `${partes[0]} ${partes[1].charAt(0).toUpperCase()}.`
  }
  return partes[0]
})

const ESTADOS_SEGUNDA_MANO = {
  nuevo: {
    texto: 'Nuevo',
    clases: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  },
  como_nuevo: {
    texto: 'Como nuevo',
    clases: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  },
  buen_estado: {
    texto: 'Buen estado',
    clases: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  },
  usado: {
    texto: 'Usado',
    clases: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
  },
}

const estado = computed(() => {
  if (!esSegundaMano.value) return null
  return ESTADOS_SEGUNDA_MANO[props.product.condition] ?? null
})

async function anadirAlCarrito() {
  if (!auth.estaAutenticado) {
    toast.info('Inicia sesión para añadir productos al carrito.')
    router.push({ path: '/login', query: { redirect: router.currentRoute.value.fullPath } })
    return
  }

  if (sinStock.value) {
    toast.advertencia('Este producto no tiene stock disponible.')
    return
  }

  anadiendo.value = true
  const ok = await carrito.agregarArticulo({ productId: props.product.id, quantity: 1 })
  anadiendo.value = false

  if (ok) {
    toast.exito('Producto añadido al carrito.')
  } else {
    toast.error(carrito.error || 'No se pudo añadir el producto.')
  }
}
</script>

<template>
  <article class="card group flex h-full flex-col overflow-hidden transition-shadow hover:shadow-md">
    <RouterLink
      :to="{ name: 'producto', params: { slug: product.slug } }"
      class="relative block aspect-square overflow-hidden bg-slate-100 dark:bg-slate-800"
    >
      <img
        :src="imagen"
        :alt="product.name"
        loading="lazy"
        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
      />

      <!-- Badge de estado (solo segunda mano) -->
      <span
        v-if="estado"
        class="absolute left-2 top-2 rounded-full px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide shadow-sm"
        :class="estado.clases"
      >
        {{ estado.texto }}
      </span>
    </RouterLink>

    <div class="flex flex-1 flex-col p-4">
      <p
        v-if="product.category?.name"
        class="text-xs font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400"
      >
        {{ product.category.name }}
      </p>

      <RouterLink
        :to="{ name: 'producto', params: { slug: product.slug } }"
        class="mt-1 line-clamp-2 text-sm font-semibold text-slate-900 hover:text-primary-700 dark:text-white dark:hover:text-primary-400"
      >
        {{ product.name }}
      </RouterLink>

      <!-- Vendedor (empresa o particular) -->
      <p
        v-if="nombreVendedor"
        class="mt-1 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400"
      >
        <svg
          v-if="esEmpresa"
          class="h-3.5 w-3.5"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21V7l9-4 9 4v14M9 21V11h6v10M3 21h18" />
        </svg>
        <svg
          v-else
          class="h-3.5 w-3.5"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 14a4 4 0 10-8 0m12 7a8 8 0 10-16 0" />
        </svg>
        <span class="truncate">por {{ nombreVendedor }}</span>
      </p>

      <div class="mt-2 flex items-center gap-2">
        <EstrellasValoracion :rating="ratingMedio" size="sm" />
        <span
          v-if="ratingMedio > 0"
          class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.65rem] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
        >
          {{ ratingMedio.toFixed(1) }}
        </span>
        <span v-else class="text-xs text-slate-400">Sin valoraciones</span>
      </div>

      <div class="mt-auto flex items-end justify-between gap-2 pt-3">
        <span class="text-lg font-bold text-slate-900 dark:text-white">{{ precio }}</span>

        <button
          type="button"
          class="btn-primary !px-3 !py-1.5 text-xs"
          :disabled="anadiendo || sinStock"
          @click="anadirAlCarrito"
        >
          <svg
            v-if="!anadiendo"
            class="h-4 w-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293A1 1 0 005.414 17H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
            />
          </svg>
          <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            />
          </svg>
          {{ sinStock ? 'Sin stock' : 'Añadir' }}
        </button>
      </div>
    </div>
  </article>
</template>
