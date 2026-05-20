<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAutenticacionStore } from '@/stores/autenticacion'

const props = defineProps({
  tipo: { type: String, required: true }, // 'empresa' | 'usuario' | 'admin'
  titulo: { type: String, default: '' },
  descripcion: { type: String, default: '' },
})

const route = useRoute()
const auth = useAutenticacionStore()
const sidebarMovil = ref(false)

const enlacesEmpresa = [
  { ruta: '/empresa', etiqueta: 'Resumen', icono: 'home', exact: true },
  { ruta: '/empresa/productos', etiqueta: 'Mis productos', icono: 'box' },
  { ruta: '/empresa/pedidos', etiqueta: 'Pedidos recibidos', icono: 'orders' },
  { ruta: '/empresa/devoluciones', etiqueta: 'Devoluciones', icono: 'orders' },
]

const enlacesUsuario = [
  { ruta: '/mi-cuenta', etiqueta: 'Mi cuenta', icono: 'home', exact: true },
  { ruta: '/mi-cuenta/pedidos', etiqueta: 'Mis pedidos', icono: 'orders' },
  { ruta: '/mi-cuenta/devoluciones', etiqueta: 'Mis devoluciones', icono: 'orders' },
  { ruta: '/mi-cuenta/wishlist', etiqueta: 'Lista de deseos', icono: 'heart' },
  { ruta: '/mis-productos', etiqueta: 'Mis productos en venta', icono: 'box' },
]

const enlacesAdmin = [
  { ruta: '/admin', etiqueta: 'Resumen', icono: 'home', exact: true },
  { ruta: '/admin/productos', etiqueta: 'Productos', icono: 'box' },
  { ruta: '/admin/usuarios', etiqueta: 'Usuarios', icono: 'users' },
  { ruta: '/admin/pedidos', etiqueta: 'Pedidos', icono: 'orders' },
  { ruta: '/admin/devoluciones', etiqueta: 'Devoluciones', icono: 'orders' },
]

const enlaces = computed(() => {
  if (props.tipo === 'admin') return enlacesAdmin
  if (props.tipo === 'usuario') return enlacesUsuario
  return enlacesEmpresa
})

const tituloPanel = computed(() => {
  if (props.tipo === 'admin') return 'Panel administrador'
  if (props.tipo === 'usuario') return 'Mi cuenta'
  return 'Panel empresa'
})

const etiquetaSidebar = computed(() => {
  if (props.tipo === 'admin') return 'Administración'
  if (props.tipo === 'usuario') return 'Mi cuenta'
  return 'Empresa'
})

function activo(enlace) {
  if (enlace.exact) return route.path === enlace.ruta
  return route.path === enlace.ruta || route.path.startsWith(enlace.ruta + '/')
}
</script>

<template>
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-[240px_1fr]">
    <!-- Sidebar escritorio -->
    <aside class="hidden lg:block">
      <div class="sticky top-20">
        <div class="card overflow-hidden">
          <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
            <p class="text-[0.7rem] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              {{ etiquetaSidebar }}
            </p>
            <p class="text-sm font-semibold text-slate-900 dark:text-white">
              {{ auth.usuario?.company_name || auth.usuario?.name }}
            </p>
          </div>
          <nav class="flex flex-col py-2">
            <RouterLink
              v-for="enlace in enlaces"
              :key="enlace.ruta"
              :to="enlace.ruta"
              class="mx-2 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
              :class="activo(enlace)
                ? 'bg-primary-600 text-white'
                : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
            >
              <svg v-if="enlace.icono === 'home'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10"/></svg>
              <svg v-else-if="enlace.icono === 'box'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 0L4 7m8 4v10"/></svg>
              <svg v-else-if="enlace.icono === 'users'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 015-3.87m4-2a4 4 0 100-8 4 4 0 000 8zm6 0a4 4 0 100-8 4 4 0 000 8z"/></svg>
              <svg v-else-if="enlace.icono === 'heart'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364l-7.682-7.682a4.5 4.5 0 010-6.364z"/></svg>
              <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5h6a2 2 0 012 2v12l-5-3-5 3V7a2 2 0 012-2z"/></svg>
              {{ enlace.etiqueta }}
            </RouterLink>
          </nav>
          <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
            <RouterLink
              to="/"
              class="text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            >
              ← Volver a la tienda
            </RouterLink>
          </div>
        </div>
      </div>
    </aside>

    <!-- Contenido -->
    <div>
      <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-400">
            {{ tituloPanel }}
          </p>
          <h1 v-if="titulo" class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
            {{ titulo }}
          </h1>
          <p v-if="descripcion" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ descripcion }}
          </p>
        </div>

        <div v-if="$slots.acciones" class="flex flex-wrap gap-2">
          <slot name="acciones" />
        </div>

        <button
          type="button"
          class="btn-secondary lg:hidden"
          @click="sidebarMovil = true"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          Menú
        </button>
      </header>

      <slot />

      <!-- Sidebar drawer móvil -->
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="sidebarMovil"
          class="fixed inset-0 z-50 bg-black/40 lg:hidden"
          @click="sidebarMovil = false"
        ></div>
      </Transition>
      <Transition
        enter-active-class="transition-transform duration-200"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-200"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
      >
        <aside
          v-if="sidebarMovil"
          class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] overflow-y-auto bg-white p-4 shadow-xl dark:bg-slate-900 lg:hidden"
        >
          <div class="mb-3 flex items-center justify-between">
            <p class="text-sm font-semibold">{{ tituloPanel }}</p>
            <button class="btn-ghost" aria-label="Cerrar" @click="sidebarMovil = false">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <nav class="flex flex-col gap-1">
            <RouterLink
              v-for="enlace in enlaces"
              :key="enlace.ruta"
              :to="enlace.ruta"
              class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
              :class="activo(enlace)
                ? 'bg-primary-600 text-white'
                : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
              @click="sidebarMovil = false"
            >
              {{ enlace.etiqueta }}
            </RouterLink>
          </nav>
        </aside>
      </Transition>
    </div>
  </div>
</template>
