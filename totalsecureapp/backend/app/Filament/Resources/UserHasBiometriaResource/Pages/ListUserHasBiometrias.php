<?php

namespace App\Filament\Resources\UserHasBiometriaResource\Pages;

use App\Filament\Resources\UserHasBiometriaResource;
use Filament\Pages\Actions;
use App\Filament\Pages\ListadoBase;

class ListUserHasBiometrias extends ListadoBase
{
    protected static string $resource = UserHasBiometriaResource::class;

    protected function getActions(): array { return []; }

    protected function getTitle(): string {
        return 'Biometria';
    }
}
