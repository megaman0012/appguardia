<?php

namespace App\Filament\Resources\PlantillaResource\Pages;

use App\Filament\Resources\PlantillaResource;
use App\Services\CuadranteGrilla;
use App\Support\PerfilPanel;
use Filament\Pages\Actions;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * El cuadrante semanal visto como grilla.
 *
 * Es también la pantalla que le faltaba al Supervisor: podía ver el listado de
 * cuadrantes pero no abrir ninguno, porque la única vista del detalle era la de
 * edición y esa la tiene cerrada. Acá lo consulta sin poder tocarlo, que es
 * exactamente su rol.
 */
class VerGrilla extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PlantillaResource::class;
    protected static string $view = 'filament.pages.cuadrante-grilla';

    public array $grilla = [];

    public function mount($record): void
    {
        // Page (a diferencia de EditRecord) no autoriza solo: hay que pedirlo.
        static::authorizeResourceAccess();

        $this->record = $this->resolveRecord($record);
        $this->grilla = app(CuadranteGrilla::class)->armar($this->record);
    }

    protected function getTitle(): string
    {
        return 'Cuadrante: ' . $this->record->pl_nombre;
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('editar')
                ->label('Editar franjas')
                ->icon('heroicon-o-pencil')
                ->url(fn () => PlantillaResource::getUrl('edit', ['record' => $this->record]))
                ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),
        ];
    }
}
