import { ref } from 'vue'

// Singleton minimalista al estilo NProgress: barra de progreso superior global.
// `iniciar()` muestra la barra, `terminar()` la lleva al 100% y la oculta.
// Soporta múltiples llamadas concurrentes (referencia contada).

const progreso = ref(0)
const visible = ref(false)
let pendientes = 0
let temporizador = null

function avanzar() {
  if (progreso.value >= 90) return
  // Avance asintótico hacia 90 mientras la operación no termina.
  const incremento = (90 - progreso.value) * 0.15
  progreso.value = Math.min(90, progreso.value + Math.max(0.5, incremento))
}

function programarTick() {
  if (temporizador) return
  temporizador = window.setInterval(avanzar, 250)
}

function detenerTick() {
  if (temporizador) {
    clearInterval(temporizador)
    temporizador = null
  }
}

export function useProgreso() {
  function iniciar() {
    pendientes++
    if (!visible.value) {
      visible.value = true
      progreso.value = 8
      programarTick()
    }
  }

  function terminar() {
    pendientes = Math.max(0, pendientes - 1)
    if (pendientes > 0) return

    detenerTick()
    progreso.value = 100
    window.setTimeout(() => {
      visible.value = false
      progreso.value = 0
    }, 250)
  }

  function fallar() {
    pendientes = 0
    detenerTick()
    progreso.value = 100
    window.setTimeout(() => {
      visible.value = false
      progreso.value = 0
    }, 250)
  }

  return { progreso, visible, iniciar, terminar, fallar }
}
