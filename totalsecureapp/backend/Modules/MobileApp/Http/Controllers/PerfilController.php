<?php

namespace Modules\MobileApp\Http\Controllers;

use App\Services\PermisosApiService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\MobileApp\Models\users;

class PerfilController extends Controller {

    protected PermisosApiService $permisos;

    public function __construct(PermisosApiService $permisos)
    {
        $this->permisos = $permisos;
    }

    /**
     * POST /api/seleccionar_perfil
     * Retorna los perfiles (roles) del usuario autenticado.
     */
    public function seleccionar_perfil(Request $request) {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $perfiles = $user->roles()
            ->where('estado', 1)
            ->orderBy('id')
            ->get(['roles.id', 'name', 'descripcion'])
            ->map(fn ($r) => [
                'id'          => $r->id,
                'nombre'      => $r->name,
                'descripcion' => $r->descripcion,
            ])
            ->values();

        return response()->json(['perfiles' => $perfiles]);
    }

    /**
     * POST /api/procesar_perfil
     * Valida que el perfil pertenezca al usuario y retorna sus permisos.
     */
    public function procesar_perfil(Request $request) {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), ['id' => 'required|integer']);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $perfil = $user->roles()
            ->where('roles.id', $request->id)
            ->where('estado', 1)
            ->first(['roles.id', 'name', 'descripcion']);

        if (!$perfil) {
            return response()->json(['message' => 'El perfil no pertenece al usuario'], 403);
        }

        $permisos = $this->permisos->paraRol((int) $perfil->id);

        return response()->json([
            'perfil' => [
                'id'          => $perfil->id,
                'nombre'      => $perfil->name,
                'descripcion' => $perfil->descripcion,
            ],
            'permisos' => $permisos,
        ]);
    }
}
