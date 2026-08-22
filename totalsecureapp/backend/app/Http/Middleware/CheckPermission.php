<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if (!$user->can($permission)) {
            return response()->json([
                'message' => 'No tiene permiso para esta acción',
                'required_permission' => $permission,
            ], 403);
        }
        return $next($request);
    }
}
