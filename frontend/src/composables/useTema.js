import { ref, watch } from 'vue'

// Composable singleton para alternar entre tema claro y oscuro.
// El valor se persiste en localStorage para sobrevivir a recargas.
const CLAVE = 'novacommerce.tema'

const inicial = (() => {
  const guardado = localStorage.getItem(CLAVE)
  if (guardado === 'oscuro' || guardado === 'claro') return guardado
  // Por defecto, respetar la preferencia del sistema.
  if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) return 'oscuro'
  return 'claro'
})()

const tema = ref(inicial)

function aplicarClase(valor) {
  const html = document.documentElement
  if (valor === 'oscuro') {
    html.classList.add('dark')
  } else {
    html.classList.remove('dark')
  }
}

aplicarClase(tema.value)

watch(tema, (nuevo) => {
  aplicarClase(nuevo)
  localStorage.setItem(CLAVE, nuevo)
})

export function useTema() {
  function alternar() {
    tema.value = tema.value === 'oscuro' ? 'claro' : 'oscuro'
  }

  return {
    tema,
    alternar,
  }
}
