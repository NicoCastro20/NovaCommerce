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
  category_id: '',
  condition: 'buen_estado',
})

const errores = ref({})
const erroresImagen = ref(null)

const imagenesExistentes = ref([])
const imagenesNuevas = ref([])
const arrastrando = ref(false)

const MAX_IMAGENES = 5
const TIPOS_VALIDOS = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']
const TAMANO_MAXIMO = 4 * 1024 * 1024

const totalImagenes = computed(() => imagenesExistentes.value.length + imagenesNuevas.value.length)
const espacioRestante = computed(() => MAX_IMAGENES - totalImagenes.value)
const algunaExistentePrincipal = computed(() =>
  imagenesExistentes.value.some((img) => img.is_primary),
)

const tituloPagina = computed(() =>
  esEdicion.value ? 'Editar producto en venta' : 'Publicar producto',
)
const descripcionPagina = computed(() =>
  esEdicion.value
    ? 'Modifica los datos del producto y, si lo deseas, sube nuevas imágenes.'
    : 'Cuenta a otros usuarios qué vendes. Stock fijado a 1: cada artículo se vende una sola vez.',
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
    const { data } = await api.get('/mis-productos', { params: { per_page: 48 } })
    const lista = data?.data ?? []
    const producto = lista.find((p) => Number(p.id) === idProducto.value)

    if (!producto) {
      errorInicial.value = 'No se encontró el producto o no tienes permiso para editarlo.'
      return
    }

    formulario.name = producto.name ?? ''
    formulario.description = producto.description ?? ''
    formulario.price = producto.price != null ? String(producto.price) : ''
    formulario.category_id = producto.category?.id ?? ''
    formulario.condition = producto.condition ?? 'buen_estado'

    if (producto.slug) {
      try {
        const detalle = await api.get(`/products/${producto.slug}`)
        const imgs = detalle.data?.data?.product?.images ?? []
        imagenesExistentes.value = imgs
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

  if (!formulario.category_id) {
    e.category_id = 'Debes seleccionar una categoría.'
  }

  if (!['nuevo', 'como_nuevo', 'buen_estado', 'usado'].includes(formulario.condition)) {
    e.condition = 'Debes indicar el estado del producto.'
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
    const cuerpo = {
      name: formulario.name.trim(),
      description: formulario.description.trim(),
      price: Number(formulario.price),
      category_id: Number(formulario.category_id),
      condition: formulario.condition,
    }

    let productoId = idProducto.value

    if (esEdicion.value) {
      await api.put(`/mis-productos/${productoId}`, cuerpo)
    } else {
      const { data } = await api.post('/mis-productos', cuerpo)
      productoId = data?.data?.product?.id ?? null
    }

    if (!productoId) {
      throw new Error('No se obtuvo el id del producto.')
    }

    if (imagenesNuevas.value.length > 0) {
      const formData = new FormData()
      const ordenadas = [...imagenesNuevas.value].sort((a, b) => {
        if (a.esPrincipal && !b.esPrincipal) return -1
        if (!a.esPrincipal && b.esPrincipal) return 1
        return 0
      })

      ordenadas.forEach((img, i) => {
        formData.append(`images[${i}]`, img.archivo)
      })

      await api.post(`/mis-productos/${productoId}/imagenes`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }

    toast.exito(
      esEdicion.value
        ? 'Producto actualizado correctamente.'
        : 'Producto publicado correctamente.',
    )
    limpiarPreviews()
    router.push('/mis-productos')
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
  router.push('/mis-productos')
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
    tipo="usuario"
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
      <button class="btn-primary" @click="router.push('/mis-productos')">
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
              placeholder="Ej. Bicicleta de montaña Trek"
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
              placeholder="Características, estado, accesorios incluidos..."
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
            <label for="condition" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Estado del producto <span class="text-red-500">*</span>
            </label>
            <select
              id="condition"
              v-model="formulario.condition"
              class="input"
              :class="{ 'border-red-500 focus:ring-red-500': errores.condition }"
            >
              <option value="nuevo">Nuevo</option>
              <option value="como_nuevo">Como nuevo</option>
              <option value="buen_estado">Buen estado</option>
              <option value="usado">Usado</option>
            </select>
            <p v-if="errores.condition" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ errores.condition }}</p>
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
        </div>

        <p class="mt-4 rounded-lg bg-primary-50 p-3 text-xs text-primary-800 dark:bg-primary-900/20 dark:text-primary-200">
          Stock fijado a 1: cada artículo se vende una sola vez.
        </p>
      </div>

      <!-- Imágenes -->
      <div class="card p-5">
        <h2 class="mb-1 text-lg font-semibold text-slate-900 dark:text-white">Imágenes</h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
          Hasta {{ MAX_IMAGENES }} imágenes en total. Formatos: JPG, PNG, WEBP. Máximo 4 MB cada una.
        </p>

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
        </div>
      </div>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" class="btn-secondary" :disabled="guardando" @click="cancelar">
          Cancelar
        </button>
        <button type="submit" class="btn-primary" :disabled="guardando">
          <svg v-if="guardando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
          </svg>
          {{ esEdicion ? 'Guardar cambios' : 'Publicar producto' }}
        </button>
      </div>
    </form>
  </PanelLayout>
</template>
