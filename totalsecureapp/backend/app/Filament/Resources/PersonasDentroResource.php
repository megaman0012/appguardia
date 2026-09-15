<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonasDentroResource\Pages;
use App\Services\AccesoService;
use App\Support\PerfilPanel;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Administracion\Models\Acceso;

/**
 * Quien esta adentro ahora mismo, y como darle salida.
 *
 * Nace de dos cosas que faltaban para cerrar un acceso desde la web:
 *
 *  1. **No habia forma de ver quien seguia adentro.** El listado de Accesos
 *     muestra todo el historico mezclado, y averiguar quien no habia salido
 *     significaba leerlo fila por fila.
 *  2. **No habia forma de registrar la salida.** `AccesoService::registrarSalida()`
 *     existia desde siempre, pero solo lo llamaba la API de la tablet. Si el
 *     visitante se iba por otra puerta, o la tablet estaba ocupada, el acceso
 *     quedaba abierto para siempre.
 *
 * La busqueda cubre documento, nombres, apellidos y placa, que es como se
 * pregunta por alguien en una garita: «el senor Perez», «la cedula 09...», «el
 * furgon ABC-123».
 */
class PersonasDentroResource extends Resource
{
    protected static ?string $model = Acceso::class;

    protected static ?string $navigationLabel = 'Personas dentro';
    protected static ?string $modelLabel = 'persona dentro';
    protected static ?string $pluralModelLabel = 'personas dentro';
    protected static ?int $navigationSort = 1;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationGroup(): ?string
    {
        return 'Operación';
    }

    /** El contador en el menu: cuanta gente hay adentro sin entrar a mirar. */
    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->count();

        return $n > 0 ? (string) $n : null;
    }

    protected const RELACIONES_TABLA = ['institucion', 'accesoPersona', 'vehiculo'];

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('accesoPersona.ap_documento')
                    ->label('Documento')->size('sm')->searchable(),

                TextColumn::make('accesoPersona.ap_nombres')
                    ->label('Nombres')->size('sm')->searchable(),

                TextColumn::make('accesoPersona.ap_apellidos')
                    ->label('Apellidos')->size('sm')->searchable(),

                TextColumn::make('vehiculo.av_patente')
                    ->label('Placa')->size('sm')->searchable()
                    ->placeholder('sin vehículo'),

                TextColumn::make('institucion.ins_descripcion')
                    ->label('Local')->size('sm')->searchable()->toggleable(),

                TextColumn::make('ac_created_at')
                    ->label('Ingresó')->size('sm')->dateTime('d/m/Y H:i')->sortable(),

                /*
                 * El dato que hace util la pantalla: no importa tanto la hora de
                 * ingreso como cuanto lleva adentro. Una visita de doce horas es
                 * casi siempre una salida que nadie registro.
                 */
                TextColumn::make('tiempo_dentro')
                    ->label('Lleva dentro')
                    ->size('sm')
                    ->state(fn (Acceso $record) => $record->ac_created_at
                        ? Carbon::parse($record->ac_created_at)->diffForHumans(null, true)
                        : '—')
                    ->color(fn (Acceso $record) => $record->ac_created_at
                        && Carbon::parse($record->ac_created_at)->diffInHours() >= 12
                            ? 'danger'
                            : null),
            ])
            ->actions([
                Actions\Action::make('registrar_salida')
                    ->label('Registrar salida')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Acceso $record) => 'Registrar la salida de '
                        . trim((optional($record->accesoPersona)->ap_nombres ?? '') . ' '
                             . (optional($record->accesoPersona)->ap_apellidos ?? '')))
                    ->modalDescription('Queda con la hora de ahora. No se puede deshacer desde el panel.')
                    ->action(function (Acceso $record) {
                        try {
                            /*
                             * Sin coordenadas: esta salida la registra alguien
                             * desde el panel, no el dispositivo del punto. Poner
                             * la ubicacion del servidor seria inventar un dato
                             * que despues se leeria como la posicion real.
                             */
                            app(AccesoService::class)->registrarSalida($record->ac_code);

                            Notification::make()
                                ->title('Salida registrada')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo registrar la salida')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('ac_created_at', 'asc')
            ->emptyStateHeading('No hay nadie adentro')
            ->poll('60s');
    }

    /**
     * Solo los accesos en curso, y acotados por el alcance de quien mira.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(self::RELACIONES_TABLA)
            ->where('ac_estado_acceso', Acceso::ESTADO_EN_CURSO);

        $locales = PerfilPanel::localesVisibles();

        if ($locales === null) {
            return $query;
        }

        if (empty($locales)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('ac_ins_code', $locales);
    }

    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    public static function canCreate(): bool
    {
        // Los ingresos se registran en el punto, desde la tablet.
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonasDentro::route('/'),
        ];
    }
}
