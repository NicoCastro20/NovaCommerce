<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSuscripcionController extends Controller
{
    /**
     * Listado de usuarios Premium (activos o con expiración futura).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min(100, $perPage));

        $usuarios = User::query()
            ->where(function ($q): void {
                $q->where('is_premium', true)
                  ->orWhere('premium_until', '>', now());
            })
            ->orderByDesc('premium_until')
            ->paginate($perPage);

        $items = $usuarios->getCollection()->map(fn (User $u) => [
            'id'             => $u->id,
            'name'           => $u->name,
            'email'          => $u->email,
            'role'           => $u->role,
            'is_premium'     => (bool) $u->is_premium,
            'activa'         => $u->esPremium(),
            'premium_since'  => $u->premium_since,
            'premium_until'  => $u->premium_until,
            'dias_restantes' => $u->diasRestantesPremium(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Suscriptores Premium obtenidos correctamente.',
            'data'    => $items,
            'meta'    => [
                'current_page' => $usuarios->currentPage(),
                'last_page'    => $usuarios->lastPage(),
                'per_page'     => $usuarios->perPage(),
                'total'        => $usuarios->total(),
            ],
        ]);
    }
}
