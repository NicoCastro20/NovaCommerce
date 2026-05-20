<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CategoriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobertura del sistema de suscripción Premium introducido en
 * 2026_05_11_000001_crear_sistema_premium.
 *
 * Endpoints: GET /api/suscripcion, POST /api/suscripcion/activar,
 *            POST /api/suscripcion/cancelar.
 * Además, verifica el efecto colateral de Premium en el checkout (envío gratis).
 */
class PremiumTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategoriesSeeder::class);

        $this->usuario = User::create([
            'name'     => 'Usuario Premium',
            'email'    => 'premium@test.com',
            'password' => bcrypt('Password1'),
            'role'     => 'usuario',
        ]);
    }

    private function token(User $u): string
    {
        return $u->createToken('test')->plainTextToken;
    }

    // ── Estado ──────────────────────────────────────────────────────────────

    public function test_usuario_no_premium_obtiene_estado_inactivo(): void
    {
        $r = $this->withHeader('Authorization', 'Bearer ' . $this->token($this->usuario))
            ->getJson('/api/suscripcion');

        $r->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.suscripcion.is_premium', false)
            ->assertJsonPath('data.suscripcion.activa', false)
            ->assertJsonPath('data.suscripcion.premium_since', null)
            ->assertJsonPath('data.suscripcion.premium_until', null)
            ->assertJsonPath('data.suscripcion.dias_restantes', 0)
            ->assertJsonPath('data.suscripcion.precio', 50);
    }

    public function test_endpoint_estado_requiere_autenticacion(): void
    {
        $this->getJson('/api/suscripcion')->assertStatus(401);
    }

    // ── Activar ─────────────────────────────────────────────────────────────

    public function test_usuario_puede_activar_premium(): void
    {
        $r = $this->withHeader('Authorization', 'Bearer ' . $this->token($this->usuario))
            ->postJson('/api/suscripcion/activar', []);

        $r->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.suscripcion.is_premium', true)
            ->assertJsonPath('data.suscripcion.activa', true);

        $this->usuario->refresh();
        $this->assertTrue((bool) $this->usuario->is_premium);
        $this->assertNotNull($this->usuario->premium_since);
        $this->assertNotNull($this->usuario->premium_until);
        // premium_until debe estar aprox 1 año en el futuro.
        $this->assertTrue($this->usuario->premium_until->greaterThan(now()->addDays(360)));
        $this->assertTrue($this->usuario->premium_until->lessThan(now()->addDays(370)));
    }

    public function test_no_se_puede_activar_premium_dos_veces(): void
    {
        $this->usuario->activarPremium();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->usuario))
            ->postJson('/api/suscripcion/activar', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_activar_premium_requiere_autenticacion(): void
    {
        $this->postJson('/api/suscripcion/activar', [])->assertStatus(401);
    }

    // ── Cancelar ────────────────────────────────────────────────────────────

    public function test_cancelar_premium_mantiene_beneficios_hasta_fecha_de_expiracion(): void
    {
        $this->usuario->activarPremium();
        $premiumUntil = $this->usuario->fresh()->premium_until;

        $r = $this->withHeader('Authorization', 'Bearer ' . $this->token($this->usuario))
            ->postJson('/api/suscripcion/cancelar', []);

        $r->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.suscripcion.is_premium', false)
            // sigue activa porque premium_until es futuro
            ->assertJsonPath('data.suscripcion.activa', true);

        $this->usuario->refresh();
        $this->assertFalse((bool) $this->usuario->is_premium);
        $this->assertNotNull($this->usuario->premium_until);
        // La fecha de expiración NO se mueve al cancelar.
        $this->assertSame(
            $premiumUntil->toDateTimeString(),
            $this->usuario->premium_until->toDateTimeString()
        );
    }

    public function test_cancelar_sin_suscripcion_devuelve_422(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->usuario))
            ->postJson('/api/suscripcion/cancelar', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cancelar_requiere_autenticacion(): void
    {
        $this->postJson('/api/suscripcion/cancelar', [])->assertStatus(401);
    }

    // ── Efecto en checkout: envío gratis para Premium ───────────────────────

    public function test_checkout_de_usuario_premium_aplica_envio_gratuito_aunque_no_alcance_el_umbral(): void
    {
        $this->usuario->activarPremium();

        // Producto de bajo importe (subtotal < 50 €, no debería entrar la
        // condición ENVIO_GRATIS_DESDE).
        $empresa = User::create([
            'name'         => 'Empresa',
            'email'        => 'empresa.checkout@test.com',
            'password'     => bcrypt('Password1'),
            'role'         => 'empresa',
            'company_name' => 'E S.L.',
            'nif_cif'      => 'B00000001',
        ]);
        $producto = Product::create([
            'user_id'     => $empresa->id,
            'category_id' => \App\Models\Category::first()->id,
            'name'        => 'Barato',
            'slug'        => 'barato',
            'description' => 'd',
            'price'       => 9.99,
            'stock'       => 10,
            'is_active'   => true,
            'type'        => 'nuevo',
        ]);

        $carrito = Cart::create(['user_id' => $this->usuario->id]);
        CartItem::create([
            'cart_id'    => $carrito->id,
            'product_id' => $producto->id,
            'quantity'   => 1,
        ]);

        $r = $this->withHeader('Authorization', 'Bearer ' . $this->token($this->usuario))
            ->postJson('/api/checkout', [
                'shipping_address'     => 'Calle 1',
                'shipping_city'        => 'Madrid',
                'shipping_postal_code' => '28013',
                'payment_method'       => 'tarjeta',
            ]);

        $r->assertCreated()
            ->assertJsonPath('data.order.shipping_cost', 0)
            ->assertJsonPath('data.order.envio_premium', true)
            ->assertJsonPath('data.order.total', 9.99);
    }

    public function test_checkout_de_usuario_no_premium_cobra_envio_si_no_alcanza_el_umbral(): void
    {
        $empresa = User::create([
            'name'         => 'Empresa',
            'email'        => 'empresa.checkout2@test.com',
            'password'     => bcrypt('Password1'),
            'role'         => 'empresa',
            'company_name' => 'E S.L.',
            'nif_cif'      => 'B00000002',
        ]);
        $producto = Product::create([
            'user_id'     => $empresa->id,
            'category_id' => \App\Models\Category::first()->id,
            'name'        => 'Barato',
            'slug'        => 'barato2',
            'description' => 'd',
            'price'       => 9.99,
            'stock'       => 10,
            'is_active'   => true,
            'type'        => 'nuevo',
        ]);

        $carrito = Cart::create(['user_id' => $this->usuario->id]);
        CartItem::create([
            'cart_id'    => $carrito->id,
            'product_id' => $producto->id,
            'quantity'   => 1,
        ]);

        $r = $this->withHeader('Authorization', 'Bearer ' . $this->token($this->usuario))
            ->postJson('/api/checkout', [
                'shipping_address'     => 'Calle 1',
                'shipping_city'        => 'Madrid',
                'shipping_postal_code' => '28013',
                'payment_method'       => 'tarjeta',
            ]);

        $r->assertCreated()
            ->assertJsonPath('data.order.shipping_cost', 5)
            ->assertJsonPath('data.order.envio_premium', false)
            ->assertJsonPath('data.order.total', 14.99);
    }

    // ── Admin: vista de suscripciones ───────────────────────────────────────

    public function test_admin_lista_suscripciones(): void
    {
        $this->usuario->activarPremium();

        $admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin.premium@test.com',
            'password' => bcrypt('Password1'),
            'role'     => 'admin',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->getJson('/api/admin/suscripciones')
            ->assertOk();
    }

    public function test_usuario_no_admin_no_puede_listar_suscripciones(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->usuario))
            ->getJson('/api/admin/suscripciones')
            ->assertForbidden();
    }
}
