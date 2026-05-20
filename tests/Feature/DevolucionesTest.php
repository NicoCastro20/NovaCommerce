<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest as ReturnModel;
use App\Models\User;
use Database\Seeders\CategoriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobertura del sistema de devoluciones (Return) introducido en
 * 2026_05_07_000001_crear_sistema_devoluciones.
 *
 * Cubre el flujo del comprador (solicitar), de la empresa vendedora (resolver
 * solo si el pedido contiene productos suyos) y del admin (resolver cualquiera),
 * más las reglas de ventana de 14 días, no duplicar y restauración de stock.
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

    private function tokenDe(User $u): string
    {
        return $u->createToken('test')->plainTextToken;
    }

    // ── Comprador: solicitar ────────────────────────────────────────────────

    public function test_comprador_puede_solicitar_devolucion_de_pedido_entregado(): void
    {
        $pedido = $this->crearPedidoEntregado();

        $r = $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->comprador))
            ->postJson("/api/orders/{$pedido->order_number}/return", [
                'reason'      => 'producto_defectuoso',
                'description' => 'No enciende al sacarlo de la caja.',
            ]);

        $r->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.return.status', 'solicitada')
            ->assertJsonPath('data.return.reason', 'producto_defectuoso');

        $this->assertDatabaseHas('returns', [
            'order_id' => $pedido->id,
            'user_id'  => $this->comprador->id,
            'reason'   => 'producto_defectuoso',
            'status'   => 'solicitada',
        ]);
    }

    public function test_comprador_no_puede_solicitar_devolucion_de_pedido_pendiente(): void
    {
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

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->comprador))
            ->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'otro'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('returns', 0);
    }

    public function test_comprador_no_puede_solicitar_devolucion_fuera_de_la_ventana_de_14_dias(): void
    {
        $pedido = $this->crearPedidoEntregado(diasDesdeEntrega: 20);

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->comprador))
            ->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'otro'])
            ->assertStatus(422);

        $this->assertDatabaseCount('returns', 0);
    }

    public function test_comprador_no_puede_solicitar_dos_devoluciones_para_el_mismo_pedido(): void
    {
        $pedido = $this->crearPedidoEntregado();
        ReturnModel::create([
            'order_id' => $pedido->id,
            'user_id'  => $this->comprador->id,
            'reason'   => 'otro',
            'status'   => 'solicitada',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->comprador))
            ->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'producto_dañado'])
            ->assertStatus(422);

        $this->assertDatabaseCount('returns', 1);
    }

    public function test_comprador_no_puede_devolver_pedido_ajeno(): void
    {
        $pedido = $this->crearPedidoEntregado();
        $otro = User::create([
            'name'     => 'Otro',
            'email'    => 'otro@test.com',
            'password' => bcrypt('Password1'),
            'role'     => 'usuario',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($otro))
            ->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'otro'])
            ->assertStatus(404);
    }

    public function test_motivo_invalido_es_rechazado(): void
    {
        $pedido = $this->crearPedidoEntregado();

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->comprador))
            ->postJson("/api/orders/{$pedido->order_number}/return", ['reason' => 'razon_falsa'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_comprador_lista_sus_devoluciones(): void
    {
        $pedido = $this->crearPedidoEntregado();
        ReturnModel::create([
            'order_id' => $pedido->id,
            'user_id'  => $this->comprador->id,
            'reason'   => 'producto_defectuoso',
            'status'   => 'solicitada',
        ]);

        $r = $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->comprador))
            ->getJson('/api/mis-devoluciones');

        $r->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    // ── Empresa: aprobar/rechazar ───────────────────────────────────────────

    public function test_empresa_aprueba_devolucion_y_restaura_stock_y_cambia_estado(): void
    {
        $pedido = $this->crearPedidoEntregado(cantidad: 3);
        $stockInicial = $this->producto->fresh()->stock;
        $devolucion = ReturnModel::create([
            'order_id' => $pedido->id,
            'user_id'  => $this->comprador->id,
            'reason'   => 'otro',
            'status'   => 'solicitada',
        ]);

        $r = $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->empresa))
            ->putJson("/api/empresa/devoluciones/{$devolucion->id}", [
                'action'      => 'aprobar',
                'admin_notes' => 'OK, defecto comprobado.',
            ]);

        $r->assertOk()
            ->assertJsonPath('data.return.status', 'aprobada');

        $this->assertSame($stockInicial + 3, $this->producto->fresh()->stock);
        $this->assertSame('devuelto', $pedido->fresh()->status);
        $this->assertNotNull($devolucion->fresh()->resolved_at);
    }

    public function test_empresa_puede_rechazar_devolucion(): void
    {
        $pedido = $this->crearPedidoEntregado();
        $stockInicial = $this->producto->fresh()->stock;
        $devolucion = ReturnModel::create([
            'order_id' => $pedido->id,
            'user_id'  => $this->comprador->id,
            'reason'   => 'otro',
            'status'   => 'solicitada',
        ]);

        $r = $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->empresa))
            ->putJson("/api/empresa/devoluciones/{$devolucion->id}", [
                'action' => 'rechazar',
                'admin_notes' => 'Sin pruebas suficientes.',
            ]);

        $r->assertOk()
            ->assertJsonPath('data.return.status', 'rechazada');

        // Rechazar NO mueve stock ni cambia el estado del pedido.
        $this->assertSame($stockInicial, $this->producto->fresh()->stock);
        $this->assertSame('entregado', $pedido->fresh()->status);
    }

    public function test_empresa_no_puede_modificar_devolucion_de_otra_empresa(): void
    {
        $otraEmpresa = User::create([
            'name'         => 'Otra Empresa',
            'email'        => 'otra@test.com',
            'password'     => bcrypt('Password1'),
            'role'         => 'empresa',
            'company_name' => 'Otra S.L.',
            'nif_cif'      => 'B99999999',
        ]);
        $productoAjeno = Product::create([
            'user_id'     => $otraEmpresa->id,
            'category_id' => \App\Models\Category::first()->id,
            'name'        => 'Ajeno',
            'slug'        => 'ajeno',
            'description' => 'd',
            'price'       => 30,
            'stock'       => 5,
            'is_active'   => true,
            'type'        => 'nuevo',
        ]);
        $pedido = Order::create([
            'user_id'              => $this->comprador->id,
            'status'               => 'entregado',
            'subtotal'             => 30,
            'shipping_cost'        => 0,
            'total'                => 30,
            'shipping_address'     => 'C1',
            'shipping_city'        => 'M',
            'shipping_postal_code' => '28000',
            'shipping_country'     => 'España',
            'payment_method'       => 'tarjeta',
            'delivered_at'         => now()->subDay(),
        ]);
        OrderItem::create([
            'order_id'      => $pedido->id,
            'product_id'    => $productoAjeno->id,
            'seller_id'     => $otraEmpresa->id,
            'product_name'  => $productoAjeno->name,
            'product_price' => 30,
            'quantity'      => 1,
            'subtotal'      => 30,
        ]);
        $devolucion = ReturnModel::create([
            'order_id' => $pedido->id,
            'user_id'  => $this->comprador->id,
            'reason'   => 'otro',
            'status'   => 'solicitada',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->empresa))
            ->putJson("/api/empresa/devoluciones/{$devolucion->id}", ['action' => 'aprobar'])
            ->assertForbidden();

        $this->assertSame('solicitada', $devolucion->fresh()->status);
    }

    public function test_empresa_no_puede_resolver_dos_veces_la_misma_devolucion(): void
    {
        $pedido = $this->crearPedidoEntregado();
        $devolucion = ReturnModel::create([
            'order_id'    => $pedido->id,
            'user_id'     => $this->comprador->id,
            'reason'      => 'otro',
            'status'      => 'rechazada',
            'resolved_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->empresa))
            ->putJson("/api/empresa/devoluciones/{$devolucion->id}", ['action' => 'aprobar'])
            ->assertStatus(422);
    }

    public function test_empresa_solo_ve_devoluciones_de_sus_pedidos(): void
    {
        // Devolución de un pedido SUYO.
        $miPedido = $this->crearPedidoEntregado();
        ReturnModel::create([
            'order_id' => $miPedido->id,
            'user_id'  => $this->comprador->id,
            'reason'   => 'otro',
            'status'   => 'solicitada',
        ]);

        // Devolución de un pedido AJENO.
        $otraEmpresa = User::create([
            'name'         => 'Otra',
            'email'        => 'otra2@test.com',
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
            'user_id' => $this->comprador->id, 'status' => 'entregado', 'subtotal' => 1,
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
            'reason' => 'otro', 'status' => 'solicitada',
        ]);

        $r = $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->empresa))
            ->getJson('/api/empresa/devoluciones');

        $r->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_usuario_particular_no_puede_acceder_a_devoluciones_empresa(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->comprador))
            ->getJson('/api/empresa/devoluciones')
            ->assertForbidden();
    }

    // ── Admin: full power ───────────────────────────────────────────────────

    public function test_admin_lista_todas_las_devoluciones_y_filtra_por_estado(): void
    {
        $pedido = $this->crearPedidoEntregado();
        ReturnModel::create([
            'order_id' => $pedido->id, 'user_id' => $this->comprador->id,
            'reason' => 'otro', 'status' => 'solicitada',
        ]);

        // Otra devolución resuelta para verificar el filtro.
        $pedido2 = Order::create([
            'user_id' => $this->comprador->id, 'status' => 'devuelto', 'subtotal' => 10,
            'shipping_cost' => 0, 'total' => 10, 'shipping_address' => 'a', 'shipping_city' => 'm',
            'shipping_postal_code' => '28000', 'shipping_country' => 'España',
            'payment_method' => 'tarjeta', 'delivered_at' => now()->subDay(),
        ]);
        ReturnModel::create([
            'order_id' => $pedido2->id, 'user_id' => $this->comprador->id,
            'reason' => 'otro', 'status' => 'aprobada', 'resolved_at' => now(),
        ]);

        $auth = ['Authorization' => 'Bearer ' . $this->tokenDe($this->admin)];

        $this->withHeaders($auth)->getJson('/api/admin/devoluciones')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->withHeaders($auth)->getJson('/api/admin/devoluciones?status=aprobada')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_aprueba_devolucion_aunque_no_sea_su_producto(): void
    {
        $pedido = $this->crearPedidoEntregado();
        $stockInicial = $this->producto->fresh()->stock;
        $devolucion = ReturnModel::create([
            'order_id' => $pedido->id, 'user_id' => $this->comprador->id,
            'reason' => 'otro', 'status' => 'solicitada',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->admin))
            ->putJson("/api/admin/devoluciones/{$devolucion->id}", ['action' => 'aprobar'])
            ->assertOk()
            ->assertJsonPath('data.return.status', 'aprobada');

        $this->assertSame($stockInicial + 1, $this->producto->fresh()->stock);
        $this->assertSame('devuelto', $pedido->fresh()->status);
    }

    public function test_admin_no_puede_resolver_devolucion_inexistente(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenDe($this->admin))
            ->putJson('/api/admin/devoluciones/99999', ['action' => 'aprobar'])
            ->assertStatus(404);
    }
}
