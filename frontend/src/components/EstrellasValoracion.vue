<script setup>
import { computed } from 'vue'

const props = defineProps({
  rating: { type: [Number, String, null], default: 0 },
  total: { type: Number, default: 5 },
  size: { type: String, default: 'sm' }, // sm | md | lg
  interactive: { type: Boolean, default: false },
  modelValue: { type: Number, default: 0 },
})

const emit = defineEmits(['update:modelValue'])

const valor = computed(() => {
  if (props.interactive) return Number(props.modelValue) || 0
  return Number(props.rating) || 0
})

const tamano = computed(() => ({
  sm: 'h-4 w-4',
  md: 'h-5 w-5',
  lg: 'h-6 w-6',
}[props.size] || 'h-4 w-4'))

function clic(n) {
  if (!props.interactive) return
  emit('update:modelValue', n)
}
</script>

<template>
  <div class="inline-flex items-center gap-0.5" :aria-label="`Valoración ${valor} de ${total}`">
    <button
      v-for="n in total"
      :key="n"
      type="button"
      :disabled="!interactive"
      :aria-label="`${n} estrellas`"
      :class="[
        'transition-transform',
        interactive ? 'cursor-pointer hover:scale-110' : 'cursor-default',
      ]"
      @click="clic(n)"
    >
      <svg
        :class="[tamano, n <= Math.round(valor) ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600']"
        fill="currentColor"
        viewBox="0 0 20 20"
      >
        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 00-.363 1.118l1.286 3.957c.3.922-.755 1.688-1.54 1.118L10.49 15.347a1 1 0 00-1.176 0l-3.366 2.446c-.784.57-1.838-.196-1.539-1.118l1.286-3.957a1 1 0 00-.363-1.118L1.964 9.154c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.385-3.957z" />
      </svg>
    </button>
  </div>
</template>
