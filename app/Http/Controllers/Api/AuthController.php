<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterEmpresaRequest;
use App\Http\Requests\Api\RegisterUsuarioRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario particular y emite un token Sanctum.
     */
    public function register(RegisterUsuarioRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $usuario = User::create([
            'name'     => $datos['name'],
            'email'    => $datos['email'],
            'password' => Hash::make($datos['password']),
            'phone'    => $datos['phone'] ?? null,
            'role'     => 'usuario',
        ]);

        $token = $usuario->createToken('token-api')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Cuenta creada correctamente.',
            'data'    => [
                'user'       => $this->datosUsuario($usuario),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Registra una nueva empresa (rol 'empresa') con NIF/CIF y nombre comercial.
     */
    public function registerEmpresa(RegisterEmpresaRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $empresa = User::create([
            'name'         => $datos['name'],
            'email'        => $datos['email'],
            'password'     => Hash::make($datos['password']),
            'phone'        => $datos['phone'] ?? null,
            'role'         => 'empresa',
            'nif_cif'      => $datos['nif_cif'],
            'company_name' => $datos['company_name'],
        ]);

        $token = $empresa->createToken('token-api')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Empresa registrada correctamente.',
            'data'    => [
                'user'       => $this->datosUsuario($empresa),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Autentica al usuario con email y contraseña y devuelve un token Sanctum.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credenciales = $request->validated();

        if (! Auth::attempt($credenciales)) {
            return response()->json([
                'success' => false,
                'message' => 'Correo o contraseña incorrectos.',
            ], 401);
        }

        /** @var User $usuario */
        $usuario = Auth::user();
        $token   = $usuario->createToken('token-api')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Sesión iniciada correctamente.',
            'data'    => [
                'user'       => $this->datosUsuario($usuario),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Cierra la sesión revocando el token actual del usuario.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /**
     * Devuelve la representación pública de un usuario.
     *
     * @return array<string, mixed>
     */
    private function datosUsuario(User $usuario): array
    {
        return [
            'id'             => $usuario->id,
            'name'           => $usuario->name,
            'email'          => $usuario->email,
            'phone'          => $usuario->phone,
            'avatar'         => $usuario->avatar,
            'role'           => $usuario->role,
            'nif_cif'        => $usuario->nif_cif,
            'company_name'   => $usuario->company_name,
            'is_premium'     => (bool) $usuario->is_premium,
            'premium_active' => $usuario->esPremium(),
            'premium_since'  => $usuario->premium_since,
            'premium_until'  => $usuario->premium_until,
            'created_at'     => $usuario->created_at,
        ];
    }
}
