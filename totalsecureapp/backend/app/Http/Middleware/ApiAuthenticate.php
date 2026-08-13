<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiAuthenticate {
    public function handle(Request $request, Closure $next) {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        /*if ($user->token()->expires_at < now()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }*/
        return $next($request);
    }
}
