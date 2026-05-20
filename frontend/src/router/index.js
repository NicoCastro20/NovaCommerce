import { createRouter, createWebHistory } from 'vue-router'
import { authGuard, guestGuard, roleGuard } from './guards'

// Páginas públicas
const Inicio           = () => import('@/views/Inicio.vue')
const Catalogo         = () => import('@/views/Catalogo.vue')
const Categoria        = () => import('@/views/Categoria.vue')
const DetalleProducto  = () => import('@/views/DetalleProducto.vue')
const Carrito          = () => import('@/views/Carrito.vue')
const Contacto         = () => import('@/views/Contacto.vue')
const Login            = () => import('@/views/Login.vue')
const Registro         = () => import('@/views/Registro.vue')
const RegistroEmpresa  = () => import('@/views/RegistroEmpresa.vue')
const Premium          = () => import('@/views/Premium.vue')
const NoEncontrado     = () => import('@/views/NoEncontrado.vue')

// Cuenta (auth)
const Checkout              = () => import('@/views/Checkout.vue')
const Cuenta                = () => import('@/views/cuenta/Cuenta.vue')
const MisPedidos            = () => import('@/views/cuenta/MisPedidos.vue')
const DetallePedido         = () => import('@/views/cuenta/DetallePedido.vue')
const MisDevoluciones       = () => import('@/views/cuenta/MisDevoluciones.vue')
const ListaDeseos           = () => import('@/views/cuenta/ListaDeseos.vue')
const MisProductos          = () => import('@/views/cuenta/MisProductos.vue')
const FormularioMiProducto  = () => import('@/views/cuenta/FormularioMiProducto.vue')

// Empresa
const EmpresaDashboard           = () => import('@/views/empresa/EmpresaDashboard.vue')
const EmpresaProductos           = () => import('@/views/empresa/EmpresaProductos.vue')
const EmpresaFormularioProducto  = () => import('@/views/empresa/EmpresaFormularioProducto.vue')
const EmpresaPedidos             = () => import('@/views/empresa/EmpresaPedidos.vue')
const EmpresaDevoluciones        = () => import('@/views/empresa/EmpresaDevoluciones.vue')

// Admin
const AdminDashboard      = () => import('@/views/admin/AdminDashboard.vue')
const AdminProductos      = () => import('@/views/admin/AdminProductos.vue')
const AdminUsuarios       = () => import('@/views/admin/AdminUsuarios.vue')
const AdminPedidos        = () => import('@/views/admin/AdminPedidos.vue')
const AdminDevoluciones   = () => import('@/views/admin/AdminDevoluciones.vue')

const DESCRIPCION_DEFECTO =
  'NovaCommerce: marketplace en español con miles de productos, vendedores verificados y envíos rápidos a toda España.'

const rutas = [
  // ── Públicas ──────────────────────────────────────────────────────────────
  { path: '/',                     name: 'inicio',           component: Inicio,           meta: { titulo: 'Inicio',      descripcion: 'Descubre miles de productos de tecnología, moda, hogar y deportes en NovaCommerce. Compra segura, envío rápido y valoraciones reales.' } },
  { path: '/catalogo',             name: 'catalogo',         component: Catalogo,         meta: { titulo: 'Catálogo',    descripcion: 'Explora todo el catálogo de NovaCommerce. Filtra por categoría, precio o valoración y encuentra justo lo que buscas.' } },
  { path: '/catalogo/:slug',       name: 'categoria',        component: Categoria,        meta: { titulo: 'Categoría',   descripcion: 'Productos por categoría en NovaCommerce.' } },
  { path: '/producto/:slug',       name: 'producto',         component: DetalleProducto,  meta: { titulo: 'Producto' } },
  { path: '/carrito',              name: 'carrito',          component: Carrito,          meta: { titulo: 'Carrito',     descripcion: 'Revisa los productos de tu carrito antes de finalizar la compra.' } },
  { path: '/contacto',             name: 'contacto',         component: Contacto,         meta: { titulo: 'Contacto',    descripcion: 'Ponte en contacto con el equipo de NovaCommerce. Resolvemos tus dudas en menos de 24 horas.' } },
  { path: '/premium',              name: 'premium',          component: Premium,          meta: { titulo: 'NovaCommerce Premium', descripcion: 'Envíos gratis y prioritarios en todos tus pedidos por solo 50 €/año con NovaCommerce Premium.' } },

  // ── Solo invitados ────────────────────────────────────────────────────────
  { path: '/login',                name: 'login',            component: Login,            meta: { titulo: 'Iniciar sesión', descripcion: 'Accede a tu cuenta de NovaCommerce.' }, beforeEnter: guestGuard },
  { path: '/registro',             name: 'registro',         component: Registro,         meta: { titulo: 'Crear cuenta',   descripcion: 'Crea tu cuenta de usuario en NovaCommerce y compra o vende artículos entre particulares.' }, beforeEnter: guestGuard },
  { path: '/registro/empresa',     name: 'registro-empresa', component: RegistroEmpresa,  meta: { titulo: 'Registrar empresa', descripcion: 'Da de alta tu empresa en NovaCommerce y vende productos nuevos a miles de compradores.' }, beforeEnter: guestGuard },

  // ── Auth ──────────────────────────────────────────────────────────────────
  { path: '/checkout',                       name: 'checkout',         component: Checkout,            meta: { titulo: 'Finalizar compra' }, beforeEnter: authGuard },
  { path: '/mi-cuenta',                      name: 'mi-cuenta',        component: Cuenta,              meta: { titulo: 'Mi cuenta' },        beforeEnter: authGuard },
  { path: '/mi-cuenta/pedidos',              name: 'mis-pedidos',      component: MisPedidos,          meta: { titulo: 'Mis pedidos' },      beforeEnter: authGuard },
  { path: '/mi-cuenta/pedidos/:number',      name: 'pedido-detalle',   component: DetallePedido,       meta: { titulo: 'Detalle del pedido' }, beforeEnter: authGuard },
  { path: '/mi-cuenta/devoluciones',         name: 'mis-devoluciones', component: MisDevoluciones,     meta: { titulo: 'Mis devoluciones' }, beforeEnter: authGuard },
  { path: '/mi-cuenta/wishlist',             name: 'wishlist',         component: ListaDeseos,         meta: { titulo: 'Lista de deseos' },  beforeEnter: authGuard },

  // ── Productos publicados por el usuario particular ───────────────────────
  { path: '/mis-productos',                 name: 'mis-productos',         component: MisProductos,           meta: { titulo: 'Mis productos en venta' }, beforeEnter: roleGuard(['usuario', 'admin']) },
  { path: '/mis-productos/nuevo',           name: 'mis-producto-nuevo',    component: FormularioMiProducto,   meta: { titulo: 'Publicar producto' },      beforeEnter: roleGuard(['usuario', 'admin']) },
  { path: '/mis-productos/:id/editar',      name: 'mis-producto-editar',   component: FormularioMiProducto,   meta: { titulo: 'Editar producto en venta' }, beforeEnter: roleGuard(['usuario', 'admin']) },

  // ── Empresa (empresa o admin) ─────────────────────────────────────────────
  { path: '/empresa',                       name: 'empresa',                  component: EmpresaDashboard,           meta: { titulo: 'Panel de empresa' },   beforeEnter: roleGuard(['empresa', 'admin']) },
  { path: '/empresa/productos',             name: 'empresa-productos',        component: EmpresaProductos,           meta: { titulo: 'Mis productos' },      beforeEnter: roleGuard(['empresa', 'admin']) },
  { path: '/empresa/productos/nuevo',       name: 'empresa-producto-nuevo',   component: EmpresaFormularioProducto,  meta: { titulo: 'Nuevo producto' },     beforeEnter: roleGuard(['empresa', 'admin']) },
  { path: '/empresa/productos/:id/editar',  name: 'empresa-producto-editar',  component: EmpresaFormularioProducto,  meta: { titulo: 'Editar producto' },    beforeEnter: roleGuard(['empresa', 'admin']) },
  { path: '/empresa/pedidos',               name: 'empresa-pedidos',          component: EmpresaPedidos,             meta: { titulo: 'Pedidos recibidos' },  beforeEnter: roleGuard(['empresa', 'admin']) },
  { path: '/empresa/devoluciones',          name: 'empresa-devoluciones',     component: EmpresaDevoluciones,        meta: { titulo: 'Devoluciones' },        beforeEnter: roleGuard(['empresa', 'admin']) },

  // ── Admin ─────────────────────────────────────────────────────────────────
  { path: '/admin',                  name: 'admin',              component: AdminDashboard,     meta: { titulo: 'Panel admin' },  beforeEnter: roleGuard('admin') },
  { path: '/admin/productos',        name: 'admin-productos',    component: AdminProductos,     meta: { titulo: 'Productos (admin)' }, beforeEnter: roleGuard('admin') },
  { path: '/admin/usuarios',         name: 'admin-usuarios',     component: AdminUsuarios,      meta: { titulo: 'Usuarios (admin)' },  beforeEnter: roleGuard('admin') },
  { path: '/admin/pedidos',          name: 'admin-pedidos',      component: AdminPedidos,       meta: { titulo: 'Pedidos (admin)' },   beforeEnter: roleGuard('admin') },
  { path: '/admin/devoluciones',     name: 'admin-devoluciones', component: AdminDevoluciones,  meta: { titulo: 'Devoluciones (admin)' }, beforeEnter: roleGuard('admin') },

  // ── 404 ────────────────────────────────────────────────────────────────────
  { path: '/:pathMatch(.*)*',        name: 'no-encontrado',   component: NoEncontrado,      meta: { titulo: 'Página no encontrada' } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: rutas,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    return { top: 0 }
  },
})

function obtenerOCrearMeta(nombre, atributo = 'name') {
  let etiqueta = document.head.querySelector(`meta[${atributo}="${nombre}"]`)
  if (!etiqueta) {
    etiqueta = document.createElement('meta')
    etiqueta.setAttribute(atributo, nombre)
    document.head.appendChild(etiqueta)
  }
  return etiqueta
}

router.afterEach((to) => {
  const titulo = to.meta?.titulo
  const tituloFinal = titulo ? `${titulo} · NovaCommerce` : 'NovaCommerce'
  document.title = tituloFinal

  const descripcion = to.meta?.descripcion ?? DESCRIPCION_DEFECTO
  obtenerOCrearMeta('description').setAttribute('content', descripcion)
  obtenerOCrearMeta('og:title', 'property').setAttribute('content', tituloFinal)
  obtenerOCrearMeta('og:description', 'property').setAttribute('content', descripcion)
  obtenerOCrearMeta('og:type', 'property').setAttribute('content', 'website')
  obtenerOCrearMeta('og:locale', 'property').setAttribute('content', 'es_ES')
})

export default router
