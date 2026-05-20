<script setup>
import { computed } from 'vue'

const props = defineProps({
  paginaActual: { type: Number, required: true },
  totalPaginas: { type: Number, required: true },
  ventana: { type: Number, default: 2 }, // páginas a cada lado
})

const emit = defineEmits(['cambiar'])

const paginas = computed(() => {
  const total = props.totalPaginas
  const actual = props.paginaActual
  const v = props.ventana
  const inicio = Math.max(1, actual - v)
  const fin = Math.min(total, actual + v)
  const lista = []
  for (let i = inicio; i <= fin; i++) lista.push(i)
  return lista
})

function ir(n) {
  if (n < 1 || n > props.totalPaginas || n === props.paginaActual) return
  emit('cambiar', n)
}
</script>

<template>
  <nav
    v-if="totalPaginas > 1"
    class="flex items-center justify-center gap-1"
    aria-label="Paginación"
  >
    <button
      type="button"
      class="btn-ghost"
      :disabled="paginaActual === 1"
      @click="ir(paginaActual - 1)"
    >
      ← Anterior
    </button>
    <button
      v-for="p in paginas"
      :key="p"
      type="button"
      class="rounded-md px-3 py-2 text-sm font-medium transition-colors"
      :class="p === paginaActual
        ? 'bg-primary-600 text-white'
        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
      :aria-current="p === paginaActual ? 'page' : undefined"
      @click="ir(p)"
    >
      {{ p }}
    </button>
    <button
      type="button"
      class="btn-ghost"
      :disabled="paginaActual === totalPaginas"
      @click="ir(paginaActual + 1)"
    >
      Siguiente →
    </button>
  </nav>
</template>
