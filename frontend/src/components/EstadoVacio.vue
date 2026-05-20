<script setup>
defineProps({
  titulo: { type: String, default: 'No hay datos' },
  descripcion: { type: String, default: '' },
  icono: { type: String, default: 'inbox' }, // inbox | search | error
})
</script>

<template>
  <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
    <div class="mb-4 grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
      <svg v-if="icono === 'search'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
      </svg>
      <svg v-else-if="icono === 'error'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <svg v-else class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0l-2 7a2 2 0 01-2 2H8a2 2 0 01-2-2l-2-7m16 0H4" />
      </svg>
    </div>
    <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ titulo }}</h3>
    <p v-if="descripcion" class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ descripcion }}</p>
    <div v-if="$slots.default" class="mt-4">
      <slot />
    </div>
  </div>
</template>
