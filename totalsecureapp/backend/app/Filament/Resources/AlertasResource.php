<?php

namespace App\Filament\Resources;

use Filament\Actions;
use App\Support\PerfilPanel;

use App\Filament\Resources\AlertasResource\Pages;
use App\Filament\Resources\AlertasResource\RelationManagers;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\OrganizacionInstitucion;

use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\BadgeColumn;
use Session;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\UserHasInstitucion;
use App\Filament\Tables\Descarga;

class AlertasResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reportería';
    }
    protected static ?int $navigationSort = 6;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'alerta';
    protected static ?string $pluralModelLabel = 'alertas';
    protected static ?string $navigationLabel = 'Alertas';
    protected static ?string $model = Alertas::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.cliente', 'usuario'];
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-triangle';
    public static function form(Schema $schema): Schema{ return $schema->schema([]); }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('al_code')->size('sm')
                    ->label('Código')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                    ->label('Cliente')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Local')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->toggleable()
                    ->searchable(),
                BadgeColumn::make('al_estado_alerta')->size('sm')
                    ->label('Estado Alerta')
                    ->colors([
                        'success' => 'Finalizada',
                        'danger' => 'Pendiente',
                    ])
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('al_fecha')->size('sm')
                    ->label('Fecha')
                    ->sortable(),
                /*ToggleColumn::make('al_estado')
                    ->label('Estado')
                    ->searchable(false)
                    ->toggleable(),*/
            ])
            ->filters([
                Filter::make('al_fecha')
                ->label('Rango de fecha')
                ->form([
                    DatePicker::make('from')
                        ->label('Desde'),
                    DatePicker::make('until')
                        ->label('Hasta'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['from'],
                            fn (Builder $query, $date) =>
                            $query->whereDate('al_fecha', '>=', $date)
                        )
                        ->when(
                            $data['until'],
                            fn (Builder $query, $date) =>
                            $query->whereDate('al_fecha', '<=', $date)
                        );
                }),
            ])
            ->actions([
                Actions\Action::make('gmap')
                    ->label('Mapa')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->al_lat},{$record->al_lng}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary')
                    // La app manda 0/0 cuando no hubo lectura de GPS: el enlace
                    // llevaria al golfo de Guinea.
                    ->visible(fn ($record) => abs((float) $record->al_lat) > 0.0001
                        || abs((float) $record->al_lng) > 0.0001),

                /*
                 * Cerrar la emergencia, que es lo que faltaba.
                 *
                 * ⚠️ Las alertas se quedaban en «en atencion» para siempre: el
                 * panel las listaba y **no habia ninguna forma de cambiarles el
                 * estado**, aunque `AlertaService::atenderAlerta()` existiera
                 * desde el principio. Nadie lo llamaba.
                 *
                 * El circuito real es: el guardia aprieta el boton, Consola
                 * llama al punto y averigua que paso, y cierra dejando escrito
                 * el resultado. Por eso el comentario es obligatorio: una
                 * emergencia cerrada sin decir que paso no se distingue de una
                 * que nadie atendio.
                 */
                Actions\Action::make('finalizar')
                    ->label('Finalizar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Alertas $record) => PerfilPanel::puedeOperar()
                        && in_array($record->al_estado_alerta, ['pendiente', 'en_atencion'], true))
                    ->form([
                        Forms\Components\Textarea::make('observacion')
                            ->label('¿Qué pasó?')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Queda en el historial de la alerta, con su hora y quién la cerró.'),
                    ])
                    ->action(function (Alertas $record, array $data) {
                        try {
                            app(\App\Services\AlertaService::class)
                                ->atenderAlerta($record, (int) \Session::get('usuID'), $data['observacion']);

                            \Filament\Notifications\Notification::make()
                                ->title('Emergencia finalizada')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se pudo finalizar')
                                ->body($e->getMessage())
                                ->danger()->send();
                        }
                    }),

                /*
                 * Falsa alarma: se cancela, no se finaliza.
                 *
                 * Separarlas importa para medir: una emergencia real atendida y
                 * un boton apretado sin querer no pueden contar como lo mismo.
                 */
                Actions\Action::make('cancelar')
                    ->label('Falsa alarma')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (Alertas $record) => PerfilPanel::puedeOperar()
                        && in_array($record->al_estado_alerta, ['pendiente', 'en_atencion'], true))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motivo')
                            ->label('Motivo')
                            ->required()
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->action(function (Alertas $record, array $data) {
                        try {
                            app(\App\Services\AlertaService::class)
                                ->cancelarAlerta($record, (int) \Session::get('usuID'), $data['motivo']);

                            \Filament\Notifications\Notification::make()
                                ->title('Marcada como falsa alarma')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se pudo cancelar')
                                ->body($e->getMessage())
                                ->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([
                Descarga::enLote('alertas'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListAlertas::route('/'),
            //'create' => Pages\CreateAlertas::route('/create'),
            //'edit' => Pages\EditAlertas::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
        if(PerfilPanel::alcanceEsPorInstitucion()){
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('al_ins_code', $institucionesCodes);
        }

        // El Lider Operativo ve los locales de su(s) pais(es). Sin paises
        // asignados no ve nada: un lider mal configurado no debe terminar
        // con acceso global.
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('al_ins_code', $localesDelPais);
        }
        return $query;
    }

}
