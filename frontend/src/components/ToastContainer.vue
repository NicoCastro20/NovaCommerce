<script setup>
import { useNotificacionesStore } from '@/stores/notificaciones'

const toasts = useNotificacionesStore()

const estilos = {
  success: 'bg-emerald-50 border-emerald-300 text-emerald-900 dark:bg-emerald-900/40 dark:border-emerald-700 dark:text-emerald-100',
  error:   'bg-red-50 border-red-300 text-red-900 dark:bg-red-900/40 dark:border-red-700 dark:text-red-100',
  info:    'bg-primary-50 border-primary-300 text-primary-900 dark:bg-primary-900/40 dark:border-primary-700 dark:text-primary-100',
  warning: 'bg-amber-50 border-amber-300 text-amber-900 dark:bg-amber-900/40 dark:border-amber-700 dark:text-amber-100',
}

function clases(tipo) {
  return estilos[tipo] ?? estilos.info
}
</script>

<template>
  <Teleport to="body">
    <div
      class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4 sm:items-end sm:right-4 sm:left-auto sm:px-0"
      role="region"
      aria-label="Notificaciones"
    >
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts.toasts"
          :key="t.id"
          class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 shadow-lg backdrop-blur"
          :class="clases(t.tipo)"
          role="status"
        >
          <span class="mt-0.5">
            <svg v-if="t.tipo === 'success'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <svg v-else-if="t.tipo === 'error'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M4.93 19h14.14a2 2 0 001.74-3l-7.07-12a2 2 0 00-3.48 0L3.2 16a2 2 0 001.73 3z"/></svg>
            <svg v-else-if="t.tipo === 'warning'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M4.93 19h14.14a2 2 0 001.74-3l-7.07-12a2 2 0 00-3.48 0L3.2 16a2 2 0 001.73 3z"/></svg>
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </span>
          <p class="flex-1 text-sm font-medium">{{ t.mensaje }}</p>
          <button
            type="button"
            class="text-xs opacity-70 transition-opacity hover:opacity-100"
            aria-label="Cerrar notificación"
            @click="toasts.dismiss(t.id)"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
