<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/api'
import TarjetaProducto from '@/components/TarjetaProducto.vue'
import ProductCardSkeleton from '@/components/ProductCardSkeleton.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import Revelar from '@/components/Revelar.vue'
import CarruselPrincipal from '@/components/CarruselPrincipal.vue'
import { useAutenticacionStore } from '@/stores/autenticacion'
import imgCarrusel1 from '@/assets/img/carrusel_novacommerce_01.jpeg'
import imgCarrusel2 from '@/assets/img/carrusel_novacommerce_02.jpeg'
import imgCarrusel3 from '@/assets/img/carrusel_novacommerce_03.jpeg'

const auth = useAutenticacionStore()

const slidesHero = computed(() => [
  {
    imagen: imgCarrusel2,
    titulo: 'Envíos gratis y sin límites',
    subtitulo: 'Únete a nuestro programa NovaCommerce Premium y olvídate de los gastos de envío en tus compras.',
    botonTexto: 'Descubre las ventajas',
    botonA: '/premium',
  },
  {
    imagen: imgCarrusel3,
    titulo: 'Selección de Ofertas',
    subtitulo: 'Encuentra descuentos increíbles en miles de artículos seleccionados. Aprovecha antes de que se agoten.',
    botonTexto: 'Ver todas las ofertas',
    botonA: '/catalogo?offers=true',
  },
  {
    imagen: imgCarrusel1,
    titulo: 'Dale una segunda vida a tus cosas',
    subtitulo: 'Compra y vende artículos con usuarios de toda la comunidad. Fácil y seguro.',
    botonTexto: 'Empieza a vender',
    botonA: auth.estaAutenticado ? '/mis-productos/nuevo' : '/registro',
  },
])

const empresas = ref([])
const cargandoEmpresas = ref(true)
const errorEmpresas = ref(null)

const segundaMano = ref([])
const cargandoSegundaMano = ref(true)
const errorSegundaMano = ref(null)

const ofertas = ref([])
const cargandoOfertas = ref(true)
const errorOfertas = ref(null)

async function cargarPorTipo(tipo, destino, errorRef, cargandoRef) {
  cargandoRef.value = true
  errorRef.value = null
  try {
    const { data } = await api.get('/products', {
      params: { type: tipo, sort: 'newest', per_page: 8 },
    })
    destino.value = data?.data ?? []
  } catch (err) {
    errorRef.value = err?.response?.data?.message ?? 'No se pudieron cargar los productos.'
  } finally {
    cargandoRef.value = false
  }
}

function cargarEmpresas() {
  return cargarPorTipo('nuevo', empresas, errorEmpresas, cargandoEmpresas)
}

function cargarSegundaMano() {
  return cargarPorTipo('segunda_mano', segundaMano, errorSegundaMano, cargandoSegundaMano)
}

async function cargarOfertas() {
  cargandoOfertas.value = true
  errorOfertas.value = null
  try {
    const { data } = await api.get('/products/offers', { params: { per_page: 8 } })
    ofertas.value = data?.data ?? []
  } catch (err) {
    errorOfertas.value = err?.response?.data?.message ?? 'No se pudieron cargar las ofertas.'
  } finally {
    cargandoOfertas.value = false
  }
}

const ventajas = [
  {
    titulo: 'Envío rápido',
    descripcion: 'Recibe tus pedidos en 24-48 horas en toda la península.',
    icono: 'truck',
  },
  {
    titulo: 'Pago seguro',
    descripcion: 'Pagos cifrados y protegidos con los mejores estándares.',
    icono: 'shield',
  },
  {
    titulo: 'Devoluciones fáciles',
    descripcion: 'Tienes 30 días para devolver lo que no te convenza.',
    icono: 'arrow',
  },
  {
    titulo: 'Valoraciones reales',
    descripcion: 'Comentarios verificados de clientes que sí compraron.',
    icono: 'star',
  },
]

const testimonios = [
  {
    nombre: 'Lucía M.',
    rol: 'Cliente desde 2024',
    texto: 'La experiencia de compra es muy fluida y el envío llegó antes de lo previsto. Repetiré sin duda.',
  },
  {
    nombre: 'Daniel R.',
    rol: 'Cliente desde 2025',
    texto: 'Encontré justo lo que buscaba a un precio inmejorable. El soporte respondió en minutos.',
  },
  {
    nombre: 'Marta G.',
    rol: 'Cliente desde 2023',
    texto: 'Me encanta la variedad y la calidad de los productos. Las valoraciones reales ayudan mucho.',
  },
]

onMounted(() => {
  cargarOfertas()
  cargarEmpresas()
  cargarSegundaMano()
})
</script>

<template>
  <div class="space-y-20 pb-12">
    <!-- Hero carrusel -->
    <section class="-mx-4 sm:-mx-6 lg:-mx-8">
      <CarruselPrincipal :slides="slidesHero" />
    </section>

    <!-- Ofertas destacadas -->
    <section
      v-if="cargandoOfertas || ofertas.length > 0 || errorOfertas"
      class="-mx-4 rounded-3xl bg-gradient-to-br from-red-50 via-white to-orange-50 px-4 py-12 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 dark:from-red-950/30 dark:via-slate-900 dark:to-orange-950/30"
    >
      <Revelar class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-red-700 dark:bg-red-900/40 dark:text-red-300">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293A1 1 0 005.414 17H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Tiempo limitado
          </span>
          <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
            No te pierdas estas ofertas
          </h2>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Descuentos seleccionados en productos de tiendas verificadas. ¡Aprovecha antes de que se agoten!
          </p>
        </div>
        <RouterLink
          to="/catalogo?offers=true"
          class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 dark:border-red-700 dark:bg-slate-900 dark:text-red-300 dark:hover:bg-slate-800"
        >
          Ver todas las ofertas →
        </RouterLink>
      </Revelar>

      <div v-if="cargandoOfertas" class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
        <ProductCardSkeleton v-for="n in 8" :key="n" />
      </div>

      <EstadoVacio
        v-else-if="errorOfertas"
        icono="error"
        titulo="No se pudieron cargar las ofertas"
        :descripcion="errorOfertas"
      >
        <button class="btn-primary" @click="cargarOfertas">Reintentar</button>
      </EstadoVacio>

      <div v-else class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
        <Revelar v-for="(p, i) in ofertas" :key="p.id" :delay="(i % 4) * 80">
          <TarjetaProducto :product="p" />
        </Revelar>
      </div>
    </section>

    <!-- Productos de tiendas -->
    <section>
      <Revelar class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21V7l9-4 9 4v14M9 21V11h6v10M3 21h18"/></svg>
            Tiendas verificadas
          </span>
          <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
            Productos de tiendas
          </h2>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Artículos nuevos de tiendas profesionales con garantía y envíos rápidos.
          </p>
        </div>
        <RouterLink
          to="/catalogo?type=nuevo"
          class="inline-flex items-center gap-1 rounded-lg border border-primary-200 bg-white px-4 py-2 text-sm font-semibold text-primary-700 transition-colors hover:bg-primary-50 dark:border-primary-700 dark:bg-slate-900 dark:text-primary-300 dark:hover:bg-slate-800"
        >
          Ver todo →
        </RouterLink>
      </Revelar>

      <div v-if="cargandoEmpresas" class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
        <ProductCardSkeleton v-for="n in 8" :key="n" />
      </div>

      <EstadoVacio
        v-else-if="errorEmpresas"
        icono="error"
        titulo="No se pudieron cargar los productos"
        :descripcion="errorEmpresas"
      >
        <button class="btn-primary" @click="cargarEmpresas">Reintentar</button>
      </EstadoVacio>

      <EstadoVacio
        v-else-if="empresas.length === 0"
        titulo="Aún no hay productos de tiendas"
        descripcion="Vuelve pronto para descubrir las novedades."
      />

      <div v-else class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
        <Revelar v-for="(p, i) in empresas" :key="p.id" :delay="(i % 4) * 80">
          <TarjetaProducto :product="p" />
        </Revelar>
      </div>
    </section>

    <!-- Productos de particulares (fondo diferenciado) -->
    <section class="-mx-4 rounded-3xl bg-gradient-to-br from-amber-50 via-white to-emerald-50 px-4 py-12 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 dark:from-slate-800 dark:via-slate-900 dark:to-slate-800">
      <Revelar class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M4 9a8 8 0 0114-3M20 15a8 8 0 01-14 3"/></svg>
            Entre particulares
          </span>
          <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
            Productos de particulares
          </h2>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Productos publicados por usuarios particulares: nuevos sin estrenar o de segunda mano en buen estado.
          </p>
        </div>
        <RouterLink
          to="/catalogo?type=segunda_mano"
          class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-50 dark:border-emerald-700 dark:bg-slate-900 dark:text-emerald-300 dark:hover:bg-slate-800"
        >
          Ver todo →
        </RouterLink>
      </Revelar>

      <div v-if="cargandoSegundaMano" class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
        <ProductCardSkeleton v-for="n in 8" :key="n" />
      </div>

      <EstadoVacio
        v-else-if="errorSegundaMano"
        icono="error"
        titulo="No se pudieron cargar los productos"
        :descripcion="errorSegundaMano"
      >
        <button class="btn-primary" @click="cargarSegundaMano">Reintentar</button>
      </EstadoVacio>

      <EstadoVacio
        v-else-if="segundaMano.length === 0"
        titulo="Aún no hay productos de particulares"
        descripcion="Cuando alguien publique algo, aparecerá aquí."
      />

      <div v-else class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
        <Revelar v-for="(p, i) in segundaMano" :key="p.id" :delay="(i % 4) * 80">
          <TarjetaProducto :product="p" />
        </Revelar>
      </div>
    </section>

    <!-- ¿Por qué comprar con nosotros? -->
    <section>
      <Revelar class="mb-10 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
          ¿Por qué comprar con nosotros?
        </h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
          Cuidamos cada detalle para que tu compra sea sencilla, segura y agradable.
        </p>
      </Revelar>

      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <Revelar
          v-for="(v, i) in ventajas"
          :key="v.titulo"
          :delay="i * 100"
        >
          <div class="card h-full p-6 text-center">
            <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
              <svg v-if="v.icono === 'truck'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h13v8H3V7zm13 3h4l1 3v2h-5v-5zM5 19a2 2 0 100-4 2 2 0 000 4zm12 0a2 2 0 100-4 2 2 0 000 4z" />
              </svg>
              <svg v-else-if="v.icono === 'shield'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
              <svg v-else-if="v.icono === 'arrow'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h13a5 5 0 010 10h-2M3 10l4-4M3 10l4 4" />
              </svg>
              <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 00-.363 1.118l1.286 3.957c.3.922-.755 1.688-1.54 1.118L10.49 15.347a1 1 0 00-1.176 0l-3.366 2.446c-.784.57-1.838-.196-1.539-1.118l1.286-3.957a1 1 0 00-.363-1.118L1.964 9.154c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.385-3.957z" />
              </svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ v.titulo }}</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ v.descripcion }}</p>
          </div>
        </Revelar>
      </div>
    </section>

    <!-- Testimonios -->
    <section>
      <Revelar class="mb-10 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
          Lo que dicen nuestros clientes
        </h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
          Miles de personas confían en NovaCommerce cada mes.
        </p>
      </Revelar>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <Revelar v-for="(t, i) in testimonios" :key="t.nombre" :delay="i * 120">
          <figure class="card flex h-full flex-col p-6">
            <svg class="h-7 w-7 text-primary-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M9 7H5a2 2 0 00-2 2v4a2 2 0 002 2h2v3a2 2 0 01-2 2H4v2h1a4 4 0 004-4V9a2 2 0 00-2-2h2zm10 0h-4a2 2 0 00-2 2v4a2 2 0 002 2h2v3a2 2 0 01-2 2h-1v2h1a4 4 0 004-4V9a2 2 0 00-2-2h2z" />
            </svg>
            <blockquote class="mt-4 flex-1 text-sm text-slate-700 dark:text-slate-300">
              «{{ t.texto }}»
            </blockquote>
            <figcaption class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
              <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ t.nombre }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ t.rol }}</p>
            </figcaption>
          </figure>
        </Revelar>
      </div>
    </section>
  </div>
</template>
