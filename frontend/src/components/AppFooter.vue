<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAutenticacionStore } from '@/stores/autenticacion'
import logoHorizontal from '@/assets/img/logo-NovaCommerce-horizontal.png'

const auth = useAutenticacionStore()
const anio = new Date().getFullYear()

// Mismo criterio que la navbar: solo aparecen los enlaces relevantes al
// rol actual. Sin sesión: solo páginas públicas + login/registro.
const enlaces = computed(() => {
  const lista = [
    { texto: 'Inicio', ruta: '/' },
    { texto: 'Catálogo', ruta: '/catalogo' },
    { texto: 'Premium', ruta: '/premium' },
    { texto: 'Contacto', ruta: '/contacto' },
  ]

  if (!auth.estaAutenticado) {
    lista.push({ texto: 'Iniciar sesión', ruta: '/login' })
    lista.push({ texto: 'Crear cuenta', ruta: '/registro' })
    return lista
  }

  lista.push({ texto: 'Carrito', ruta: '/carrito' })
  lista.push({ texto: 'Mi cuenta', ruta: '/mi-cuenta' })

  // Cada rol ve solo el panel que le corresponde — el admin no es vendedor.
  if (auth.esAdmin) {
    lista.push({ texto: 'Administración', ruta: '/admin' })
  } else if (auth.esEmpresa) {
    lista.push({ texto: 'Mi empresa', ruta: '/empresa' })
  } else if (auth.esUsuario) {
    lista.push({ texto: 'Vender un producto', ruta: '/mis-productos' })
  }

  return lista
})
</script>

<template>
  <footer class="mt-24 border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-4 lg:px-8">
      <!-- Marca -->
      <div>
        <img
          :src="logoHorizontal"
          alt="NovaCommerce"
          class="h-12 w-auto dark:brightness-0 dark:invert"
        />
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
          Tu marketplace de confianza. Productos seleccionados, vendedores verificados y envíos a toda España.
        </p>
      </div>

      <!-- Enlaces rápidos -->
      <div>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Enlaces rápidos</h3>
        <ul class="space-y-2 text-sm">
          <li v-for="enlace in enlaces" :key="enlace.ruta">
            <RouterLink :to="enlace.ruta" class="hover:text-primary-600 dark:hover:text-primary-400">
              {{ enlace.texto }}
            </RouterLink>
          </li>
        </ul>
      </div>

      <!-- Atención al cliente -->
      <div>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Atención al cliente</h3>
        <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
          <li>Lun – Vie: 9:00 – 18:00</li>
          <li>soporte@novacommerce.com</li>
          <li>+34 900 000 000</li>
        </ul>
      </div>

      <!-- Redes -->
      <div>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Síguenos</h3>
        <div class="flex gap-3">
          <a href="#" aria-label="Twitter" class="btn-ghost">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 5.92a8.2 8.2 0 01-2.36.64A4.11 4.11 0 0021.45 4.3a8.27 8.27 0 01-2.6 1A4.1 4.1 0 0011.84 9a11.65 11.65 0 01-8.46-4.29 4.11 4.11 0 001.27 5.48 4.07 4.07 0 01-1.86-.51v.05a4.11 4.11 0 003.29 4.03 4.13 4.13 0 01-1.85.07 4.11 4.11 0 003.83 2.85A8.24 8.24 0 012 18.4a11.62 11.62 0 006.29 1.84c7.55 0 11.68-6.25 11.68-11.67l-.01-.53A8.32 8.32 0 0022 5.92z"/></svg>
          </a>
          <a href="#" aria-label="Instagram" class="btn-ghost">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zM17.5 6.5h.01"/></svg>
          </a>
          <a href="#" aria-label="Facebook" class="btn-ghost">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12a10 10 0 10-11.56 9.88v-7H7.9V12h2.54V9.79c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.88h-2.34v7A10 10 0 0022 12z"/></svg>
          </a>
        </div>
      </div>
    </div>

    <div class="border-t border-slate-200 py-4 text-center text-xs text-slate-500 dark:border-slate-800 dark:text-slate-500">
      © {{ anio }} NovaCommerce. Todos los derechos reservados.
    </div>
  </footer>
</template>
