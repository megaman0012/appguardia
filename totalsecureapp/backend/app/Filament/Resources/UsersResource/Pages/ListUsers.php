<?php

namespace App\Filament\Resources\UsersResource\Pages;

use App\Filament\Pages\ListadoBase;
use App\Filament\Resources\UsersResource;
use App\Services\UsuarioImportService;
use App\Support\PerfilPanel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListUsers extends ListadoBase
{
    protected static string $resource = UsersResource::class;

    protected function accionesPropias(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo Usuario'),

            // Que el operador no tipee los nombres de los locales evita la mitad
            // de los errores de carga: el modelo sale con datos de esta base.
            Actions\Action::make('descargarModelo')
                ->label('Modelo de carga')
                ->icon('heroicon-o-download')
                ->color('secondary')
                ->action(fn () => $this->descargarModelo())
                ->visible(fn () => PerfilPanel::puedeGestionarPersonal()),

            // ⚠️ Primero se revisa sin escribir. Ver los problemas antes de
            // crear trescientos usuarios es la mitad del valor de la carga.
            Actions\Action::make('revisarCarga')
                ->label('Revisar archivo')
                ->icon('heroicon-o-search')
                ->color('secondary')
                ->modalHeading('Revisar el archivo sin crear nada')
                ->modalSubheading('Se leen todas las filas y se informan los problemas. No se escribe nada en la base.')
                ->form($this->campoDeArchivo())
                ->action(fn (array $data) => $this->revisar($data))
                ->visible(fn () => PerfilPanel::puedeGestionarPersonal()),

            Actions\Action::make('cargarUsuarios')
                ->label('Cargar usuarios')
                ->icon('heroicon-o-upload')
                ->color('primary')
                ->modalHeading('Cargar usuarios desde un archivo')
                ->modalSubheading('Crea el usuario, su rol, su gestión abierta y el vínculo a sus locales. Las claves temporales se muestran al terminar: anótelas, no se vuelven a mostrar.')
                ->form($this->campoDeArchivo())
                ->action(fn (array $data) => $this->cargar($data))
                ->visible(fn () => PerfilPanel::puedeGestionarPersonal()),
        ];
    }

    protected function getTitle(): string
    {
        return 'Usuarios';
    }

    /** @return array<int,mixed> */
    private function campoDeArchivo(): array
    {
        $servicio = app(UsuarioImportService::class);

        return [
            Placeholder::make('columnas')
                ->label('Columnas del archivo')
                ->content(
                    'Obligatorias: ' . implode(', ', UsuarioImportService::COLUMNAS) . '. ' .
                    'Opcionales: ' . implode(', ', UsuarioImportService::OPCIONALES) . '. ' .
                    'La clave NO va en el archivo: se genera una por persona.'
                ),

            FileUpload::make('archivo')
                ->label('Archivo CSV')
                ->required()
                ->disk('local')
                ->directory('importaciones')
                // Un CSV llega con mime distinto segun quien lo genero (Excel,
                // LibreOffice, Sheets); el contenido se valida fila por fila.
                ->acceptedFileTypes([
                    'text/csv', 'text/plain', 'application/csv',
                    'application/vnd.ms-excel', 'application/octet-stream',
                ])
                ->helperText('Varios locales en la misma fila: separados por «|». Descargue el modelo si tiene dudas.'),
        ];
    }

    private function descargarModelo(): StreamedResponse
    {
        $csv = app(UsuarioImportService::class)->plantillaDeEjemplo();

        return response()->streamDownload(function () use ($csv) {
            // BOM para que Excel abra los acentos bien.
            echo "\xEF\xBB\xBF" . $csv;
        }, 'modelo-carga-usuarios.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function revisar(array $data): void
    {
        $ruta = Storage::disk('local')->path($data['archivo']);
        $r = app(UsuarioImportService::class)->analizar($ruta);
        Storage::disk('local')->delete($data['archivo']);

        if ($r['errores'] !== []) {
            Notification::make()
                ->title('El archivo tiene problemas')
                ->body('• ' . implode("\n• ", array_slice($r['errores'], 0, 12)))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $cuerpo = count($r['filas']) . ' usuarios listos para crear.';

        if ($r['avisos'] !== []) {
            $cuerpo .= "\n\n• " . implode("\n• ", array_slice($r['avisos'], 0, 10));
        }

        Notification::make()
            ->title('Archivo revisado, no se creó nada')
            ->body($cuerpo)
            ->success()
            ->persistent()
            ->send();
    }

    private function cargar(array $data): void
    {
        $ruta = Storage::disk('local')->path($data['archivo']);
        $r = app(UsuarioImportService::class)->importar($ruta, auth()->id());
        Storage::disk('local')->delete($data['archivo']);

        if ($r['errores'] !== []) {
            Notification::make()
                ->title('No se creó ningún usuario')
                ->body('• ' . implode("\n• ", array_slice($r['errores'], 0, 12)))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        // Las claves se muestran una sola vez, en la notificacion. No se
        // guardan en ningun lado ni se envian por correo: dejarlas escritas en
        // algun archivo del servidor seria peor que mostrarlas aca.
        $lineas = array_map(
            fn ($c) => "{$c['cedula']}  {$c['nombre']}  →  {$c['clave']}",
            $r['claves']
        );

        $cuerpo = "{$r['creados']} usuarios creados.\n\nClaves temporales (anótelas ahora):\n"
            . implode("\n", array_slice($lineas, 0, 40));

        if (count($lineas) > 40) {
            $cuerpo .= "\n… y " . (count($lineas) - 40) . ' más.';
        }

        if ($r['avisos'] !== []) {
            $cuerpo .= "\n\nObservaciones:\n• " . implode("\n• ", array_slice($r['avisos'], 0, 10));
        }

        Notification::make()
            ->title('Usuarios cargados')
            ->body($cuerpo)
            ->success()
            ->persistent()
            ->send();
    }
}
