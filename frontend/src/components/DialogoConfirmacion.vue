<script setup>
import { computed } from 'vue'

const props = defineProps({
  abierto: { type: Boolean, default: false },
  titulo: { type: String, default: '¿Estás seguro?' },
  mensaje: { type: String, default: 'Esta acción no se puede deshacer.' },
  textoConfirmar: { type: String, default: 'Confirmar' },
  textoCancelar: { type: String, default: 'Cancelar' },
  variante: { type: String, default: 'primary' }, // primary | danger
  cargando: { type: Boolean, default: false },
})

const emit = defineEmits(['confirmar', 'cancelar', 'update:abierto'])

const claseBotonConfirmar = computed(() =>
  props.variante === 'danger'
    ? 'inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed dark:focus:ring-offset-slate-900'
    : 'btn-primary',
)

function cancelar() {
  if (props.cargando) return
  emit('update:abierto', false)
  emit('cancelar')
}

function confirmar() {
  emit('confirmar')
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="abierto"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 px-4"
        role="dialog"
        aria-modal="true"
        @click.self="cancelar"
      >
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          appear
        >
          <div class="card w-full max-w-md p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ titulo }}</h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ mensaje }}</p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <button
                type="button"
                class="btn-secondary"
                :disabled="cargando"
                @click="cancelar"
              >
                {{ textoCancelar }}
              </button>
              <button
                type="button"
                :class="claseBotonConfirmar"
                :disabled="cargando"
                @click="confirmar"
              >
                <svg v-if="cargando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                {{ cargando ? 'Procesando...' : textoConfirmar }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
