<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import BarraProgreso from '@/components/BarraProgreso.vue'
import { useAutenticacionStore } from '@/stores/autenticacion'
import { useCarritoStore } from '@/stores/carrito'
import { useNotificacionesStore } from '@/stores/notificaciones'
import { useProgreso } from '@/composables/useProgreso'
import { registrarManejador401 } from '@/api'

const auth = useAutenticacionStore()
const carrito = useCarritoStore()
const notificaciones = useNotificacionesStore()
const router = useRouter()
const progreso = useProgreso()

// Inyectar router, cerrar sesión y notificación en el interceptor 401/error de api.
registrarManejador401({
  router,
  logout: (opciones) => auth.cerrarSesion(opciones),
  toastError: (mensaje) => notificaciones.error(mensaje),
})

// Barra de progreso superior durante la navegación entre rutas.
router.beforeEach(() => {
  progreso.iniciar()
})
router.afterEach(() => {
  progreso.terminar()
})
router.onError(() => {
  progreso.fallar()
})

onMounted(async () => {
  if (auth.token) {
    await auth.obtenerUsuario()
    if (auth.estaAutenticado) {
      carrito.obtenerCarrito()
    }
  }
})
</script>

<template>
  <BarraProgreso />
  <AppLayout>
    <RouterView v-slot="{ Component, route }">
      <Transition
        name="fade-pagina"
        mode="out-in"
      >
        <component :is="Component" :key="route.fullPath" />
      </Transition>
    </RouterView>
  </AppLayout>
  <ToastContainer />
</template>

<style>
.fade-pagina-enter-active,
.fade-pagina-leave-active {
  transition: opacity 180ms ease, transform 180ms ease;
}
.fade-pagina-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.fade-pagina-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
