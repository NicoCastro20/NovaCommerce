<script setup>
import { computed } from 'vue'

const props = defineProps({
  size: { type: String, default: 'md' }, // sm | md | lg
  texto: { type: String, default: '' },
  fullscreen: { type: Boolean, default: false },
})

const tamano = computed(() => ({
  sm: 'h-4 w-4 border-2',
  md: 'h-6 w-6 border-2',
  lg: 'h-10 w-10 border-4',
}[props.size] || 'h-6 w-6 border-2'))
</script>

<template>
  <div
    :class="fullscreen
      ? 'fixed inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-white/70 backdrop-blur dark:bg-slate-950/70'
      : 'inline-flex items-center gap-2'"
    role="status"
    aria-live="polite"
  >
    <span
      :class="[tamano, 'animate-spin rounded-full border-slate-300 border-t-primary-600 dark:border-slate-700 dark:border-t-primary-400']"
    ></span>
    <span v-if="texto" class="text-sm text-slate-600 dark:text-slate-300">{{ texto }}</span>
  </div>
</template>
