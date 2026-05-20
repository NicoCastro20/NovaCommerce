<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <section class="grid place-items-center py-16 text-center">
    <p class="text-6xl font-extrabold text-primary-600">404</p>
    <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
      Página no encontrada
    </h1>
    <p class="mt-2 max-w-md text-slate-600 dark:text-slate-400">
      Lo sentimos, no hemos podido encontrar la página que buscas. Puede que se haya movido o ya no exista.
    </p>
    <RouterLink to="/" class="btn-primary mt-6">Volver al inicio</RouterLink>
  </section>
</template>
