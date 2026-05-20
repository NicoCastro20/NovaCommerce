import { onBeforeUnmount, onMounted, ref } from 'vue'

// Composable que activa una clase cuando el elemento entra en viewport.
// Usa IntersectionObserver, sin librerías externas.
export function useRevelar(opciones = {}) {
  const elemento = ref(null)
  const visible = ref(false)
  let observer = null

  onMounted(() => {
    if (!elemento.value) return

    if (typeof IntersectionObserver === 'undefined') {
      visible.value = true
      return
    }

    observer = new IntersectionObserver(
      ([entrada]) => {
        if (entrada.isIntersecting) {
          visible.value = true
          observer?.disconnect()
        }
      },
      { threshold: opciones.threshold ?? 0.15, rootMargin: opciones.rootMargin ?? '0px' },
    )
    observer.observe(elemento.value)
  })

  onBeforeUnmount(() => {
    observer?.disconnect()
  })

  return { elemento, visible }
}
