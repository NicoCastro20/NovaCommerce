<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Seeder idempotente que añade ofertas de muestra a productos de empresa ya
 * existentes, sin tocar otros datos (pedidos, reseñas, etc.). Pensado para
 * lanzarlo una vez en producción tras desplegar la feature de ofertas, cuando
 * los productos ya están sembrados pero ninguno tiene `original_price`.
 *
 * Selecciona 1 de cada 3 productos de empresa (orden por id) y les asigna
 * descuento + etiqueta + caducidad de forma determinista. Si un producto ya
 * tiene `original_price`, se respeta su valor actual.
 */
class OfertasMuestraSeeder extends Seeder
{
    public function run(): void
    {
        $etiquetas    = ['OFERTA', 'REBAJAS', 'BLACK FRIDAY', 'LIQUIDACIÓN', 'PROMOCIÓN'];
        $porcentajes  = [10, 15, 20, 25, 30, 35, 40, 45, 50];

        $productos = Product::query()
            ->where('type', 'nuevo')
            ->orderBy('id')
            ->get();

        $contador = 0;
        $puestas  = 0;

        foreach ($productos as $i => $producto) {
            // 1 de cada 3 productos pasa a oferta.
            if ($i % 3 !== 0) continue;
            $contador++;

            // Idempotencia: si ya está marcado como oferta, no lo tocamos.
            if ($producto->original_price !== null) continue;

            $porcentaje    = $porcentajes[$contador % count($porcentajes)];
            $precioActual  = (float) $producto->price;
            $precioOriginal = round($precioActual / (1 - $porcentaje / 100), 2);
            if ($precioOriginal <= $precioActual) {
                $precioOriginal = round($precioActual + 0.01, 2);
            }

            $datos = ['original_price' => $precioOriginal];

            // Una etiqueta cada dos ofertas, para variar.
            if ($contador % 2 === 1) {
                $datos['offer_label'] = $etiquetas[$contador % count($etiquetas)];
            }
            // Caducidad próxima (3-21 días) cada tres ofertas.
            if ($contador % 3 === 0) {
                $datos['offer_ends_at'] = now()->addDays(3 + ($contador % 19));
            }

            $producto->update($datos);
            $puestas++;
        }

        $this->command->info("✓ Ofertas de muestra: {$puestas} producto(s) marcado(s) como oferta.");
    }
}
