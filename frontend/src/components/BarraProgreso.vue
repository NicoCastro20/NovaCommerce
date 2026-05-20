<script setup>
import { useProgreso } from '@/composables/useProgreso'

const { progreso, visible } = useProgreso()
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-150"
    leave-active-class="transition-opacity duration-300"
    enter-from-class="opacity-0"
    leave-to-class="opacity-0"
  >
    <div
      v-if="visible"
      class="pointer-events-none fixed inset-x-0 top-0 z-[100] h-0.5 bg-transparent"
      role="progressbar"
      aria-label="Cargando"
      :aria-valuenow="Math.round(progreso)"
      aria-valuemin="0"
      aria-valuemax="100"
    >
      <div
        class="h-full bg-primary-600 shadow-[0_0_8px_2px_rgba(37,99,235,0.6)] transition-[width] duration-200 ease-out"
        :style="{ width: progreso + '%' }"
      ></div>
    </div>
  </Transition>
</template>
