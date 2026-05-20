<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando categorías principales y subcategorías...');

        $arbol = [
            [
                'name' => 'Tecnología',
                'icon' => 'laptop',
                'subcategorias' => [
                    'Electrónica',
                    'Informática',
                    'Telefonía',
                    'Televisores y Audio',
                    'Fotografía',
                ],
            ],
            [
                'name' => 'Ropa y Moda',
                'icon' => 'shirt',
                'subcategorias' => [
                    'Hombre',
                    'Mujer',
                    'Niños',
                    'Calzado',
                    'Accesorios',
                ],
            ],
            [
                'name' => 'Hogar y Jardín',
                'icon' => 'home',
                'subcategorias' => [
                    'Muebles',
                    'Decoración',
                    'Electrodomésticos',
                    'Jardín',
                    'Bricolaje',
                ],
            ],
            [
                'name' => 'Deportes',
                'icon' => 'dumbbell',
                'subcategorias' => [
                    'Fitness',
                    'Ciclismo',
                    'Fútbol',
                    'Running',
                    'Natación',
                ],
            ],
            [
                'name' => 'Libros y Música',
                'icon' => 'book',
                'subcategorias' => [
                    'Libros',
                    'Música',
                    'Cine',
                    'Videojuegos',
                ],
            ],
            [
                'name' => 'Alimentación',
                'icon' => 'shopping-basket',
                'subcategorias' => [
                    'Frescos',
                    'Bebidas',
                    'Ecológico',
                    'Gourmet',
                ],
            ],
        ];

        foreach ($arbol as $datos) {
            $padre = Category::create([
                'name'      => $datos['name'],
                'slug'      => Str::slug($datos['name']),
                'icon'      => $datos['icon'],
                'parent_id' => null,
            ]);

            foreach ($datos['subcategorias'] as $nombre) {
                Category::create([
                    'name'      => $nombre,
                    'slug'      => Str::slug($padre->slug . '-' . $nombre),
                    'parent_id' => $padre->id,
                ]);
            }
        }

        $this->command->info('✓ ' . Category::count() . ' categorías creadas.');
    }
}
