<?php

namespace App\Filament\Resources\PlantillaResource\Pages;

use App\Filament\Resources\PlantillaResource;
use App\Services\PlantillaImportService;
use App\Services\PlantillaTurnoService;
use App\Support\PerfilPanel;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditPlantilla extends EditRecord
{
    protected static string $resource = PlantillaResource::class;

    protected function getActions(): array
    {
        return [
            // Descargar el modelo con los puestos del local ya escritos: que el
            // lider no tipee los nombres evita la mitad de los errores de carga.
            Actions\Action::make('descargarModelo')
                ->label('Descargar modelo')
                ->icon('heroicon-o-download')
                ->color('secondary')
                ->action(fn () => $this->descargarModelo())
                ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),

            Actions\Action::make('importar')
                ->label('Importar cuadrante')
                ->icon('heroicon-o-upload')
                ->color('secondary')
                ->modalHeading('Cargar el cuadrante desde un archivo')
                ->modalSubheading('Reemplaza las franjas actuales por las del archivo. Los turnos ya generados no se tocan: se regeneran después.')
                ->form([
                    FileUpload::make('archivo')
                        ->label('Archivo CSV')
                        ->required()
                        ->disk('local')
                        ->directory('importaciones')
                        // Un CSV llega con mime distinto segun quien lo generó
                        // (Excel, LibreOffice, Sheets); el contenido igual se
                        // valida fila por fila, asi que se es amplio aqui.
                        ->acceptedFileTypes([
                            'text/csv', 'text/plain', 'application/csv',
                            'application/vnd.ms-excel', 'application/octet-stream',
                        ])
                        ->helperText('Columnas: cedula, puesto, dia, hora_inicio, hora_fin'),
                ])
                ->action(fn (array $data) => $this->importar($data))
                ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),

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

    private function descargarModelo(): StreamedResponse
    {
        $csv = app(PlantillaImportService::class)->plantillaDeEjemplo($this->record);
        $nombre = 'cuadrante-' . $this->record->pl_id . '.csv';

        return response()->streamDownload(function () use ($csv) {
            // BOM para que Excel abra los acentos bien al descargarlo.
            echo "\xEF\xBB\xBF" . $csv;
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function importar(array $data): void
    {
        $ruta = Storage::disk('local')->path($data['archivo']);

        $r = app(PlantillaImportService::class)->importar($this->record, $ruta);

        // El archivo subido no se conserva: ya quedo volcado en la plantilla.
        Storage::disk('local')->delete($data['archivo']);

        if (!empty($r['errores'])) {
            Notification::make()
                ->title('No se importó nada')
                ->body("• " . implode("\n• ", array_slice($r['errores'], 0, 10)))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if (!empty($r['avisos'])) {
            Notification::make()
                ->title('Importado con observaciones')
                ->body("• " . implode("\n• ", array_slice($r['avisos'], 0, 10)))
                ->warning()
                ->persistent()
                ->send();
        }

        Notification::make()
            ->title('Cuadrante importado')
            ->body("{$r['franjas']} franjas y {$r['asignaciones']} asignaciones. Ahora genere los turnos del período.")
            ->success()
            ->send();
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
