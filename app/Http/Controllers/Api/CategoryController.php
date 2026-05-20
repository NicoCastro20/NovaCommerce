<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductCollection;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Devuelve el árbol de categorías (padres con sus hijas anidadas).
     */
    public function index(): JsonResponse
    {
        $arbol = Category::query()
            ->whereNull('parent_id')
            ->with(['subcategorias' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Categorías obtenidas correctamente.',
            'data'    => [
                'categories' => CategoryResource::collection($arbol),
            ],
        ]);
    }

    /**
     * Devuelve los productos activos de una categoría (incluye los de sus subcategorías).
     */
    public function show(string $slug, Request $request): JsonResponse|ProductCollection
    {
        $categoria = Category::query()
            ->with('subcategorias:id,parent_id')
            ->where('slug', $slug)
            ->first();

        if ($categoria === null) {
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada.',
            ], 404);
        }

        $idsCategorias = collect([$categoria->id])
            ->merge($categoria->subcategorias->pluck('id'))
            ->all();

        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $consulta = Product::query()
            ->activos()
            ->whereIn('category_id', $idsCategorias)
            ->with(['categoria', 'imagenPrincipal', 'vendedor:id,name,role,company_name'])
            ->withCount('resenas as reviews_count')
            ->withAvg('resenas as rating_average', 'rating');

        $this->aplicarOrden($consulta, (string) $request->input('sort', 'newest'));

        $productos = $consulta->paginate($perPage)->withQueryString();

        return (new ProductCollection($productos))->additional([
            'success'  => true,
            'message'  => 'Productos de la categoría obtenidos correctamente.',
            'category' => [
                'id'   => $categoria->id,
                'name' => $categoria->name,
                'slug' => $categoria->slug,
                'icon' => $categoria->icon,
            ],
        ]);
    }

    private function aplicarOrden(Builder $consulta, string $sort): void
    {
        match ($sort) {
            'price_asc'  => $consulta->orderBy('price'),
            'price_desc' => $consulta->orderByDesc('price'),
            'rating'     => $consulta->orderByDesc('rating_average')->orderByDesc('reviews_count'),
            default      => $consulta->orderByDesc('created_at'),
        };
    }
}
