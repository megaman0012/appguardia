<?php

namespace App\Filament\Resources\PlantillaResource\Pages;

use App\Filament\Resources\PlantillaResource;
use App\Services\PlantillaTurnoService;
use App\Support\PerfilPanel;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlantilla extends EditRecord
{
    protected static string $resource = PlantillaResource::class;

    protected function getActions(): array
    {
        return [
            // Primero se revisa sin tocar nada: ver los problemas antes de crear
            // cientos de turnos es la mitad del valor de la plantilla.
            Actions\Action::make('revisar')
                ->label('Revisar')
                ->icon('heroicon-o-search')
                ->color('secondary')
                ->form($this->camposDePeriodo())
                ->action(fn (array $data) => $this->revisar($data))
                ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),

            Actions\Action::make('generar')
                ->label('Generar turnos')
                ->icon('heroicon-o-calendar')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar los turnos del período')
                ->modalSubheading('Se reemplazan los turnos de este cuadrante que aún no tengan marcaje. Los ya marcados y los cargados a mano no se tocan.')
                ->form($this->camposDePeriodo())
                ->action(fn (array $data) => $this->generar($data))
                ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),
        ];
    }

    /** @return array<int, DatePicker> */
    private function camposDePeriodo(): array
    {
        return [
            DatePicker::make('desde')
                ->label('Desde')
                ->required()
                ->default($this->record->pl_vigencia_desde ?? now()->startOfMonth()),
            DatePicker::make('hasta')
                ->label('Hasta')
                ->required()
                ->default($this->record->pl_vigencia_hasta ?? now()->endOfMonth()),
        ];
    }

    private function revisar(array $data): void
    {
        $revision = app(PlantillaTurnoService::class)->validar(
            $this->record,
            Carbon::parse($data['desde']),
            Carbon::parse($data['hasta'])
        );

        $this->informar($revision, null);
    }

    private function generar(array $data): void
    {
        $r = app(PlantillaTurnoService::class)->generar(
            $this->record,
            Carbon::parse($data['desde']),
            Carbon::parse($data['hasta'])
        );

        $this->informar($r, $r['creados'] ?? 0, $r['conservados'] ?? 0);
    }

    /**
     * Los errores bloquean; los avisos no.
     *
     * Una franja sin cubrir o un descanso corto son decisiones del negocio, no
     * datos invalidos: se informan y el cuadrante se publica igual.
     */
    private function informar(array $r, ?int $creados, int $conservados = 0): void
    {
        if (!empty($r['errores'])) {
            Notification::make()
                ->title('No se generaron turnos')
                ->body("• " . implode("\n• ", array_slice($r['errores'], 0, 8)))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if (!empty($r['avisos'])) {
            Notification::make()
                ->title('Revisar antes de continuar')
                ->body("• " . implode("\n• ", array_slice($r['avisos'], 0, 8)))
                ->warning()
                ->persistent()
                ->send();
        }

        if ($creados === null) {
            Notification::make()
                ->title('Sin errores')
                ->body('El cuadrante se puede generar.')
                ->success()
                ->send();

            return;
        }

        $texto = "{$creados} turnos generados.";
        if ($conservados > 0) {
            $texto .= " Se conservaron {$conservados} que ya tenían marcaje.";
        }

        Notification::make()->title('Listo')->body($texto)->success()->send();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['pl_updated_user'] = auth()->id();

        return $data;
    }

    protected function getFormActions(): array
    {
        return PerfilPanel::puedeAdministrarLocales() ? parent::getFormActions() : [];
    }
}
