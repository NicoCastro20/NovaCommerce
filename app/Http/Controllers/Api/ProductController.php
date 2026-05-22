<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Listado público paginado de productos activos con filtros.
     *
     * Query params soportados:
     * - search:     busca en name y description (LIKE)
     * - category:   slug de categoría (incluye productos de subcategorías)
     * - type:       'nuevo' (productos de empresas) o 'segunda_mano' (de particulares)
     * - min_price:  precio mínimo
     * - max_price:  precio máximo
     * - sort:       price_asc | price_desc | newest | rating | discount
     * - offers:     '1' o 'true' para listar solo productos en oferta
     * - per_page:   12 por defecto, máximo 48
     */
    public function index(Request $request): JsonResponse|ProductCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $consulta = Product::query()
            ->activos()
            ->with(['categoria', 'imagenPrincipal', 'vendedor:id,name,role,company_name'])
            ->withCount('resenas as reviews_count')
            ->withAvg('resenas as rating_average', 'rating');

        // Búsqueda por texto
        if ($termino = trim((string) $request->input('search', ''))) {
            $consulta->buscar($termino);
        }

        // Filtro por tipo de producto: empresas (nuevo) o particulares (segunda_mano).
        if ($tipo = $request->input('type')) {
            if (in_array($tipo, ['nuevo', 'segunda_mano'], true)) {
                $consulta->where('type', $tipo);
            }
        }

        // Filtro de "solo ofertas"
        if (filter_var($request->input('offers'), FILTER_VALIDATE_BOOLEAN)) {
            $consulta->onOffer();
        }

        // Filtro por categoría (incluye subcategorías)
        if ($slugCategoria = $request->input('category')) {
            $categoria = Category::where('slug', $slugCategoria)->first();

            if ($categoria === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada.',
                ], 404);
            }

            $idsCategorias = collect([$categoria->id])
                ->merge(Category::where('parent_id', $categoria->id)->pluck('id'))
                ->all();

            $consulta->whereIn('category_id', $idsCategorias);
        }

        // Rango de precios
        if (($min = $request->input('min_price')) !== null && $min !== '') {
            $consulta->where('price', '>=', (float) $min);
        }

        if (($max = $request->input('max_price')) !== null && $max !== '') {
            $consulta->where('price', '<=', (float) $max);
        }

        // Ordenación
        $this->aplicarOrden($consulta, (string) $request->input('sort', 'newest'));

        $productos = $consulta->paginate($perPage)->withQueryString();

        return (new ProductCollection($productos))->additional([
            'success' => true,
            'message' => 'Productos obtenidos correctamente.',
        ]);
    }

    /**
     * Listado público paginado de productos en oferta (solo de empresas).
     *
     * Query params soportados:
     * - per_page: 20 por defecto, máximo 48
     * - sort:     price_asc | price_desc | discount | newest (default discount)
     */
    public function offers(Request $request): ProductCollection
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(48, $perPage));

        $consulta = Product::query()
            ->activos()
            ->onOffer()
            ->where('type', 'nuevo')
            ->with(['categoria', 'imagenPrincipal', 'vendedor:id,name,role,company_name'])
            ->withCount('resenas as reviews_count')
            ->withAvg('resenas as rating_average', 'rating');

        $sort = (string) $request->input('sort', 'discount');
        match ($sort) {
            'price_asc'  => $consulta->orderBy('price'),
            'price_desc' => $consulta->orderByDesc('price'),
            'newest'     => $consulta->orderByDesc('created_at'),
            default      => $consulta->orderByRaw('((original_price - price) / original_price) DESC'),
        };

        $productos = $consulta->paginate($perPage)->withQueryString();

        return (new ProductCollection($productos))->additional([
            'success' => true,
            'message' => 'Ofertas obtenidas correctamente.',
        ]);
    }

    /**
     * Detalle público de un producto por slug, con imágenes, vendedor, reseñas y media de rating.
     */
    public function show(string $slug): JsonResponse
    {
        $producto = Product::query()
            ->where('slug', $slug)
            ->with([
                'categoria',
                'imagenes',
                'vendedor:id,name,role,company_name,avatar',
                'resenas' => fn ($q) => $q->latest()->with('usuario:id,name,avatar'),
            ])
            ->withCount('resenas as reviews_count')
            ->withAvg('resenas as rating_average', 'rating')
            ->first();

        if ($producto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Producto obtenido correctamente.',
            'data'    => [
                'product' => new ProductResource($producto),
            ],
        ]);
    }

    private function aplicarOrden(Builder $consulta, string $sort): void
    {
        match ($sort) {
            'price_asc'  => $consulta->orderBy('price'),
            'price_desc' => $consulta->orderByDesc('price'),
            'rating'     => $consulta->orderByDesc('rating_average')->orderByDesc('reviews_count'),
            'discount'   => $consulta->orderByRaw(
                'CASE WHEN original_price IS NOT NULL AND original_price > price '
                . 'THEN ((original_price - price) / original_price) ELSE 0 END DESC'
            ),
            default      => $consulta->orderByDesc('created_at'),
        };
    }
}
