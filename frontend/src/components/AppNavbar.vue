<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useCarritoStore } from '@/stores/carrito'
import { useTema } from '@/composables/useTema'
import logoHorizontal from '@/assets/img/logo-NovaCommerce-horizontal.png'

const route = useRoute()
const router = useRouter()
const auth = useAutenticacionStore()
const carrito = useCarritoStore()
const { tema, alternar } = useTema()

const menuMovil = ref(false)
const menuUsuario = ref(false)
const buscadorMovil = ref(false)
const consulta = ref('')
const inputBuscadorMovil = ref(null)

const cantidadCarrito = computed(() => carrito.cantidad)

const esPremium = computed(() => Boolean(auth.usuario?.premium_active))

const nombreUsuario = computed(() => {
  if (!auth.estaAutenticado) return ''
  return auth.usuario?.company_name || auth.usuario?.name || ''
})

const inicialUsuario = computed(() => {
  const valor = nombreUsuario.value
  return valor ? valor.charAt(0).toUpperCase() : '?'
})

// Enlaces principales de navegación: siempre visibles, sin mezclar con sesión.
const enlacesNavegacion = [
  { texto: 'Inicio', ruta: '/' },
  { texto: 'Catálogo', ruta: '/catalogo' },
  { texto: 'Contacto', ruta: '/contacto' },
]

// Opciones del dropdown de usuario según el rol. El admin no es vendedor, por
// eso no ve "Vender un producto" ni "Mi empresa".
const opcionesUsuario = computed(() => {
  const opciones = [
    { texto: 'Mi cuenta', ruta: '/mi-cuenta' },
    { texto: 'Mis pedidos', ruta: '/mi-cuenta/pedidos' },
  ]
  if (auth.esAdmin) {
    opciones.push({ texto: 'Administración', ruta: '/admin' })
  } else if (auth.esEmpresa) {
    opciones.push({ texto: 'Mi empresa', ruta: '/empresa' })
  } else if (auth.esUsuario) {
    opciones.push({ texto: 'Vender un producto', ruta: '/mis-productos' })
  }
  return opciones
})

function buscar() {
  const termino = consulta.value.trim()
  if (!termino) return
  router.push({ path: '/catalogo', query: { search: termino } })
  menuMovil.value = false
  buscadorMovil.value = false
}

async function cerrarSesion() {
  menuUsuario.value = false
  menuMovil.value = false
  await auth.cerrarSesion()
  router.push('/')
}

function activo(ruta) {
  if (ruta === '/') return route.path === '/'
  return route.path === ruta || route.path.startsWith(ruta + '/')
}

// Al desplegar el buscador móvil enfocamos el input automáticamente.
watch(buscadorMovil, async (abierto) => {
  if (abierto) {
    await nextTick()
    inputBuscadorMovil.value?.focus()
  }
})
</script>

<template>
  <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
      <!-- Logo -->
      <RouterLink to="/" class="flex shrink-0 items-center" aria-label="Inicio NovaCommerce">
        <img
          :src="logoHorizontal"
          alt="NovaCommerce"
          class="h-9 w-auto dark:brightness-0 dark:invert"
        />
      </RouterLink>

      <!-- Enlaces de navegación (desktop) -->
      <nav class="hidden items-center gap-1 lg:flex">
        <RouterLink
          v-for="enlace in enlacesNavegacion"
          :key="enlace.ruta"
          :to="enlace.ruta"
          :class="[
            'whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-colors',
            activo(enlace.ruta)
              ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
          ]"
        >
          {{ enlace.texto }}
        </RouterLink>
      </nav>

      <!-- Buscador (desktop) -->
      <form
        class="hidden lg:ml-auto lg:block lg:w-[260px] xl:w-[300px]"
        role="search"
        @submit.prevent="buscar"
      >
        <label class="sr-only" for="navbar-buscador">Buscar productos</label>
        <div class="relative">
          <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
          </span>
          <input
            id="navbar-buscador"
            v-model="consulta"
            type="search"
            placeholder="Buscar productos..."
            class="block w-full rounded-full border border-transparent bg-slate-100 py-2 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-500 transition-colors focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-200 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-400 dark:focus:bg-slate-900 dark:focus:ring-primary-900/50"
          />
        </div>
      </form>

      <!-- Zona derecha -->
      <div class="ml-auto flex items-center gap-2 lg:ml-0">
        <!-- Buscador móvil (toggle) -->
        <button
          type="button"
          class="grid h-10 w-10 place-items-center rounded-full text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
          aria-label="Buscar productos"
          :aria-expanded="buscadorMovil"
          @click="buscadorMovil = !buscadorMovil"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
        </button>

        <!-- Tema oscuro (solo desktop; en móvil va dentro del menú) -->
        <button
          type="button"
          class="hidden h-10 w-10 place-items-center rounded-full text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:grid"
          :aria-label="tema === 'oscuro' ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
          @click="alternar"
        >
          <svg v-if="tema === 'oscuro'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36-6.36l-1.41 1.41M7.05 16.95l-1.41 1.41m0-12.72l1.41 1.41m9.9 9.9l1.41 1.41M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3a7 7 0 109.79 9.79z"/></svg>
        </button>

        <!-- No autenticado: botones de sesión (desktop) -->
        <template v-if="!auth.estaAutenticado">
          <RouterLink
            to="/login"
            class="hidden whitespace-nowrap rounded-lg border border-primary-600 px-4 py-2 text-sm font-semibold text-primary-700 transition-colors hover:bg-primary-50 dark:border-primary-500 dark:text-primary-300 dark:hover:bg-primary-900/30 lg:inline-flex"
          >
            Iniciar sesión
          </RouterLink>
          <RouterLink
            to="/registro"
            class="hidden whitespace-nowrap rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700 lg:inline-flex"
          >
            Registrarse
          </RouterLink>
        </template>

        <!-- Autenticado: carrito + dropdown usuario -->
        <template v-else>
          <!-- Carrito: visible en móvil y desktop -->
          <RouterLink
            to="/carrito"
            class="relative grid h-10 w-10 place-items-center rounded-full text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
            aria-label="Ver carrito"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293A1 1 0 005.414 17H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span
              v-if="cantidadCarrito > 0"
              class="absolute -right-0.5 -top-0.5 grid h-4 min-w-[1rem] place-items-center rounded-full bg-primary-600 px-1 text-[0.6rem] font-bold text-white"
              aria-label="Productos en el carrito"
            >
              {{ cantidadCarrito }}
            </span>
          </RouterLink>

          <!-- Dropdown usuario (solo desktop) -->
          <div class="relative hidden lg:block">
            <button
              type="button"
              class="flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              aria-haspopup="menu"
              :aria-expanded="menuUsuario"
              @click="menuUsuario = !menuUsuario"
            >
              <span class="relative grid h-7 w-7 place-items-center rounded-full bg-primary-600 text-xs font-bold text-white">
                {{ inicialUsuario }}
                <span
                  v-if="esPremium"
                  class="absolute -bottom-1 -right-1 grid h-4 w-4 place-items-center rounded-full bg-amber-400 text-[0.55rem] text-amber-900 ring-2 ring-white dark:ring-slate-800"
                  aria-label="Suscriptor Premium"
                  title="Suscriptor Premium"
                >
                  <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
                </span>
              </span>
              <span class="hidden max-w-[140px] truncate xl:inline">{{ nombreUsuario }}</span>
              <span
                v-if="esPremium"
                class="hidden rounded-full bg-amber-100 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 xl:inline"
              >
                Premium
              </span>
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <Transition
              enter-active-class="transition duration-150"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition duration-150"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div
                v-if="menuUsuario"
                class="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
                role="menu"
                @click="menuUsuario = false"
              >
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                  <p class="flex items-center gap-2 truncate text-sm font-medium text-slate-900 dark:text-white">
                    {{ nombreUsuario }}
                    <span
                      v-if="esPremium"
                      class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.6rem] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                    >
                      Premium
                    </span>
                  </p>
                  <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth.usuario?.email }}</p>
                </div>
                <RouterLink
                  v-for="o in opcionesUsuario"
                  :key="o.ruta"
                  :to="o.ruta"
                  class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  {{ o.texto }}
                </RouterLink>
                <button
                  type="button"
                  class="block w-full border-t border-slate-200 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:border-slate-700 dark:text-red-400 dark:hover:bg-red-900/20"
                  @click="cerrarSesion"
                >
                  Cerrar sesión
                </button>
              </div>
            </Transition>
          </div>
        </template>

        <!-- Hamburguesa móvil -->
        <button
          type="button"
          class="grid h-10 w-10 place-items-center rounded-full text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
          aria-label="Abrir menú"
          :aria-expanded="menuMovil"
          @click="menuMovil = !menuMovil"
        >
          <svg v-if="!menuMovil" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <!-- Buscador desplegable (móvil) -->
    <Transition
      enter-active-class="transition duration-150"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <form
        v-if="buscadorMovil"
        class="border-t border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900 lg:hidden"
        role="search"
        @submit.prevent="buscar"
      >
        <label class="sr-only" for="navbar-buscador-movil">Buscar productos</label>
        <div class="relative">
          <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
          </span>
          <input
            id="navbar-buscador-movil"
            ref="inputBuscadorMovil"
            v-model="consulta"
            type="search"
            placeholder="Buscar productos..."
            class="block w-full rounded-full border border-transparent bg-slate-100 py-2 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-500 transition-colors focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-200 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-400 dark:focus:bg-slate-900 dark:focus:ring-primary-900/50"
          />
        </div>
      </form>
    </Transition>

    <!-- Menú móvil -->
    <Transition
      enter-active-class="transition duration-150"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-if="menuMovil"
        class="border-t border-slate-200 bg-white px-4 py-4 dark:border-slate-800 dark:bg-slate-900 lg:hidden"
      >
        <!-- Identidad del usuario en móvil -->
        <div
          v-if="auth.estaAutenticado"
          class="mb-3 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/60"
        >
          <span class="relative grid h-9 w-9 place-items-center rounded-full bg-primary-600 text-sm font-bold text-white">
            {{ inicialUsuario }}
            <span
              v-if="esPremium"
              class="absolute -bottom-1 -right-1 grid h-4 w-4 place-items-center rounded-full bg-amber-400 text-amber-900 ring-2 ring-slate-50 dark:ring-slate-800"
              aria-label="Suscriptor Premium"
            >
              <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
            </span>
          </span>
          <div class="min-w-0 flex-1">
            <p class="flex items-center gap-2 truncate text-sm font-medium text-slate-900 dark:text-white">
              {{ nombreUsuario }}
              <span
                v-if="esPremium"
                class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.6rem] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
              >
                Premium
              </span>
            </p>
            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth.usuario?.email }}</p>
          </div>
        </div>

        <!-- Enlaces de navegación -->
        <nav class="flex flex-col gap-1">
          <RouterLink
            v-for="enlace in enlacesNavegacion"
            :key="enlace.ruta"
            :to="enlace.ruta"
            :class="[
              'rounded-md px-3 py-2 text-sm font-medium',
              activo(enlace.ruta)
                ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800',
            ]"
            @click="menuMovil = false"
          >
            {{ enlace.texto }}
          </RouterLink>

          <!-- Opciones del usuario autenticado -->
          <template v-if="auth.estaAutenticado">
            <RouterLink
              v-for="o in opcionesUsuario"
              :key="o.ruta"
              :to="o.ruta"
              :class="[
                'rounded-md px-3 py-2 text-sm font-medium',
                activo(o.ruta)
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                  : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800',
              ]"
              @click="menuMovil = false"
            >
              {{ o.texto }}
            </RouterLink>
          </template>
        </nav>

        <!-- Tema oscuro en móvil -->
        <button
          type="button"
          class="mt-2 flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
          @click="alternar"
        >
          <svg v-if="tema === 'oscuro'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36-6.36l-1.41 1.41M7.05 16.95l-1.41 1.41m0-12.72l1.41 1.41m9.9 9.9l1.41 1.41M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3a7 7 0 109.79 9.79z"/></svg>
          {{ tema === 'oscuro' ? 'Tema claro' : 'Tema oscuro' }}
        </button>

        <!-- Acciones de sesión -->
        <div class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-700">
          <template v-if="auth.estaAutenticado">
            <button
              type="button"
              class="block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
              @click="cerrarSesion"
            >
              Cerrar sesión
            </button>
          </template>
          <template v-else>
            <RouterLink
              to="/login"
              class="block rounded-md px-3 py-2 text-sm font-medium text-primary-700 hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-900/30"
              @click="menuMovil = false"
            >
              Iniciar sesión
            </RouterLink>
            <RouterLink
              to="/registro"
              class="block rounded-md px-3 py-2 text-sm font-medium text-primary-700 hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-900/30"
              @click="menuMovil = false"
            >
              Registrarse
            </RouterLink>
          </template>
        </div>
      </div>
    </Transition>
  </header>
</template>
