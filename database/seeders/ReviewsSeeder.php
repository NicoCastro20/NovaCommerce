<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando reseñas de ejemplo...');

        $clientes  = User::where('role', 'usuario')->get();
        $productos = Product::take(10)->get();

        $comentarios = [
            5 => [
                'Producto excelente, superó mis expectativas. Lo recomiendo sin duda.',
                'Calidad increíble para el precio. Llegó antes de lo previsto.',
                'Muy satisfecho con la compra. El producto es tal y como se describe.',
                'Perfecto, funciona genial. Muy contento con la compra.',
            ],
            4 => [
                'Buen producto en general, aunque el embalaje podría mejorar.',
                'Cumple con lo prometido. La relación calidad-precio es buena.',
                'Bien, aunque tardó un poco más de lo esperado en llegar.',
                'Producto de buena calidad, aunque esperaba un poco más.',
            ],
            3 => [
                'Regular. No es lo que esperaba pero cumple su función.',
                'Aceptable para el precio, pero hay opciones mejores en el mercado.',
            ],
            2 => [
                'No me convenció del todo. La calidad es mejorable.',
            ],
        ];

        $resenas = [];

        foreach ($productos as $producto) {
            // Cada cliente deja como máximo una reseña por producto
            $clientesBarajados = $clientes->shuffle()->take(rand(1, $clientes->count()));

            foreach ($clientesBarajados as $cliente) {
                // Evitar duplicados (constraint unique user_id + product_id)
                $yaExiste = Review::where('user_id', $cliente->id)
                    ->where('product_id', $producto->id)
                    ->exists();

                if ($yaExiste) {
                    continue;
                }

                $puntuacion = collect([5, 5, 4, 4, 3, 2])->random();
                $comentario = collect($comentarios[$puntuacion] ?? $comentarios[3])->random();

                Review::create([
                    'user_id'    => $cliente->id,
                    'product_id' => $producto->id,
                    'rating'     => $puntuacion,
                    'comment'    => $comentario,
                ]);
            }
        }

        $this->command->info('✓ ' . Review::count() . ' reseñas creadas.');
    }
}
