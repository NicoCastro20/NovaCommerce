<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * Listado paginado de usuarios con filtros opcionales.
     *
     * Filtros (query string):
     * - search:        coincide en name, email o nif_cif (LIKE)
     * - role:          usuario | empresa | admin
     * - with_trashed:  1 para incluir usuarios desactivados
     * - only_trashed:  1 para listar solo desactivados
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $consulta = User::query();

        if ($request->boolean('only_trashed')) {
            $consulta->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $consulta->withTrashed();
        }

        if ($termino = $request->input('search')) {
            $consulta->where(function ($q) use ($termino) {
                $q->where('name', 'like', "%{$termino}%")
                  ->orWhere('email', 'like', "%{$termino}%")
                  ->orWhere('nif_cif', 'like', "%{$termino}%");
            });
        }

        if ($rol = $request->input('role')) {
            $consulta->where('role', $rol);
        }

        $usuarios = $consulta
            ->withCount(['productos', 'pedidos'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        // Aplanamos manualmente para incluir el estado Premium "vivo".
        $items = collect($usuarios->items())->map(function (User $u): array {
            $datos = $u->toArray();
            $datos['premium_active'] = $u->esPremium();
            return $datos;
        });

        return response()->json([
            'success' => true,
            'message' => 'Usuarios obtenidos correctamente.',
            'data'    => $items,
            'meta'    => [
                'current_page' => $usuarios->currentPage(),
                'last_page'    => $usuarios->lastPage(),
                'per_page'     => $usuarios->perPage(),
                'total'        => $usuarios->total(),
            ],
        ]);
    }

    /**
     * Cambia el rol de un usuario. Un admin no puede cambiar su propio rol
     * para evitar quedarse sin permisos por error.
     */
    public function updateRole(UpdateUserRoleRequest $request, int $id): JsonResponse
    {
        $usuario = User::find($id);

        if ($usuario === null) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ((int) $usuario->id === (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes cambiar tu propio rol.',
            ], 422);
        }

        $usuario->role = $request->validated('role');
        $usuario->save();

        // Revocar tokens del usuario para que el cambio de rol surta efecto.
        $usuario->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rol del usuario actualizado correctamente.',
            'data'    => [
                'id'   => $usuario->id,
                'name' => $usuario->name,
                'role' => $usuario->role,
            ],
        ]);
    }

    /**
     * Desactiva (soft delete) un usuario. No permitido sobre uno mismo.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = User::find($id);

        if ($usuario === null) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ((int) $usuario->id === (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes desactivar tu propia cuenta.',
            ], 422);
        }

        // Revoca los tokens activos antes de desactivar.
        $usuario->tokens()->delete();
        $usuario->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario desactivado correctamente.',
        ]);
    }
}
