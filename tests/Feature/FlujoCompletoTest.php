<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los tres flujos críticos descritos en las tareas finales:
 *
 *   1. Cliente: registro → login → catálogo → carrito → checkout → pedido.
 *   2. Vendedor: registro → login → publicar producto → ver en catálogo.
 *   3. Admin: dashboard → gestión de productos, usuarios y pedidos.
 */
class FlujoCompletoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\CategoriesSeeder::class);
    }

    public function test_flujo_completo_del_cliente(): void
    {
        // ── Registro ─────────────────────────────────────────────────────
        $registro = $this->postJson('/api/register', [
            'name'                  => 'Usuario Demo',
            'email'                 => 'usuario.demo@novacommerce.com',
            'password'              => 'Password1',
            'password_confirmation' => 'Password1',
        ]);
        $registro->assertCreated()->assertJsonPath('success', true);
        $tokenRegistro = $registro->json('data.token');
        $this->assertNotEmpty($tokenRegistro);

        // ── Login ────────────────────────────────────────────────────────
        $login = $this->postJson('/api/login', [
            'email'    => 'usuario.demo@novacommerce.com',
            'password' => 'Password1',
        ]);
        $login->assertOk();
        $token = $login->json('data.token');

        // ── Crear empresa + producto para el catálogo ────────────────────
        $empresa = User::create([
            'name'         => 'Empresa Demo',
            'email'        => 'empresa.demo@novacommerce.com',
            'password'     => bcrypt('Password1'),
            'role'         => 'empresa',
            'company_name' => 'Demo S.L.',
            'nif_cif'      => 'B11111111',
        ]);
        $producto = Product::create([
            'user_id'     => $empresa->id,
            'category_id' => \App\Models\Category::first()->id,
            'name'        => 'Producto E2E',
            'slug'        => 'producto-e2e',
            'description' => 'Descripción de prueba.',
            'price'       => 25.00,
            'stock'       => 10,
            'is_active'   => true,
            'type'        => 'nuevo',
        ]);

        // ── Catálogo público ─────────────────────────────────────────────
        $catalogo = $this->getJson('/api/products');
        $catalogo->assertOk()->assertJsonFragment(['name' => 'Producto E2E']);

        // ── Añadir al carrito (autenticado) ──────────────────────────────
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/cart/items', [
                'product_id' => $producto->id,
                'quantity'   => 2,
            ])
            ->assertSuccessful();

        $carrito = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/cart');
        $carrito->assertOk()->assertJsonPath('data.cart.items.0.quantity', 2);

        // ── Checkout ─────────────────────────────────────────────────────
        $checkout = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/checkout', [
                'shipping_address'     => 'Calle Mayor 1',
                'shipping_city'        => 'Madrid',
                'shipping_postal_code' => '28013',
                'payment_method'       => 'tarjeta',
            ]);
        $checkout->assertCreated();
        $orderNumber = $checkout->json('data.order.order_number');
        $this->assertNotEmpty($orderNumber);

        // ── Ver pedido ───────────────────────────────────────────────────
        $detalle = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/orders/{$orderNumber}");
        $detalle->assertOk()->assertJsonPath('data.order.order_number', $orderNumber);
    }

    public function test_flujo_del_vendedor(): void
    {
        // Registro como empresa
        $registro = $this->postJson('/api/register/empresa', [
            'name'                  => 'Empresa Test',
            'email'                 => 'empresa.test@novacommerce.com',
            'password'              => 'Password1',
            'password_confirmation' => 'Password1',
            'company_name'          => 'Empresa Test S.L.',
            'nif_cif'               => 'B22222222',
        ]);
        $registro->assertCreated();
        $token = $registro->json('data.token');

        // Publicar producto
        $publicar = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/empresa/productos', [
                'name'        => 'Producto de Empresa',
                'description' => 'Producto creado en test.',
                'price'       => 49.50,
                'stock'       => 5,
                'category_id' => \App\Models\Category::first()->id,
            ]);
        $publicar->assertCreated()->assertJsonPath('data.product.name', 'Producto de Empresa');

        // Aparecer en el catálogo público
        $catalogo = $this->getJson('/api/products');
        $catalogo->assertOk()->assertJsonFragment(['name' => 'Producto de Empresa']);

        // Una empresa no puede editar productos de otra empresa
        $otra = User::create([
            'name'         => 'Otra Empresa',
            'email'        => 'otra@novacommerce.com',
            'password'     => bcrypt('Password1'),
            'role'         => 'empresa',
            'company_name' => 'Otra S.L.',
            'nif_cif'      => 'B33333333',
        ]);
        $productoAjeno = Product::create([
            'user_id'     => $otra->id,
            'category_id' => \App\Models\Category::first()->id,
            'name'        => 'Producto Ajeno',
            'slug'        => 'producto-ajeno',
            'description' => '...',
            'price'       => 10,
            'stock'       => 1,
            'is_active'   => true,
            'type'        => 'nuevo',
        ]);
        $intentoEdicion = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/empresa/productos/{$productoAjeno->id}", [
                'name' => 'Cambiado',
            ]);
        $intentoEdicion->assertForbidden();
    }

    public function test_flujo_del_admin(): void
    {
        $admin = User::create([
            'name'     => 'Admin Test',
            'email'    => 'admin.test@novacommerce.com',
            'password' => bcrypt('Password1'),
            'role'     => 'admin',
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        // Generar algo de actividad para el dashboard
        $empresa = User::create([
            'name'         => 'Empresa',
            'email'        => 'empresa@x.com',
            'password'     => bcrypt('Password1'),
            'role'         => 'empresa',
            'company_name' => 'V S.L.',
            'nif_cif'      => 'B44444444',
        ]);
        $usuario = User::create([
            'name'     => 'Usuario',
            'email'    => 'usu@x.com',
            'password' => bcrypt('Password1'),
            'role'     => 'usuario',
        ]);
        $producto = Product::create([
            'user_id'     => $empresa->id,
            'category_id' => \App\Models\Category::first()->id,
            'name'        => 'Prod Admin',
            'slug'        => 'prod-admin',
            'description' => 'desc',
            'price'       => 100,
            'stock'       => 3,
            'is_active'   => true,
            'type'        => 'nuevo',
        ]);
        $pedido = Order::create([
            'user_id'              => $usuario->id,
            'order_number'         => 'NC-TEST0001',
            'status'               => 'pendiente',
            'subtotal'             => 100,
            'shipping_cost'        => 0,
            'total'                => 100,
            'shipping_address'     => 'Calle 1',
            'shipping_city'        => 'Madrid',
            'shipping_postal_code' => '28000',
            'shipping_country'     => 'España',
            'payment_method'       => 'tarjeta',
        ]);
        OrderItem::create([
            'order_id'      => $pedido->id,
            'product_id'    => $producto->id,
            'seller_id'     => $empresa->id,
            'product_name'  => $producto->name,
            'product_price' => 100,
            'quantity'      => 1,
            'subtotal'      => 100,
        ]);

        $auth = ['Authorization' => "Bearer {$token}"];

        // Dashboard
        $dashboard = $this->withHeaders($auth)->getJson('/api/admin/dashboard');
        $dashboard->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_users', 'total_products', 'total_orders',
                    'total_revenue', 'orders_by_status', 'recent_orders',
                    'top_products', 'sales_last_30_days', 'new_users_last_30_days',
                ],
            ]);

        // Productos admin: filtros
        $productosAdmin = $this->withHeaders($auth)->getJson('/api/admin/products?seller_id=' . $empresa->id);
        $productosAdmin->assertOk()->assertJsonFragment(['name' => 'Prod Admin']);

        // Toggle producto
        $this->withHeaders($auth)->putJson("/api/admin/products/{$producto->id}/toggle")->assertOk();
        $this->assertFalse($producto->fresh()->is_active);

        // Usuarios: cambio de rol
        $this->withHeaders($auth)->putJson("/api/admin/users/{$usuario->id}/role", ['role' => 'empresa'])
            ->assertOk();
        $this->assertSame('empresa', $usuario->fresh()->role);

        // Cambiar estado de pedido
        $this->withHeaders($auth)->putJson("/api/admin/orders/{$pedido->id}/status", ['status' => 'enviado'])
            ->assertOk();
        $this->assertSame('enviado', $pedido->fresh()->status);
    }

    public function test_rate_limiting_en_login(): void
    {
        // 5 intentos permitidos
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email'    => 'noexiste@x.com',
                'password' => 'wrong',
            ])->assertStatus(401);
        }

        // El 6º debe ser bloqueado por el throttle (429)
        $this->postJson('/api/login', [
            'email'    => 'noexiste@x.com',
            'password' => 'wrong',
        ])->assertStatus(429)
          ->assertJsonPath('success', false);
    }
}
