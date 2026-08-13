<?php

namespace App\Filament\Resources\UserHasBiometriaResource\Pages;

use App\Filament\Resources\UserHasBiometriaResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserHasBiometrias extends ListRecords
{
    protected static string $resource = UserHasBiometriaResource::class;

    protected function getActions(): array { return []; }

    protected function getTitle(): string {
        return 'Biometria';
    }
}
