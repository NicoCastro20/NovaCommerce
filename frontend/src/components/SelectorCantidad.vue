<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Number, required: true },
  min: { type: Number, default: 1 },
  max: { type: Number, default: 99 },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const valor = computed(() => props.modelValue)

function actualizar(nuevo) {
  let n = Math.floor(Number(nuevo))
  if (Number.isNaN(n)) n = props.min
  if (n < props.min) n = props.min
  if (n > props.max) n = props.max
  if (n !== valor.value) emit('update:modelValue', n)
}

function decrementar() {
  if (props.disabled) return
  actualizar(valor.value - 1)
}
function incrementar() {
  if (props.disabled) return
  actualizar(valor.value + 1)
}
function alEscribir(evento) {
  actualizar(evento.target.value)
}
</script>

<template>
  <div class="inline-flex items-stretch rounded-lg border border-slate-300 dark:border-slate-700">
    <button
      type="button"
      class="px-3 text-lg font-bold text-slate-600 hover:bg-slate-100 disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
      :disabled="disabled || valor <= min"
      aria-label="Disminuir cantidad"
      @click="decrementar"
    >−</button>
    <input
      type="number"
      :min="min"
      :max="max"
      :value="valor"
      :disabled="disabled"
      class="w-14 border-x border-slate-300 bg-white text-center text-sm font-semibold text-slate-900 focus:outline-none disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
      aria-label="Cantidad"
      @input="alEscribir"
    />
    <button
      type="button"
      class="px-3 text-lg font-bold text-slate-600 hover:bg-slate-100 disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
      :disabled="disabled || valor >= max"
      aria-label="Aumentar cantidad"
      @click="incrementar"
    >+</button>
  </div>
</template>

<style scoped>
input[type='number']::-webkit-outer-spin-button,
input[type='number']::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type='number'] {
  -moz-appearance: textfield;
}
</style>
