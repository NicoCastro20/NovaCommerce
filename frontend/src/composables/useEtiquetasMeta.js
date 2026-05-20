import { onUnmounted, watch } from 'vue'

// Composable ligero para gestionar `<title>` y meta tags dinámicos por página
// sin añadir dependencias externas. Restaura el valor anterior al desmontar.

const SUFIJO = ' · NovaCommerce'

function obtenerOCrearMeta(nombre) {
  let etiqueta = document.head.querySelector(`meta[name="${nombre}"]`)
  if (!etiqueta) {
    etiqueta = document.createElement('meta')
    etiqueta.setAttribute('name', nombre)
    document.head.appendChild(etiqueta)
  }
  return etiqueta
}

function obtenerOCrearProperty(prop) {
  let etiqueta = document.head.querySelector(`meta[property="${prop}"]`)
  if (!etiqueta) {
    etiqueta = document.createElement('meta')
    etiqueta.setAttribute('property', prop)
    document.head.appendChild(etiqueta)
  }
  return etiqueta
}

/**
 * Establece title y meta description.
 * Acepta valores reactivos (refs) o estáticos a través de un getter.
 *
 * @param {() => { titulo?: string, descripcion?: string, imagen?: string }} obtener
 */
export function useEtiquetasMeta(obtener) {
  const tituloPrevio = document.title

  const aplicar = () => {
    const datos = typeof obtener === 'function' ? obtener() : obtener
    if (!datos) return

    if (datos.titulo) {
      document.title = `${datos.titulo}${SUFIJO}`
    }

    if (datos.descripcion) {
      obtenerOCrearMeta('description').setAttribute('content', datos.descripcion)
      obtenerOCrearProperty('og:description').setAttribute('content', datos.descripcion)
    }

    if (datos.titulo) {
      obtenerOCrearProperty('og:title').setAttribute('content', `${datos.titulo}${SUFIJO}`)
    }

    if (datos.imagen) {
      obtenerOCrearProperty('og:image').setAttribute('content', datos.imagen)
    }
  }

  aplicar()

  // Reactividad: si la función depende de refs, re-aplicar al cambiar.
  if (typeof obtener === 'function') {
    watch(obtener, aplicar, { deep: true })
  }

  onUnmounted(() => {
    document.title = tituloPrevio
  })
}
