<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OrdersSeeder extends Seeder
{
    /**
     * Genera ~12 pedidos de prueba repartidos entre los clientes y los estados
     * disponibles para que el dashboard tenga datos realistas.
     */
    public function run(): void
    {
        $this->command->info('Creando pedidos de ejemplo...');

        $clientes = User::where('role', 'usuario')->get();
        // Pedidos de prueba sólo con productos de empresa: los de segunda mano
        // tienen stock=1 y mezclar cantidades aleatorias rompería su invariante.
        $productos = Product::where('is_active', true)->where('type', 'nuevo')->get();

        if ($clientes->isEmpty() || $productos->isEmpty()) {
            $this->command->warn('No hay clientes o productos. Saltando OrdersSeeder.');
            return;
        }

        $direcciones = [
            ['address' => 'Calle Mayor 12, 3ºB',  'city' => 'Madrid',    'postal_code' => '28013'],
            ['address' => 'Av. Diagonal 421',     'city' => 'Barcelona', 'postal_code' => '08008'],
            ['address' => 'Calle Sierpes 45',     'city' => 'Sevilla',   'postal_code' => '41004'],
            ['address' => 'Calle Colón 18',       'city' => 'Valencia',  'postal_code' => '46004'],
            ['address' => 'Gran Vía 27',          'city' => 'Bilbao',    'postal_code' => '48001'],
        ];

        // Repartimos 12 pedidos: 2 pendientes, 2 pagados, 3 enviados, 4 entregados, 1 cancelado.
        $plan = [
            'pendiente' => 2,
            'pagado'    => 2,
            'enviado'   => 3,
            'entregado' => 4,
            'cancelado' => 1,
        ];

        $contador = 0;

        foreach ($plan as $estado => $cantidad) {
            for ($i = 0; $i < $cantidad; $i++) {
                $contador++;

                $cliente   = $clientes->random();
                $direccion = $direcciones[array_rand($direcciones)];

                // Cada pedido lleva entre 1 y 3 items distintos.
                $itemsProductos = $productos->random(min(3, max(1, rand(1, 3))));
                if (! is_iterable($itemsProductos)) {
                    $itemsProductos = collect([$itemsProductos]);
                }

                $subtotal = 0.0;
                $itemsADatos = [];

                foreach ($itemsProductos as $producto) {
                    $cantidadItem = rand(1, 3);
                    $precioItem   = (float) $producto->price;
                    $subtotalItem = round($precioItem * $cantidadItem, 2);
                    $subtotal    += $subtotalItem;

                    $itemsADatos[] = [
                        'product_id'    => $producto->id,
                        'seller_id'     => $producto->user_id,
                        'product_name'  => $producto->name,
                        'product_price' => $precioItem,
                        'quantity'      => $cantidadItem,
                        'subtotal'      => $subtotalItem,
                    ];
                }

                $envio = $subtotal >= 50 ? 0.0 : 5.0;
                $total = round($subtotal + $envio, 2);

                // Distribuir los pedidos a lo largo de los últimos 25 días para que
                // la gráfica del dashboard tenga puntos en varios días.
                $diasAtras = rand(0, 25);
                $fecha     = Carbon::now()->subDays($diasAtras)->subHours(rand(0, 23));

                $pedido = Order::create([
                    'user_id'              => $cliente->id,
                    'order_number'         => 'NC-' . strtoupper(Str::random(8)),
                    'status'               => $estado,
                    'subtotal'             => round($subtotal, 2),
                    'shipping_cost'        => $envio,
                    'total'                => $total,
                    'shipping_address'     => $direccion['address'],
                    'shipping_city'        => $direccion['city'],
                    'shipping_postal_code' => $direccion['postal_code'],
                    'shipping_country'     => 'España',
                    'payment_method'       => 'tarjeta',
                    'notes'                => null,
                    'created_at'           => $fecha,
                    'updated_at'           => $fecha,
                ]);

                foreach ($itemsADatos as $datos) {
                    OrderItem::create(array_merge($datos, [
                        'order_id'   => $pedido->id,
                        'created_at' => $fecha,
                        'updated_at' => $fecha,
                    ]));
                }
            }
        }

        $this->command->info("✓ {$contador} pedidos creados con sus items.");
    }
}
