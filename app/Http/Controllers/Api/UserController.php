<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
                    'avatar'            => $usuario->avatarUrl(),
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
     *
     * El avatar llega como archivo (multipart). Los administradores no pueden
     * cambiar su avatar — siempre se muestra la inicial del nombre.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $datos   = $request->validated();

        if ($request->hasFile('avatar')) {
            if ($usuario->esAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los administradores no pueden cambiar su avatar.',
                ], 403);
            }

            // Borrar el avatar anterior si era una ruta relativa nuestra
            // (no tocar las URLs externas heredadas).
            if (! empty($usuario->avatar)
                && ! str_starts_with($usuario->avatar, 'http://')
                && ! str_starts_with($usuario->avatar, 'https://')
            ) {
                Storage::disk('public')->delete($usuario->avatar);
            }

            $archivo   = $request->file('avatar');
            $extension = $archivo->getClientOriginalExtension() ?: $archivo->extension();
            $nombre    = sprintf('avatars/%d_%s.%s', $usuario->id, now()->timestamp, strtolower($extension));
            $archivo->storeAs('', $nombre, 'public');
            $datos['avatar'] = $nombre;
        } else {
            // Si no llega archivo, no tocar el avatar existente.
            unset($datos['avatar']);
        }

        $usuario->fill($datos)->save();

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente.',
            'data'    => [
                'user' => [
                    'id'             => $usuario->id,
                    'name'           => $usuario->name,
                    'email'          => $usuario->email,
                    'phone'          => $usuario->phone,
                    'avatar'         => $usuario->avatarUrl(),
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
