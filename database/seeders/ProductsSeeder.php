<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductsSeeder extends Seeder
{
    /**
     * Formato base de las imágenes de Unsplash. El placeholder {ID} se sustituye
     * por el identificador del foto (la parte después de "photo-" en la URL
     * canónica de Unsplash). Mantenemos los parámetros de tamaño y recorte
     * en un solo sitio para poder ajustarlos globalmente.
     */
    private const UNSPLASH = 'https://images.unsplash.com/photo-{ID}?w=600&h=400&fit=crop';

    public function run(): void
    {
        $this->command->info('Creando productos de ejemplo...');

        $techstore    = User::where('email', 'techstore@novacommerce.com')->first();
        $mueblesdeco  = User::where('email', 'mueblesdeco@novacommerce.com')->first();
        $carlos       = User::where('email', 'carlos@ejemplo.com')->first();
        $maria        = User::where('email', 'maria@ejemplo.com')->first();
        $javier       = User::where('email', 'javier@ejemplo.com')->first();

        $electronica  = Category::where('slug', 'tecnologia-electronica')->first();
        $informatica  = Category::where('slug', 'tecnologia-informatica')->first();
        $telefonia    = Category::where('slug', 'tecnologia-telefonia')->first();
        $hombre       = Category::where('slug', 'ropa-y-moda-hombre')->first();
        $mujer        = Category::where('slug', 'ropa-y-moda-mujer')->first();
        $calzado      = Category::where('slug', 'ropa-y-moda-calzado')->first();
        $fitness      = Category::where('slug', 'deportes-fitness')->first();
        $hogar        = Category::where('slug', 'hogar-y-jardin-decoracion')->first();
        $muebles      = Category::where('slug', 'hogar-y-jardin-muebles')->first()
            ?? Category::where('slug', 'hogar-y-jardin-decoracion')->first();

        // ── Productos de empresa (type='nuevo') ───────────────────────────────
        // Cada entrada lleva una imagen principal y, opcionalmente, una segunda
        // imagen de detalle, todas tomadas de Unsplash.
        $catalogoEmpresa = [
            // TechStore — electrónica e informática
            [
                'user'        => $techstore,
                'category'    => $electronica,
                'name'        => 'Auriculares Inalámbricos Pro X',
                'description' => 'Auriculares Bluetooth 5.3 con cancelación activa de ruido, 30 horas de batería y sonido Hi-Fi.',
                'price'       => 89.99,
                'stock'       => 45,
                'images'      => ['1505740420928-5e560c06d30e', '1583394838336-acd977736f90'],
            ],
            [
                'user'        => $techstore,
                'category'    => $electronica,
                'name'        => 'Altavoz Portátil SoundBoost 360',
                'description' => 'Altavoz bluetooth resistente al agua (IPX7), 20W de potencia, batería de 12 horas.',
                'price'       => 59.95,
                'stock'       => 30,
                'images'      => ['1608043152269-423dbba4e7e1', '1589003077984-894e133dabab'],
            ],
            [
                'user'        => $techstore,
                'category'    => $informatica,
                'name'        => 'Teclado Mecánico RGB TechType',
                'description' => 'Teclado mecánico TKL con switches Cherry MX Red, retroiluminación RGB y construcción en aluminio.',
                'price'       => 129.00,
                'stock'       => 20,
                'images'      => ['1587829741301-dc798b83add3', '1595225476474-87563907a212'],
            ],
            [
                'user'        => $techstore,
                'category'    => $informatica,
                'name'        => 'Ratón Gaming UltraSpeed 16000 DPI',
                'description' => 'Ratón gaming con sensor óptico de 16.000 DPI, 7 botones programables y peso ajustable.',
                'price'       => 49.99,
                'stock'       => 55,
                'images'      => ['1527864550417-7fd91fc51a46', '1615663245857-ac93bb7c39e7'],
            ],
            [
                'user'        => $techstore,
                'category'    => $telefonia,
                'name'        => 'Cargador Rápido 65W GaN',
                'description' => 'Cargador USB-C GaN de 65W compatible con USB Power Delivery 3.0. Carga 3 dispositivos a la vez.',
                'price'       => 39.99,
                'stock'       => 75,
                'images'      => ['1583863788434-e58a36330cf0', '1609692814858-f7cd2f0afa4f'],
            ],
            [
                'user'        => $techstore,
                'category'    => $electronica,
                'name'        => 'Smart TV 55" 4K OLED',
                'description' => 'Televisor OLED 55" con resolución 4K, HDR Dolby Vision y sistema Android TV 12.',
                'price'       => 899.00,
                'stock'       => 8,
                'images'      => ['1593359677879-a4bb92f829d1', '1577979749830-f1d742b96791'],
            ],
            [
                'user'        => $techstore,
                'category'    => $electronica,
                'name'        => 'Reloj Inteligente FitWatch 5',
                'description' => 'Smartwatch con pantalla AMOLED 1.43", GPS, monitorización del sueño y resistencia 5ATM.',
                'price'       => 119.90,
                'stock'       => 38,
                'images'      => ['1523275335684-37898b6baf30', '1579586337278-3befd40fd17a'],
            ],
            [
                'user'        => $techstore,
                'category'    => $telefonia,
                'name'        => 'Power Bank 20.000mAh USB-C PD',
                'description' => 'Batería externa de 20.000mAh con USB-C Power Delivery 22.5W y pantalla LED.',
                'price'       => 34.95,
                'stock'       => 65,
                'images'      => ['1610792516775-01de03eae630', '1606166325683-e6deb697d301'],
            ],

            // MueblesDeco — hogar y muebles
            [
                'user'        => $mueblesdeco,
                'category'    => $muebles,
                'name'        => 'Mesa de Comedor Roble Macizo',
                'description' => 'Mesa rectangular de roble macizo para 6 comensales (180×90 cm). Acabado natural barnizado.',
                'price'       => 459.00,
                'stock'       => 6,
                'images'      => ['1604578762246-41134e37f9cc', '1617806118233-18e1de247200'],
            ],
            [
                'user'        => $mueblesdeco,
                'category'    => $hogar,
                'name'        => 'Lámpara de Pie Industrial',
                'description' => 'Lámpara de pie en metal negro con pantalla orientable. Bombilla E27 incluida (no LED).',
                'price'       => 89.00,
                'stock'       => 18,
                'images'      => ['1507473885765-e6ed057f782c', '1513506003901-1e6a229e2d15'],
            ],
            [
                'user'        => $mueblesdeco,
                'category'    => $hogar,
                'name'        => 'Manta de Algodón Orgánico',
                'description' => 'Manta tejida en algodón 100% orgánico GOTS. Medidas 130×170 cm.',
                'price'       => 49.00,
                'stock'       => 45,
                'images'      => ['1600369671236-e74521d4b6ad', '1584100936595-c0654b55a2e2'],
            ],
            [
                'user'        => $mueblesdeco,
                'category'    => $hogar,
                'name'        => 'Set de Velas Aromáticas Ibéricas',
                'description' => 'Colección de 4 velas de cera de soja con aromas mediterráneos: azahar, lavanda, romero y tomillo.',
                'price'       => 28.50,
                'stock'       => 90,
                'images'      => ['1606760227091-3dd870d97f1d', '1603006905003-be475563bc59'],
            ],
            [
                'user'        => $mueblesdeco,
                'category'    => $muebles,
                'name'        => 'Estantería Modular de Pino',
                'description' => 'Estantería modular en madera de pino con 5 baldas. 180×80×30 cm. Fácil montaje.',
                'price'       => 139.00,
                'stock'       => 14,
                'images'      => ['1594620302200-9a762244a156', '1598300042247-d088f8ab3a91'],
            ],
        ];

        $contadorEmpresa = 0;
        foreach ($catalogoEmpresa as $datos) {
            if ($datos['user'] === null || $datos['category'] === null) continue;
            $contadorEmpresa++;

            $producto = Product::create([
                'user_id'     => $datos['user']->id,
                'category_id' => $datos['category']->id,
                'name'        => $datos['name'],
                'slug'        => Str::slug($datos['name']),
                'description' => $datos['description'],
                'price'       => $datos['price'],
                'stock'       => $datos['stock'],
                'is_active'   => true,
                'type'        => 'nuevo',
                'condition'   => null,
            ]);

            foreach ($datos['images'] as $idx => $unsplashId) {
                ProductImage::create([
                    'product_id' => $producto->id,
                    'image_path' => str_replace('{ID}', $unsplashId, self::UNSPLASH),
                    'is_primary' => $idx === 0,
                    'sort_order' => $idx,
                ]);
            }
        }

        // ── Productos de segunda mano (particulares) ──────────────────────────
        // stock siempre = 1, condition obligatorio. Una imagen por producto,
        // que evoca una foto más casual que la de catálogo profesional.
        $catalogoSegundaMano = [
            // Carlos
            [
                'user'        => $carlos,
                'category'    => $telefonia,
                'name'        => 'iPhone 12 - Buen estado',
                'description' => 'iPhone 12 de 128 GB color azul. Batería al 87 %. Pequeñas marcas de uso en los bordes. Incluye caja original y cable.',
                'price'       => 320.00,
                'condition'   => 'buen_estado',
                'image'       => '1601784551446-20c9e07cdbdb',
            ],
            [
                'user'        => $carlos,
                'category'    => $electronica,
                'name'        => 'PS5 con dos mandos',
                'description' => 'PlayStation 5 edición digital con dos mandos DualSense (uno blanco, otro negro). Apenas usada, todo funcionando perfectamente.',
                'price'       => 380.00,
                'condition'   => 'como_nuevo',
                'image'       => '1606813907291-d86efa9b94db',
            ],

            // María
            [
                'user'        => $maria,
                'category'    => $fitness,
                'name'        => 'Bicicleta de montaña',
                'description' => 'Bici de montaña talla M, cuadro de aluminio, suspensión delantera. Le hace falta un cambio de cubiertas.',
                'price'       => 220.00,
                'condition'   => 'usado',
                'image'       => '1532298229144-0ec0c57515c7',
            ],
            [
                'user'        => $maria,
                'category'    => $mujer,
                'name'        => 'Vestido de fiesta talla S',
                'description' => 'Vestido midi negro, talla S, usado solo una vez en una boda. Sin tara alguna.',
                'price'       => 35.00,
                'condition'   => 'como_nuevo',
                'image'       => '1539008835657-9e8e9680c956',
            ],

            // Javier
            [
                'user'        => $javier,
                'category'    => $informatica,
                'name'        => 'Portátil Lenovo ThinkPad T480',
                'description' => 'ThinkPad T480 con 16 GB de RAM, SSD 512 GB y batería extendida. Ideal para programar o estudiar.',
                'price'       => 410.00,
                'condition'   => 'buen_estado',
                'image'       => '1496181133206-80ce9b88a853',
            ],
            [
                'user'        => $javier,
                'category'    => $hombre,
                'name'        => 'Chaqueta vaquera talla L',
                'description' => 'Chaqueta vaquera azul oscuro, talla L. Apenas la he usado, está prácticamente nueva.',
                'price'       => 25.00,
                'condition'   => 'como_nuevo',
                'image'       => '1543076447-215ad9ba6923',
            ],
            [
                'user'        => $javier,
                'category'    => $calzado,
                'name'        => 'Botas de senderismo talla 43',
                'description' => 'Botas de senderismo impermeables, talla 43. Las he usado para varias rutas, suela en perfecto estado.',
                'price'       => 45.00,
                'condition'   => 'usado',
                'image'       => '1551107696-a4b0c5a0d9a2',
            ],
        ];

        $contadorSegundaMano = 0;
        foreach ($catalogoSegundaMano as $datos) {
            if ($datos['user'] === null || $datos['category'] === null) continue;
            $contadorSegundaMano++;

            $producto = Product::create([
                'user_id'     => $datos['user']->id,
                'category_id' => $datos['category']->id,
                'name'        => $datos['name'],
                'slug'        => Str::slug($datos['name']),
                'description' => $datos['description'],
                'price'       => $datos['price'],
                'stock'       => 1,
                'is_active'   => true,
                'type'        => 'segunda_mano',
                'condition'   => $datos['condition'],
            ]);

            ProductImage::create([
                'product_id' => $producto->id,
                'image_path' => str_replace('{ID}', $datos['image'], self::UNSPLASH),
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        }

        $this->command->info("✓ {$contadorEmpresa} productos de empresa + {$contadorSegundaMano} de segunda mano creados.");
    }
}
