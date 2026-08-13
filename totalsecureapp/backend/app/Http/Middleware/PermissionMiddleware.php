<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Session;
use Auth;

class PermissionMiddleware{
    public function handle(Request $request, Closure $next, $permission){
        if(!Auth::check() || Session::get('usuID') == ''){
            return redirect('/acceso/login');
        }

        if (Auth::guest()) {
            return redirect('/acceso/logout');
        }

        if (!Auth::user()->can($permission)) {
            abort(403, 'Acceso Denegado');
        }
        return $next($request);
    }
}
