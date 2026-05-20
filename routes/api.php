<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Admin\AdminReturnController;
use App\Http\Controllers\Api\Admin\AdminSuscripcionController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\Empresa\EmpresaOrderController;
use App\Http\Controllers\Api\Empresa\EmpresaProductoController;
use App\Http\Controllers\Api\Empresa\EmpresaReturnController;
use App\Http\Controllers\Api\OrderCancelController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SuscripcionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Usuario\UsuarioProductoController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

// ── Rutas públicas ──────────────────────────────────────────────────────────
// Rate limiting estricto para prevenir ataques de fuerza bruta (5 intentos/min).
Route::middleware('throttle:5,1')->group(function (): void {
    Route::post('/register',         [AuthController::class, 'register']);
    Route::post('/register/empresa', [AuthController::class, 'registerEmpresa']);
    Route::post('/login',            [AuthController::class, 'login']);
});

Route::get('/categories',         [CategoryController::class, 'index']);
Route::get('/categories/{slug}',  [CategoryController::class, 'show']);

Route::get('/products',                       [ProductController::class, 'index']);
Route::get('/products/{slug}',                [ProductController::class, 'show']);
Route::get('/products/{slug}/reviews',        [ReviewController::class, 'index']);

// Aliases en español del catálogo público.
Route::get('/productos',           [ProductController::class, 'index']);
Route::get('/productos/{slug}',    [ProductController::class, 'show']);

// ── Rutas autenticadas (token Sanctum) ──────────────────────────────────────
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user',           [UserController::class, 'profile']);
    Route::put('/user',           [UserController::class, 'update']);
    Route::put('/user/password',  [UserController::class, 'updatePassword']);

    // ── Suscripción Premium ────────────────────────────────────────────────
    Route::get('/suscripcion',          [SuscripcionController::class, 'estado']);
    Route::post('/suscripcion/activar', [SuscripcionController::class, 'activar']);
    Route::post('/suscripcion/cancelar',[SuscripcionController::class, 'cancelar']);

    // ── Carrito (comprador) ────────────────────────────────────────────────
    Route::get('/cart',                  [CartController::class, 'show']);
    Route::post('/cart/items',           [CartController::class, 'addItem']);
    Route::put('/cart/items/{id}',       [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{id}',    [CartController::class, 'removeItem']);
    Route::delete('/cart',               [CartController::class, 'clear']);

    // ── Checkout y pedidos del comprador ───────────────────────────────────
    Route::post('/checkout',                   [CheckoutController::class, 'store']);
    Route::get('/orders',                      [OrderController::class, 'index']);
    Route::get('/orders/{orderNumber}',        [OrderController::class, 'show']);
    Route::post('/orders/{orderNumber}/cancel',[OrderCancelController::class, 'cancel']);
    Route::post('/orders/{orderNumber}/return',[ReturnController::class, 'store']);

    Route::get('/mis-devoluciones',            [ReturnController::class, 'misDevoluciones']);

    // ── Reseñas (autenticadas) ─────────────────────────────────────────────
    Route::post('/products/{slug}/reviews',    [ReviewController::class, 'store']);
    Route::put('/reviews/{id}',                [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}',             [ReviewController::class, 'destroy']);

    // ── Lista de deseos ────────────────────────────────────────────────────
    Route::get('/wishlist',                    [WishlistController::class, 'index']);
    Route::post('/wishlist/{productId}',       [WishlistController::class, 'toggle']);

    // ── Productos de segunda mano del usuario particular ──────────────────
    Route::middleware('role:usuario,admin')->group(function (): void {
        Route::get('/mis-productos',                  [UsuarioProductoController::class, 'index']);
        Route::post('/mis-productos',                 [UsuarioProductoController::class, 'store']);
        Route::put('/mis-productos/{id}',             [UsuarioProductoController::class, 'update']);
        Route::delete('/mis-productos/{id}',          [UsuarioProductoController::class, 'destroy']);
        Route::post('/mis-productos/{id}/imagenes',   [UsuarioProductoController::class, 'uploadImages']);
    });

    // ── Productos y pedidos de la empresa (también accesible para admin) ──
    Route::middleware('role:empresa,admin')->prefix('empresa')->group(function (): void {
        Route::get('/productos',                     [EmpresaProductoController::class, 'index']);
        Route::post('/productos',                    [EmpresaProductoController::class, 'store']);
        Route::put('/productos/{id}',                [EmpresaProductoController::class, 'update']);
        Route::delete('/productos/{id}',             [EmpresaProductoController::class, 'destroy']);
        Route::post('/productos/{id}/imagenes',      [EmpresaProductoController::class, 'uploadImages']);

        Route::get('/pedidos',                       [EmpresaOrderController::class, 'index']);
        Route::put('/pedidos/{id}/status',           [EmpresaOrderController::class, 'updateStatus']);

        Route::get('/devoluciones',                  [EmpresaReturnController::class, 'index']);
        Route::put('/devoluciones/{id}',             [EmpresaReturnController::class, 'update']);
    });

    // ── Admin ──────────────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function (): void {
        Route::get('/dashboard',                 [AdminDashboardController::class, 'show']);

        Route::get('/users',                     [AdminUserController::class, 'index']);
        Route::put('/users/{id}/role',           [AdminUserController::class, 'updateRole']);
        Route::delete('/users/{id}',             [AdminUserController::class, 'destroy']);

        Route::get('/products',                  [AdminProductController::class, 'index']);
        Route::put('/products/{id}/toggle',      [AdminProductController::class, 'toggle']);
        Route::delete('/products/{id}',          [AdminProductController::class, 'destroy']);

        Route::get('/orders',                    [AdminOrderController::class, 'index']);
        Route::put('/orders/{id}/status',        [AdminOrderController::class, 'updateStatus']);

        Route::get('/devoluciones',              [AdminReturnController::class, 'index']);
        Route::put('/devoluciones/{id}',         [AdminReturnController::class, 'update']);

        Route::get('/suscripciones',             [AdminSuscripcionController::class, 'index']);
    });
});
