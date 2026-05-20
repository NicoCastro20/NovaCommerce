<?php

namespace App\Http\Controllers\Api\Empresa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEmpresaProductoRequest;
use App\Http\Requests\Api\UpdateEmpresaProductoRequest;
use App\Http\Requests\Api\UploadProductImagesRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmpresaProductoController extends Controller
{
    /**
     * Listado paginado de los productos de la empresa autenticada (incluye inactivos).
     */
    public function index(Request $request): ProductCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(1, min(48, $perPage));

        $productos = Product::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'nuevo')
            ->with(['categoria', 'imagenPrincipal'])
            ->withCount('resenas as reviews_count')
            ->withAvg('resenas as rating_average', 'rating')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return (new ProductCollection($productos))->additional([
            'success' => true,
            'message' => 'Productos obtenidos correctamente.',
        ]);
    }

    /**
     * Crea un nuevo producto de empresa (type='nuevo') asociado al usuario autenticado.
     */
    public function store(StoreEmpresaProductoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $producto = Product::create([
            'user_id'     => $request->user()->id,
            'category_id' => $datos['category_id'],
            'name'        => $datos['name'],
            'slug'        => $this->slugUnico($datos['name']),
            'description' => $datos['description'],
            'price'       => $datos['price'],
            'stock'       => $datos['stock'],
            'is_active'   => $datos['is_active'] ?? true,
            'type'        => 'nuevo',
            'condition'   => null,
        ]);

        $producto->load(['categoria', 'imagenes']);

        return response()->json([
            'success' => true,
            'message' => 'Producto creado correctamente.',
            'data'    => [
                'product' => new ProductResource($producto),
            ],
        ], 201);
    }

    /**
     * Actualiza un producto de la empresa autenticada.
     */
    public function update(UpdateEmpresaProductoRequest $request, int $id): JsonResponse
    {
        $producto = Product::find($id);

        if ($producto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        if (! $this->puedeGestionar($producto, $request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para editar este producto.',
            ], 403);
        }

        $datos = $request->validated();

        if (isset($datos['name']) && $datos['name'] !== $producto->name) {
            $datos['slug'] = $this->slugUnico($datos['name'], $producto->id);
        }

        $producto->fill($datos)->save();
        $producto->load(['categoria', 'imagenes']);

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado correctamente.',
            'data'    => [
                'product' => new ProductResource($producto),
            ],
        ]);
    }

    /**
     * Elimina (soft delete) un producto de la empresa autenticada.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $producto = Product::find($id);

        if ($producto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        if (! $this->puedeGestionar($producto, $request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar este producto.',
            ], 403);
        }

        $producto->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado correctamente.',
        ]);
    }

    /**
     * Sube hasta 5 imágenes a `storage/app/public/products` y las asocia al producto.
     */
    public function uploadImages(UploadProductImagesRequest $request, int $id): JsonResponse
    {
        $producto = Product::find($id);

        if ($producto === null) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        if (! $this->puedeGestionar($producto, $request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para subir imágenes a este producto.',
            ], 403);
        }

        $tienePrincipal  = $producto->imagenes()->where('is_primary', true)->exists();
        $maxOrdenActual  = (int) $producto->imagenes()->max('sort_order');
        $imagenesCreadas = [];

        foreach ($request->file('images', []) as $i => $archivo) {
            $ruta = $archivo->store('products', 'public');

            $esPrincipal = ! $tienePrincipal && $i === 0;
            $tienePrincipal = $tienePrincipal || $esPrincipal;

            $imagenesCreadas[] = ProductImage::create([
                'product_id' => $producto->id,
                'image_path' => $ruta,
                'is_primary' => $esPrincipal,
                'sort_order' => ++$maxOrdenActual,
            ]);
        }

        $producto->load('imagenes');

        return response()->json([
            'success' => true,
            'message' => 'Imágenes subidas correctamente.',
            'data'    => [
                'product'  => new ProductResource($producto),
                'uploaded' => collect($imagenesCreadas)->map(fn (ProductImage $img) => [
                    'id'         => $img->id,
                    'url'        => Storage::disk('public')->url($img->image_path),
                    'is_primary' => (bool) $img->is_primary,
                    'sort_order' => $img->sort_order,
                ]),
            ],
        ], 201);
    }

    private function puedeGestionar(Product $producto, Request $request): bool
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return false;
        }

        if ($usuario->role === 'admin') {
            return true;
        }

        return (int) $producto->user_id === (int) $usuario->id;
    }

    private function slugUnico(string $nombre, ?int $ignorarId = null): string
    {
        $base = Str::slug($nombre);
        $slug = $base;
        $i    = 2;

        while (Product::query()
            ->where('slug', $slug)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
