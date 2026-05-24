<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest as ReturnModel;
use App\Models\User;
use Database\Seeders\CategoriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cobertura del sistema de devoluciones automáticas dentro del plazo legal
 * de 14 días. Tras este flujo, al solicitar una devolución se aprueba al
 * instante, se restaura el stock y el pedido pasa a 'devuelto'. Empresa y
 * admin sólo tienen vistas de consulta (no resuelven).
 */
class DevolucionesTest extends TestCase
{
    use RefreshDatabase;

    private User $comprador;
    private User $empresa;
    private User $admin;
    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategoriesSeeder::class);

        $this->comprador = User::create([
            'name'     => 'Comprador',
            'email'    => 'comprador@test.com',
            'password' => bcrypt('Password1'),
            'role'     => 'usuario',
        ]);

        $this->empresa = User::create([
            'name'         => 'Empresa',
            'email'        => 'empresa@test.com',
            'password'     => bcrypt('Password1'),
            'role'         => 'empresa',
            'company_name' => 'Empresa S.L.',
            'nif_cif'      => 'B12345678',
        ]);

        $this->admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => bcrypt('Password1'),
            'role'     => 'admin',
        ]);

        $this->producto = Product::create([
            'user_id'     => $this->empresa->id,
            'category_id' => \App\Models\Category::first()->id,
            'name'        => 'Producto Devolución',
            'slug'        => 'producto-devolucion',
            'description' => 'desc',
            'price'       => 50,
            'stock'       => 10,
            'is_active'   => true,
            'type'        => 'nuevo',
        ]);
    }

    /**
     * Pedido entregado dentro de la ventana de 14 días con un item de la empresa.
     */
    private function crearPedidoEntregado(int $diasDesdeEntrega = 1, int $cantidad = 1): Order
    {
        $pedido = Order::create([
            'user_id'              => $this->comprador->id,
            'status'               => 'entregado',
            'subtotal'             => $this->producto->price * $cantidad,
            'shipping_cost'        => 0,
            'total'                => $this->producto->price * $cantidad,
            'shipping_address'     => 'Calle 1',
            'shipping_city'        => 'Madrid',
            'shipping_postal_code' => '28013',
            'shipping_country'     => 'España',
            'payment_method'       => 'tarjeta',
            'delivered_at'         => now()->subDays($diasDesdeEntrega),
        ]);

        OrderItem::create([
            'order_id'      => $pedido->id,
            'product_id'    => $this->producto->id,
            'seller_id'     => $this->empresa->id,
            'product_name'  => $this->producto->name,
            'product_price' => $this->producto->price,
            'quantity'      => $cantidad,
            'subtotal'      => $this->producto->price * $cantidad,
        ]);

        return $pedido->fresh(['items']);
    }

    // ── Comprador: solicitar (aprobación automática) ────────────────────────

    public function test_solicitar_devolucion_dentro_del_plazo_aprueba_automaticamente(): void
    {
        Sanctum::actingAs($this->comprador);
        $pedido = $this->crearPedidoEntregado(diasDesdeEntrega: 3, cantidad: 2);
        $stockInicial = $this->producto->fresh()->stock;

        $r = $this->postJson("/api/orders/{$pedido->order_number}/return", [
            'reason'      => 'defectuoso',
            'description' => 'Llegó sin funcionar.',
        ]);

        $r->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.return.status', 'aprobada')
            ->assertJsonPath('data.return.reason', 'defectuoso');

        $this->assertDatabaseHas('returns', [
            'order_id' => $pedido->id,
            'user_id'  => $this->comprador->id,
            'reason'   => 'defectuoso',
            'status'   => 'aprobada',
        ]);

        $devolucion = ReturnModel::where('order_id', $pedido->id)->first();
        $this->assertNotNull($devolucion->resolved_at);

        // Pedido en 'devuelto' y stock restaurado.
        $this->assertSame('devuelto', $pedido->fresh()->status);
        $this->assertSame($stockInicial + 2, $this->producto->fresh()->stock);
    }

    public function test_solicitar_devolucion_acepta_motivos_genericos_y_comentarios_opcionales(): void
    {
        Sanctum::actingAs($this->comprador);
        $pedido = $this->crearPedidoEntregado();

        $this->postJson("/api/orders/{$pedido->order_number}/return", [
            'reason' => 'cambio_opinion',
        ])
            ->assertCreated()
            ->assertJsonPath('data.return.status', 'aprobada')
            ->assertJsonPath('data.return.reason', 'cambio_opinion');
    }

    public function test_no_se_puede_solicitar_devolucion_de_pedido_no_entregado(): void
    {
        Sanctum::actingAs($this->comprador);
        $pedido = Order::create([
            'user_id'              => $this->comprador->id,
            'status'               => 'pendiente',
            'subtotal'             => 50,
            'shipping_cost'        => 0,
            'total'                => 50,
            'shipping_address'     => 'Calle 1',
            'shipping_city'        => 'Madrid',
            'shipping_postal_code' => '28013',
            'shipping_country'     => 'España',
            'payment_method'       => 'tarjeta',
        ]);

        $this->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'otro'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('returns', 0);
    }

    public function test_no_se_puede_solicitar_devolucion_fuera_del_plazo_de_14_dias(): void
    {
        Sanctum::actingAs($this->comprador);
        $pedido = $this->crearPedidoEntregado(diasDesdeEntrega: 20);

        $this->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'otro'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El plazo de devolución de 14 días ha expirado.');

        $this->assertDatabaseCount('returns', 0);
    }

    public function test_no_se_puede_solicitar_dos_devoluciones_para_el_mismo_pedido(): void
    {
        Sanctum::actingAs($this->comprador);
        $pedido = $this->crearPedidoEntregado();

        $this->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'otro'])
            ->assertCreated();

        $this->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'defectuoso'])
            ->assertStatus(422);

        $this->assertDatabaseCount('returns', 1);
    }

    public function test_no_se_puede_devolver_pedido_ajeno(): void
    {
        $pedido = $this->crearPedidoEntregado();
        $otro = User::create([
            'name'     => 'Otro',
            'email'    => 'otro@test.com',
            'password' => bcrypt('Password1'),
            'role'     => 'usuario',
        ]);

        Sanctum::actingAs($otro);
        $this->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'otro'])
            ->assertStatus(404);
    }

    public function test_motivo_no_listado_es_rechazado_con_422(): void
    {
        Sanctum::actingAs($this->comprador);
        $pedido = $this->crearPedidoEntregado();

        $this->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'inventado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_comprador_lista_sus_devoluciones(): void
    {
        Sanctum::actingAs($this->comprador);
        $pedido = $this->crearPedidoEntregado();

        $this->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'defectuoso'])
            ->assertCreated();

        $this->getJson('/api/mis-devoluciones')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'aprobada');
    }

    public function test_orders_expone_return_days_left_y_can_be_returned(): void
    {
        Sanctum::actingAs($this->comprador);
        $this->crearPedidoEntregado(diasDesdeEntrega: 4);

        $r = $this->getJson('/api/orders');

        $r->assertOk()
            ->assertJsonPath('data.0.can_be_returned', true);

        $dias = $r->json('data.0.return_days_left');
        $this->assertIsInt($dias);
        // 14 - 4 = 10 días restantes (con tolerancia por redondeo).
        $this->assertEqualsWithDelta(10, $dias, 1);
    }

    // ── Empresa y admin: sólo lectura ───────────────────────────────────────

    public function test_empresa_solo_ve_devoluciones_de_sus_pedidos(): void
    {
        // Devolución de un pedido SUYO (aprobada directamente vía modelo).
        $miPedido = $this->crearPedidoEntregado();
        ReturnModel::create([
            'order_id'    => $miPedido->id,
            'user_id'     => $this->comprador->id,
            'reason'      => 'otro',
            'status'      => 'aprobada',
            'resolved_at' => now(),
        ]);

        // Devolución de un pedido AJENO.
        $otraEmpresa = User::create([
            'name'         => 'Otra',
            'email'        => 'otra@test.com',
            'password'     => bcrypt('Password1'),
            'role'         => 'empresa',
            'company_name' => 'O S.L.',
            'nif_cif'      => 'B00000099',
        ]);
        $prodAjeno = Product::create([
            'user_id' => $otraEmpresa->id, 'category_id' => \App\Models\Category::first()->id,
            'name' => 'X', 'slug' => 'x', 'description' => 'd', 'price' => 1, 'stock' => 1,
            'is_active' => true, 'type' => 'nuevo',
        ]);
        $pedidoAjeno = Order::create([
            'user_id' => $this->comprador->id, 'status' => 'devuelto', 'subtotal' => 1,
            'shipping_cost' => 0, 'total' => 1, 'shipping_address' => 'a', 'shipping_city' => 'm',
            'shipping_postal_code' => '28000', 'shipping_country' => 'España',
            'payment_method' => 'tarjeta', 'delivered_at' => now()->subDay(),
        ]);
        OrderItem::create([
            'order_id' => $pedidoAjeno->id, 'product_id' => $prodAjeno->id, 'seller_id' => $otraEmpresa->id,
            'product_name' => 'X', 'product_price' => 1, 'quantity' => 1, 'subtotal' => 1,
        ]);
        ReturnModel::create([
            'order_id' => $pedidoAjeno->id, 'user_id' => $this->comprador->id,
            'reason' => 'otro', 'status' => 'aprobada', 'resolved_at' => now(),
        ]);

        Sanctum::actingAs($this->empresa);
        $this->getJson('/api/empresa/devoluciones')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_usuario_particular_no_puede_acceder_a_devoluciones_empresa(): void
    {
        Sanctum::actingAs($this->comprador);
        $this->getJson('/api/empresa/devoluciones')->assertForbidden();
    }

    public function test_admin_lista_todas_las_devoluciones(): void
    {
        $pedido = $this->crearPedidoEntregado();
        ReturnModel::create([
            'order_id'    => $pedido->id,
            'user_id'     => $this->comprador->id,
            'reason'      => 'otro',
            'status'      => 'aprobada',
            'resolved_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/devoluciones')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'aprobada');
    }

    public function test_endpoints_de_resolucion_manual_ya_no_existen(): void
    {
        Sanctum::actingAs($this->admin);

        // Ya no hay rutas PUT — Laravel devuelve 404 (no hay ruta para ese verbo).
        $this->putJson('/api/admin/devoluciones/1', ['action' => 'aprobar'])
            ->assertStatus(404);

        $this->putJson('/api/empresa/devoluciones/1', ['action' => 'aprobar'])
            ->assertStatus(404);
    }
}
