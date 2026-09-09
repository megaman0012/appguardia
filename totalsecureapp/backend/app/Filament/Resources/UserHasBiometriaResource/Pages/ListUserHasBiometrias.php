<?php

namespace App\Filament\Resources\UserHasBiometriaResource\Pages;

use App\Filament\Resources\UserHasBiometriaResource;
use Filament\Actions;
use App\Filament\Pages\ListadoBase;

class ListUserHasBiometrias extends ListadoBase
{
    protected static string $resource = UserHasBiometriaResource::class;

    protected function accionesPropias(): array { return []; }

    public function getTitle(): string {
        return 'Biometria';
    }
}
