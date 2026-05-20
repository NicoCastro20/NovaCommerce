<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Devuelve los datos del usuario autenticado.
     */
    public function profile(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Perfil obtenido correctamente.',
            'data'    => [
                'user' => [
                    'id'                => $usuario->id,
                    'name'              => $usuario->name,
                    'email'             => $usuario->email,
                    'phone'             => $usuario->phone,
                    'avatar'            => $usuario->avatar,
                    'role'              => $usuario->role,
                    'nif_cif'           => $usuario->nif_cif,
                    'company_name'      => $usuario->company_name,
                    'is_premium'        => (bool) $usuario->is_premium,
                    'premium_active'    => $usuario->esPremium(),
                    'premium_since'     => $usuario->premium_since,
                    'premium_until'     => $usuario->premium_until,
                    'email_verified_at' => $usuario->email_verified_at,
                    'created_at'        => $usuario->created_at,
                ],
            ],
        ]);
    }

    /**
     * Actualiza los datos del perfil (nombre, teléfono y avatar).
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $usuario->fill($request->validated())->save();

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente.',
            'data'    => [
                'user' => [
                    'id'             => $usuario->id,
                    'name'           => $usuario->name,
                    'email'          => $usuario->email,
                    'phone'          => $usuario->phone,
                    'avatar'         => $usuario->avatar,
                    'role'           => $usuario->role,
                    'is_premium'     => (bool) $usuario->is_premium,
                    'premium_active' => $usuario->esPremium(),
                    'premium_until'  => $usuario->premium_until,
                ],
            ],
        ]);
    }

    /**
     * Cambia la contraseña del usuario y revoca todos sus tokens previos.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $usuario = $request->user();

        $usuario->forceFill([
            'password' => Hash::make($request->input('new_password')),
        ])->save();

        // Revocar todos los tokens existentes para forzar un nuevo inicio de sesión.
        $usuario->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente. Vuelve a iniciar sesión.',
        ]);
    }
}
