<?php

namespace App\Filament\Resources\UsersResource\Pages;

use App\Filament\Resources\UsersResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListUsers extends ListadoBase {
    protected static string $resource = UsersResource::class;
    protected function getActions(): array {
        return [
            Actions\CreateAction::make()->label('Nuevo Usuario'),
        ];
    }
    protected function getTitle(): string {
        return 'Usuarios';
    }
}
