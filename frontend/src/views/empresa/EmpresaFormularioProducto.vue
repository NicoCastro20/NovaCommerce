<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api'
import PanelLayout from '@/components/PanelLayout.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import { useNotificacionesStore } from '@/stores/notificaciones'

const route = useRoute()
const router = useRouter()
const toast = useNotificacionesStore()

const idProducto = computed(() => {
  const id = Number(route.params.id)
  return Number.isFinite(id) && id > 0 ? id : null
})
const esEdicion = computed(() => idProducto.value !== null)

const cargandoInicial = ref(true)
const errorInicial = ref(null)
const guardando = ref(false)

const categorias = ref([])

const formulario = reactive({
  name: '',
  description: '',
  price: '',
  stock: '',
  category_id: '',
  is_active: true,
  original_price: '',
  offer_starts_at: '',
  offer_ends_at: '',
  offer_label: '',
})

const ofertaAbierta = ref(false)

const errores = ref({})
const erroresImagen = ref(null)

const imagenesExistentes = ref([])

// Imágenes nuevas a subir: { archivo, previewUrl, esPrincipal }
const imagenesNuevas = ref([])
const arrastrando = ref(false)

const MAX_IMAGENES = 5
const TIPOS_VALIDOS = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']
const TAMANO_MAXIMO = 4 * 1024 * 1024 // 4 MB

const totalImagenes = computed(() => imagenesExistentes.value.length + imagenesNuevas.value.length)
const espacioRestante = computed(() => MAX_IMAGENES - totalImagenes.value)
const algunaExistentePrincipal = computed(() =>
  imagenesExistentes.value.some((img) => img.is_primary),
)

const tituloPagina = computed(() =>
  esEdicion.value ? 'Editar producto' : 'Nuevo producto',
)
const descripcionPagina = computed(() =>
  esEdicion.value
    ? 'Modifica los datos del producto y, si lo deseas, sube nuevas imágenes.'
    : 'Completa los datos del producto y añade hasta 5 imágenes.',
)

async function cargarCategorias() {
  try {
    const { data } = await api.get('/categories')
    categorias.value = data?.data?.categories ?? data?.data ?? []
  } catch {
    categorias.value = []
  }
}

async function cargarProducto() {
  if (!esEdicion.value) {
    cargandoInicial.value = false
    return
  }

  cargandoInicial.value = true
  errorInicial.value = null

  try {
    const { data } = await api.get('/empresa/productos', { params: { per_page: 48 } })
    const lista = data?.data ?? []
    const producto = lista.find((p) => Number(p.id) === idProducto.value)

    if (!producto) {
      errorInicial.value = 'No se encontró el producto o no tienes permiso para editarlo.'
      return
    }

    formulario.name = producto.name ?? ''
    formulario.description = producto.description ?? ''
    formulario.price = producto.price != null ? String(producto.price) : ''
    formulario.stock = producto.stock != null ? String(producto.stock) : ''
    formulario.category_id = producto.category?.id ?? ''
    formulario.is_active = producto.is_active !== false

    // Para precargar imágenes y datos completos de oferta pedimos el detalle público.
    if (producto.slug) {
      try {
        const detalle = await api.get(`/products/${producto.slug}`)
        const detalleProducto = detalle.data?.data?.product
        const imgs = detalleProducto?.images ?? []
        imagenesExistentes.value = imgs

        if (detalleProducto?.original_price != null) {
          formulario.original_price = String(detalleProducto.original_price)
          formulario.offer_label = detalleProducto.offer_label ?? ''
          formulario.offer_starts_at = recortarFechaIso(detalleProducto.offer_starts_at)
          formulario.offer_ends_at = recortarFechaIso(detalleProducto.offer_ends_at)
          ofertaAbierta.value = true
        }
      } catch {
        imagenesExistentes.value = []
      }
    }
  } catch (err) {
    errorInicial.value = err?.response?.data?.message ?? 'No se pudo cargar el producto.'
  } finally {
    cargandoInicial.value = false
  }
}

function recortarFechaIso(valor) {
  if (!valor) return ''
  // Convertimos a "YYYY-MM-DDTHH:mm" para <input type="datetime-local">.
  try {
    const fecha = new Date(valor)
    if (Number.isNaN(fecha.getTime())) return ''
    const pad = (n) => String(n).padStart(2, '0')
    const yyyy = fecha.getFullYear()
    const mm = pad(fecha.getMonth() + 1)
    const dd = pad(fecha.getDate())
    const hh = pad(fecha.getHours())
    const mi = pad(fecha.getMinutes())
    return `${yyyy}-${mm}-${dd}T${hh}:${mi}`
  } catch {
    return ''
  }
}

function validar() {
  const e = {}

  if (!formulario.name || formulario.name.trim().length < 3) {
    e.name = 'El nombre debe tener al menos 3 caracteres.'
  } else if (formulario.name.length > 200) {
    e.name = 'El nombre no puede superar los 200 caracteres.'
  }

  if (!formulario.description || formulario.description.trim().length === 0) {
    e.description = 'La descripción es obligatoria.'
  }

  const precio = Number(formulario.price)
  if (!formulario.price || Number.isNaN(precio) || precio < 0.01) {
    e.price = 'El precio debe ser un número mayor o igual a 0,01.'
  }

  const stock = Number(formulario.stock)
  if (formulario.stock === '' || !Number.isInteger(stock) || stock < 0) {
    e.stock = 'El stock debe ser un número entero igual o mayor que 0.'
  }

  if (!formulario.category_id) {
    e.category_id = 'Debes seleccionar una categoría.'
  }

  if (formulario.original_price !== '' && formulario.original_price !== null) {
    const original = Number(formulario.original_price)
    if (Number.isNaN(original) || original <= 0) {
      e.original_price = 'El precio original debe ser un número mayor que 0.'
    } else if (!Number.isNaN(precio) && original <= precio) {
      e.original_price = 'El precio original debe ser mayor que el precio de oferta.'
    }
  }

  if (
    formulario.offer_starts_at
    && formulario.offer_ends_at
    && new Date(formulario.offer_ends_at) <= new Date(formulario.offer_starts_at)
  ) {
    e.offer_ends_at = 'La fecha de fin debe ser posterior a la de inicio.'
  }

  if (formulario.offer_label && formulario.offer_label.length > 50) {
    e.offer_label = 'La etiqueta no puede superar los 50 caracteres.'
  }

  errores.value = e
  return Object.keys(e).length === 0
}

function aceptarArchivos(archivos) {
  erroresImagen.value = null
  const lista = Array.from(archivos || [])

  if (lista.length === 0) return

  if (totalImagenes.value + lista.length > MAX_IMAGENES) {
    erroresImagen.value = `Solo puedes subir hasta ${MAX_IMAGENES} imágenes en total.`
    return
  }

  for (const archivo of lista) {
    if (!TIPOS_VALIDOS.includes(archivo.type)) {
      erroresImagen.value = 'Solo se admiten imágenes JPG, PNG o WEBP.'
      continue
    }
    if (archivo.size > TAMANO_MAXIMO) {
      erroresImagen.value = 'Cada imagen debe pesar como máximo 4 MB.'
      continue
    }
    imagenesNuevas.value.push({
      archivo,
      previewUrl: URL.createObjectURL(archivo),
      esPrincipal: false,
    })
  }

  ajustarPrincipal()
}

function ajustarPrincipal() {
  const hayPrincipalExistente = algunaExistentePrincipal.value
  const tienePrincipalNueva = imagenesNuevas.value.some((img) => img.esPrincipal)

  if (!hayPrincipalExistente && !tienePrincipalNueva && imagenesNuevas.value.length > 0) {
    imagenesNuevas.value[0].esPrincipal = true
  }
}

function marcarPrincipal(indice) {
  imagenesNuevas.value.forEach((img, i) => {
    img.esPrincipal = i === indice
  })
}

function quitarImagenNueva(indice) {
  const eliminada = imagenesNuevas.value.splice(indice, 1)[0]
  if (eliminada?.previewUrl) {
    URL.revokeObjectURL(eliminada.previewUrl)
  }
  ajustarPrincipal()
}

function alSoltar(evento) {
  arrastrando.value = false
  if (espacioRestante.value <= 0) return
  aceptarArchivos(evento.dataTransfer?.files)
}

function alSeleccionarArchivos(evento) {
  aceptarArchivos(evento.target.files)
  evento.target.value = ''
}

async function guardar() {
  if (!validar()) {
    toast.error('Revisa los campos marcados en rojo.')
    return
  }

  guardando.value = true

  try {
    const tieneOferta = formulario.original_price !== '' && formulario.original_price !== null

    const cuerpo = {
      name: formulario.name.trim(),
      description: formulario.description.trim(),
      price: Number(formulario.price),
      stock: Number(formulario.stock),
      category_id: Number(formulario.category_id),
      is_active: !!formulario.is_active,
      // Si el campo está vacío, enviamos null para desactivar la oferta.
      original_price: tieneOferta ? Number(formulario.original_price) : null,
      offer_starts_at: tieneOferta && formulario.offer_starts_at ? formulario.offer_starts_at : null,
      offer_ends_at:   tieneOferta && formulario.offer_ends_at   ? formulario.offer_ends_at   : null,
      offer_label:     tieneOferta && formulario.offer_label     ? formulario.offer_label.trim() : null,
    }

    let productoId = idProducto.value

    if (esEdicion.value) {
      await api.put(`/empresa/productos/${productoId}`, cuerpo)
    } else {
      const { data } = await api.post('/empresa/productos', cuerpo)
      productoId = data?.data?.product?.id ?? null
    }

    if (!productoId) {
      throw new Error('No se obtuvo el id del producto.')
    }

    if (imagenesNuevas.value.length > 0) {
      const formData = new FormData()
      // El backend marca como principal la primera de la subida si no hay principal
      // existente; reordenamos para colocar la marcada en primer lugar.
      const ordenadas = [...imagenesNuevas.value].sort((a, b) => {
        if (a.esPrincipal && !b.esPrincipal) return -1
        if (!a.esPrincipal && b.esPrincipal) return 1
        return 0
      })

      ordenadas.forEach((img, i) => {
        formData.append(`images[${i}]`, img.archivo)
      })

      await api.post(`/empresa/productos/${productoId}/imagenes`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }

    toast.exito(
      esEdicion.value
        ? 'Producto actualizado correctamente.'
        : 'Producto creado correctamente.',
    )
    limpiarPreviews()
    router.push('/empresa/productos')
  } catch (err) {
    if (err?.response?.status === 422 && err.response.data?.errors) {
      errores.value = Object.fromEntries(
        Object.entries(err.response.data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]),
      )
    }
    toast.error(err?.response?.data?.message ?? 'No se pudo guardar el producto.')
  } finally {
    guardando.value = false
  }
}

function cancelar() {
  router.push('/empresa/productos')
}

function limpiarPreviews() {
  imagenesNuevas.value.forEach((img) => {
    if (img.previewUrl) URL.revokeObjectURL(img.previewUrl)
  })
}

onMounted(async () => {
  await cargarCategorias()
  await cargarProducto()
})

onBeforeUnmount(limpiarPreviews)
</script>

<template>
  <PanelLayout
    tipo="empresa"
    :titulo="tituloPagina"
    :descripcion="descripcionPagina"
  >
    <div v-if="cargandoInicial" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando producto..." />
    </div>

    <EstadoVacio
      v-else-if="errorInicial"
      icono="error"
      titulo="No se pudo cargar el producto"
      :descripcion="errorInicial"
    >
      <button class="btn-primary" @click="router.push('/empresa/productos')">
        Volver a mis productos
      </button>
    </EstadoVacio>

    <form v-else class="space-y-6" novalidate @submit.prevent="guardar">
      <!-- Datos básicos -->
      <div class="card p-5">
        <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
          Datos del producto
        </h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="name" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Nombre <span class="text-red-500">*</span>
            </label>
            <input
              id="name"
              v-model="formulario.name"
              type="text"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.name }"
              placeholder="Ej. Camiseta de algodón orgánico"
              maxlength="200"
            />
            <p v-if="errores.name" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errores.name }}</p>
          </div>

          <div class="sm:col-span-2">
            <label for="description" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Descripción <span class="text-red-500">*</span>
            </label>
            <textarea
              id="description"
              v-model="formulario.description"
              rows="5"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.description }"
              placeholder="Describe materiales, tallas, características..."
            ></textarea>
            <p v-if="errores.description" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errores.description }}</p>
          </div>

          <div>
            <label for="price" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Precio (EUR) <span class="text-red-500">*</span>
            </label>
            <input
              id="price"
              v-model="formulario.price"
              type="number"
              min="0.01"
              step="0.01"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.price }"
              placeholder="0,00"
            />
            <p v-if="errores.price" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errores.price }}</p>
          </div>

          <div>
            <label for="stock" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Stock <span class="text-red-500">*</span>
            </label>
            <input
              id="stock"
              v-model="formulario.stock"
              type="number"
              min="0"
              step="1"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.stock }"
              placeholder="0"
            />
            <p v-if="errores.stock" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errores.stock }}</p>
          </div>

          <div class="sm:col-span-2">
            <label for="category_id" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Categoría <span class="text-red-500">*</span>
            </label>
            <select
              id="category_id"
              v-model="formulario.category_id"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.category_id }"
            >
              <option value="">Selecciona una categoría</option>
              <template v-for="cat in categorias" :key="cat.id">
                <option :value="cat.id">{{ cat.name }}</option>
                <option v-for="sub in cat.children ?? []" :key="sub.id" :value="sub.id">
                  &nbsp;&nbsp;↳ {{ sub.name }}
                </option>
              </template>
            </select>
            <p v-if="errores.category_id" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errores.category_id }}</p>
          </div>

          <div class="flex items-center gap-2 sm:col-span-2">
            <input
              id="is_active"
              v-model="formulario.is_active"
              type="checkbox"
              class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
            />
            <label for="is_active" class="text-sm text-slate-700 dark:text-slate-300">
              Producto activo (visible en el catálogo)
            </label>
          </div>
        </div>
      </div>

      <!-- Configurar oferta (opcional) -->
      <div class="card p-5">
        <button
          type="button"
          class="flex w-full items-center justify-between gap-2 text-left"
          :aria-expanded="ofertaAbierta"
          @click="ofertaAbierta = !ofertaAbierta"
        >
          <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
              Configurar oferta
              <span class="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                Opcional
              </span>
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Marca el producto como rebajado mostrando el precio original tachado y el porcentaje de descuento.
            </p>
          </div>
          <svg
            class="h-5 w-5 shrink-0 text-slate-500 transition-transform"
            :class="ofertaAbierta ? 'rotate-180' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>

        <div v-if="ofertaAbierta" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
            Para desactivar la oferta deja el campo <strong>Precio original</strong> vacío.
          </div>

          <div>
            <label for="original_price" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Precio original (EUR)
            </label>
            <input
              id="original_price"
              v-model="formulario.original_price"
              type="number"
              min="0.01"
              step="0.01"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.original_price }"
              placeholder="Ej. 99,99"
            />
            <p v-if="errores.original_price" class="mt-1 text-xs text-red-600 dark:text-red-400">
              {{ errores.original_price }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              Debe ser mayor que el precio actual del producto.
            </p>
          </div>

          <div>
            <label for="offer_label" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Etiqueta de la oferta
            </label>
            <input
              id="offer_label"
              v-model="formulario.offer_label"
              type="text"
              maxlength="50"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.offer_label }"
              placeholder="Ej. BLACK FRIDAY, REBAJAS..."
            />
            <p v-if="errores.offer_label" class="mt-1 text-xs text-red-600 dark:text-red-400">
              {{ errores.offer_label }}
            </p>
          </div>

          <div>
            <label for="offer_starts_at" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Inicio de la oferta
            </label>
            <input
              id="offer_starts_at"
              v-model="formulario.offer_starts_at"
              type="datetime-local"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.offer_starts_at }"
            />
            <p v-if="errores.offer_starts_at" class="mt-1 text-xs text-red-600 dark:text-red-400">
              {{ errores.offer_starts_at }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              Si lo dejas vacío, la oferta se activa inmediatamente.
            </p>
          </div>

          <div>
            <label for="offer_ends_at" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Fin de la oferta
            </label>
            <input
              id="offer_ends_at"
              v-model="formulario.offer_ends_at"
              type="datetime-local"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.offer_ends_at }"
            />
            <p v-if="errores.offer_ends_at" class="mt-1 text-xs text-red-600 dark:text-red-400">
              {{ errores.offer_ends_at }}
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              Vacío = sin caducidad automática.
            </p>
          </div>
        </div>
      </div>

      <!-- Imágenes -->
      <div class="card p-5">
        <h2 class="mb-1 text-lg font-semibold text-slate-900 dark:text-white">Imágenes</h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
          Hasta {{ MAX_IMAGENES }} imágenes en total. Formatos: JPG, PNG, WEBP. Máximo 4 MB cada una.
          Marca cuál de las imágenes nuevas debe ser la principal.
        </p>

        <!-- Imágenes existentes -->
        <div v-if="imagenesExistentes.length > 0" class="mb-4">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Imágenes actuales
          </p>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-5">
            <div
              v-for="img in imagenesExistentes"
              :key="img.id"
              class="relative overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800"
            >
              <img :src="img.url" :alt="`Imagen ${img.id}`" class="aspect-square w-full object-cover" />
              <span
                v-if="img.is_primary"
                class="absolute left-1 top-1 rounded bg-primary-600 px-1.5 py-0.5 text-[0.65rem] font-bold uppercase text-white"
              >
                Principal
              </span>
            </div>
          </div>
        </div>

        <!-- Drag & drop / picker -->
        <div
          v-if="espacioRestante > 0"
          class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-10 text-center transition-colors"
          :class="arrastrando
            ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
            : 'border-slate-300 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50'"
          @dragover.prevent="arrastrando = true"
          @dragenter.prevent="arrastrando = true"
          @dragleave.prevent="arrastrando = false"
          @drop.prevent="alSoltar"
        >
          <svg class="mb-2 h-10 w-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M16 8l-4-4m0 0L8 8m4-4v12"/>
          </svg>
          <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
            Arrastra imágenes aquí o
            <label class="cursor-pointer text-primary-700 hover:underline dark:text-primary-400">
              haz clic para seleccionar
              <input
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/webp"
                multiple
                class="hidden"
                @change="alSeleccionarArchivos"
              />
            </label>
          </p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Quedan {{ espacioRestante }} de {{ MAX_IMAGENES }}.
          </p>
        </div>
        <p v-else class="rounded-lg bg-slate-100 p-3 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">
          Has alcanzado el máximo de {{ MAX_IMAGENES }} imágenes.
        </p>

        <p v-if="erroresImagen" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ erroresImagen }}</p>

        <!-- Previews de imágenes nuevas -->
        <div v-if="imagenesNuevas.length > 0" class="mt-4">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Imágenes a subir
          </p>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-5">
            <div
              v-for="(img, i) in imagenesNuevas"
              :key="i"
              class="relative overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
              <img :src="img.previewUrl" alt="Vista previa" class="aspect-square w-full object-cover" />
              <button
                type="button"
                class="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white transition-colors hover:bg-red-600"
                aria-label="Quitar imagen"
                @click="quitarImagenNueva(i)"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
              <button
                type="button"
                class="absolute bottom-1 left-1 right-1 rounded px-1.5 py-0.5 text-[0.65rem] font-bold uppercase transition-colors"
                :class="img.esPrincipal
                  ? 'bg-primary-600 text-white'
                  : 'bg-white/80 text-slate-700 hover:bg-white'"
                :disabled="algunaExistentePrincipal && !img.esPrincipal"
                :title="algunaExistentePrincipal ? 'Ya existe una imagen principal' : ''"
                @click="marcarPrincipal(i)"
              >
                {{ img.esPrincipal ? 'Principal' : 'Marcar principal' }}
              </button>
            </div>
          </div>
          <p v-if="algunaExistentePrincipal" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Ya existe una imagen principal en este producto.
          </p>
        </div>
      </div>

      <!-- Acciones -->
      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" class="btn-secondary" :disabled="guardando" @click="cancelar">
          Cancelar
        </button>
        <button type="submit" class="btn-primary" :disabled="guardando">
          <svg v-if="guardando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
          {{ esEdicion ? 'Guardar cambios' : 'Crear producto' }}
        </button>
      </div>
    </form>
  </PanelLayout>
</template>
