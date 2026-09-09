<?php

namespace App\Filament\Resources\UserHasBiometriaResource\Pages;

use App\Filament\Resources\UserHasBiometriaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserHasBiometria extends EditRecord
{
    protected static string $resource = UserHasBiometriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
