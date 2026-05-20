<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api'
import PanelLayout from '@/components/PanelLayout.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import EstadoVacio from '@/components/EstadoVacio.vue'
import Paginacion from '@/components/Paginacion.vue'
import DialogoConfirmacion from '@/components/DialogoConfirmacion.vue'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { useAutenticacionStore } from '@/stores/autenticacion'

const route = useRoute()
const router = useRouter()
const toast = useNotificacionesStore()
const auth = useAutenticacionStore()

const usuarios = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const cargando = ref(true)
const error = ref(null)

const filtros = ref({ search: '', role: '', estado: '', page: 1 })
let temporizadorBusqueda = null

const cambiandoRol = ref({}) // { [userId]: bool }
const dialogoDesactivar = ref(false)
const usuarioADesactivar = ref(null)
const desactivando = ref(false)

const ROLES = [
  { valor: 'usuario', etiqueta: 'Usuario' },
  { valor: 'empresa', etiqueta: 'Empresa' },
  { valor: 'admin', etiqueta: 'Administrador' },
]

const paginaActual = computed(() => meta.value?.current_page ?? 1)
const totalPaginas = computed(() => meta.value?.last_page ?? 1)

function etiquetaRol(rol) {
  return ROLES.find((r) => r.valor === rol)?.etiqueta ?? rol
}

function clasesRol(rol) {
  return {
    usuario: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    empresa: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    admin: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200',
  }[rol] || 'bg-slate-100 text-slate-700'
}

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    const params = { page: filtros.value.page, per_page: 15 }
    if (filtros.value.search) params.search = filtros.value.search
    if (filtros.value.role) params.role = filtros.value.role
    if (filtros.value.estado === 'activos') {
      // Por defecto se devuelven solo activos.
    } else if (filtros.value.estado === 'todos') {
      params.with_trashed = 1
    } else if (filtros.value.estado === 'desactivados') {
      params.only_trashed = 1
    }

    const { data } = await api.get('/admin/users', { params })
    usuarios.value = data?.data ?? []
    meta.value = data?.meta ?? meta.value
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'No se pudieron obtener los usuarios.'
  } finally {
    cargando.value = false
  }
}

function alBuscar(evento) {
  const valor = evento.target.value
  if (temporizadorBusqueda) clearTimeout(temporizadorBusqueda)
  temporizadorBusqueda = setTimeout(() => {
    filtros.value.search = valor
    filtros.value.page = 1
    sincronizarRuta()
    cargar()
  }, 300)
}

function alCambiarFiltro() {
  filtros.value.page = 1
  sincronizarRuta()
  cargar()
}

function sincronizarRuta() {
  const query = {}
  if (filtros.value.search) query.search = filtros.value.search
  if (filtros.value.role) query.role = filtros.value.role
  if (filtros.value.estado) query.estado = filtros.value.estado
  if (filtros.value.page > 1) query.page = String(filtros.value.page)
  router.replace({ query })
}

function cambiarPagina(p) {
  filtros.value.page = p
  sincronizarRuta()
  cargar()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function cambiarRol(usuario, nuevoRol) {
  if (!nuevoRol || nuevoRol === usuario.role) return
  if (usuario.id === auth.usuario?.id) {
    toast.error('No puedes cambiar tu propio rol.')
    return
  }
  cambiandoRol.value = { ...cambiandoRol.value, [usuario.id]: true }
  try {
    await api.put(`/admin/users/${usuario.id}/role`, { role: nuevoRol })
    usuario.role = nuevoRol
    toast.exito(`Rol actualizado a ${etiquetaRol(nuevoRol)}.`)
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo cambiar el rol.')
  } finally {
    cambiandoRol.value = { ...cambiandoRol.value, [usuario.id]: false }
  }
}

function pedirDesactivar(u) {
  if (u.id === auth.usuario?.id) {
    toast.error('No puedes desactivar tu propia cuenta.')
    return
  }
  usuarioADesactivar.value = u
  dialogoDesactivar.value = true
}

async function confirmarDesactivar() {
  if (!usuarioADesactivar.value) return
  desactivando.value = true
  try {
    await api.delete(`/admin/users/${usuarioADesactivar.value.id}`)
    toast.exito('Usuario desactivado correctamente.')
    dialogoDesactivar.value = false
    usuarioADesactivar.value = null
    cargar()
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'No se pudo desactivar el usuario.')
  } finally {
    desactivando.value = false
  }
}

function formatearFecha(fecha) {
  if (!fecha) return ''
  try {
    return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' }).format(new Date(fecha))
  } catch {
    return ''
  }
}

watch(() => route.query, (nueva) => {
  filtros.value.search = nueva.search ?? ''
  filtros.value.role = nueva.role ?? ''
  filtros.value.estado = nueva.estado ?? ''
  filtros.value.page = Number(nueva.page) || 1
  cargar()
})

onMounted(() => {
  filtros.value.search = route.query.search ?? ''
  filtros.value.role = route.query.role ?? ''
  filtros.value.estado = route.query.estado ?? ''
  filtros.value.page = Number(route.query.page) || 1
  cargar()
})
</script>

<template>
  <PanelLayout
    tipo="admin"
    titulo="Gestión de usuarios"
    descripcion="Cambia roles y desactiva cuentas. No puedes editar tu propia cuenta desde aquí."
  >
    <!-- Filtros -->
    <div class="card mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
      <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
        </span>
        <input
          :value="filtros.search"
          type="search"
          class="input pl-9"
          placeholder="Buscar por nombre, email o NIF..."
          aria-label="Buscar usuarios"
          @input="alBuscar"
        />
      </div>

      <select v-model="filtros.role" class="input" aria-label="Filtrar por rol" @change="alCambiarFiltro">
        <option value="">Todos los roles</option>
        <option v-for="r in ROLES" :key="r.valor" :value="r.valor">{{ r.etiqueta }}</option>
      </select>

      <select v-model="filtros.estado" class="input" aria-label="Filtrar por estado" @change="alCambiarFiltro">
        <option value="">Solo activos</option>
        <option value="todos">Todos (incluye desactivados)</option>
        <option value="desactivados">Solo desactivados</option>
      </select>
    </div>

    <div v-if="cargando" class="flex justify-center py-16">
      <LoadingSpinner size="lg" texto="Cargando usuarios..." />
    </div>

    <EstadoVacio
      v-else-if="error"
      icono="error"
      titulo="No se pudieron cargar los usuarios"
      :descripcion="error"
    >
      <button class="btn-primary" @click="cargar">Reintentar</button>
    </EstadoVacio>

    <EstadoVacio
      v-else-if="usuarios.length === 0"
      icono="search"
      titulo="No hay usuarios que coincidan"
      descripcion="Prueba a ajustar los filtros."
    />

    <div v-else class="space-y-4">
      <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
            <tr>
              <th class="px-4 py-3">Nombre</th>
              <th class="px-4 py-3">Email</th>
              <th class="px-4 py-3">Rol</th>
              <th class="px-4 py-3">Premium</th>
              <th class="px-4 py-3 text-right">Productos</th>
              <th class="px-4 py-3 text-right">Pedidos</th>
              <th class="px-4 py-3">Registro</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr
              v-for="u in usuarios"
              :key="u.id"
              class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40"
            >
              <td class="px-4 py-3">
                <div class="font-medium text-slate-900 dark:text-white">
                  {{ u.name }}
                  <span v-if="u.id === auth.usuario?.id" class="ml-1 text-xs font-normal text-primary-600 dark:text-primary-400">(tú)</span>
                </div>
                <div v-if="u.company_name" class="text-xs text-slate-500 dark:text-slate-400">
                  {{ u.company_name }}
                </div>
              </td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ u.email }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold capitalize"
                  :class="clasesRol(u.role)"
                >
                  {{ etiquetaRol(u.role) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span
                  v-if="u.premium_active"
                  class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                >
                  <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.18 4.49L18.21 22 12 17.27 5.79 22l2.39-8.15L2 9.36h7.61z"/></svg>
                  Sí
                </span>
                <span
                  v-else
                  class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                >
                  No
                </span>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-white">
                {{ u.productos_count ?? 0 }}
              </td>
              <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-white">
                {{ u.pedidos_count ?? 0 }}
              </td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                {{ formatearFecha(u.created_at) }}
              </td>
              <td class="px-4 py-3">
                <span
                  v-if="u.deleted_at"
                  class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-800 dark:bg-red-900/40 dark:text-red-200"
                >
                  Desactivado
                </span>
                <span
                  v-else
                  class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200"
                >
                  Activo
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex flex-col items-end gap-1 sm:flex-row sm:items-center sm:justify-end sm:gap-2">
                  <select
                    :value="u.role"
                    class="input h-8 py-0 text-xs"
                    :disabled="u.id === auth.usuario?.id || cambiandoRol[u.id] || !!u.deleted_at"
                    :aria-label="`Cambiar rol de ${u.name}`"
                    @change="cambiarRol(u, $event.target.value)"
                  >
                    <option v-for="r in ROLES" :key="r.valor" :value="r.valor">{{ r.etiqueta }}</option>
                  </select>
                  <button
                    v-if="!u.deleted_at"
                    type="button"
                    class="text-xs font-medium text-red-600 hover:underline dark:text-red-400 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="u.id === auth.usuario?.id"
                    @click="pedirDesactivar(u)"
                  >
                    Desactivar
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Paginacion
        :pagina-actual="paginaActual"
        :total-paginas="totalPaginas"
        @cambiar="cambiarPagina"
      />
    </div>

    <DialogoConfirmacion
      v-model:abierto="dialogoDesactivar"
      titulo="¿Desactivar este usuario?"
      :mensaje="`Se desactivará la cuenta de '${usuarioADesactivar?.name ?? ''}'. Sus tokens de sesión se revocarán y no podrá iniciar sesión.`"
      texto-confirmar="Desactivar"
      texto-cancelar="Cancelar"
      variante="danger"
      :cargando="desactivando"
      @confirmar="confirmarDesactivar"
    />
  </PanelLayout>
</template>
