<?php

namespace App\Filament\Resources\UserHasGestionResource\Pages;

use Closure;
use App\Filament\Resources\UserHasGestionResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Acceso\Models\user_has_gestions;

class ListUserHasGestions extends ListRecords
{
    protected static string $resource = UserHasGestionResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->label('Nueva Gestion'),
        ];
    }

    protected function getTitle(): string {
        return 'Usuarios > Gestion';
    }

    public function isTableRecordSelectable(): ?Closure {
        return fn (user_has_gestions $record): bool => false;
    }

}
