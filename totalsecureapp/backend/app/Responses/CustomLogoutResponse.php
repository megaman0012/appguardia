<?php

namespace App\Responses;

use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;

class CustomLogoutResponse implements LogoutResponseContract {
    public function toResponse($request): RedirectResponse {
        return redirect()->to('acceso/login');
    }
}
