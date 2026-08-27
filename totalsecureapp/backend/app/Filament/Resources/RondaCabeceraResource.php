<?php

namespace App\Filament\Resources;

use App\Support\PerfilPanel;

use App\Filament\Resources\RondaCabeceraResource\Pages;
use App\Filament\Resources\RondaCabeceraResource\RelationManagers;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Modules\Administracion\Models\ronda_cabecera;
use Modules\Administracion\Models\OrganizacionInstitucion;

use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\BadgeColumn;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Modules\Administracion\Models\UserHasInstitucion;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Session;

class RondaCabeceraResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reporteria';
    }
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Rondas';
    protected static ?string $model = ronda_cabecera::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.cliente', 'users'];

    protected static ?string $navigationIcon = 'heroicon-o-share';

    public static function form(Form $form): Form{ return $form->schema([]); }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rc_id')->size('sm')
                    ->label('Codigo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Institucion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('users.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('rc_fecha_inicio')->size('sm')
                    ->label('Inicio')
                    ->sortable()
                    ->toggleable()
                    ->dateTime(),
                TextColumn::make('rc_fecha_fin')->size('sm')
                    ->label('Fin')
                    ->sortable()
                    ->toggleable()
                    ->dateTime(),
                TextColumn::make('rc_estado_ronda')->size('sm')
                    ->label('Estado Ronda')
                    ->toggleable()
                    ->searchable(),
                BadgeColumn::make('rc_estado_ronda')->size('sm')
                    ->label('Estado Alerta')
                    ->colors([
                        'warning' => 'Iniciada',
                        'danger' => 'Cancelada',
                        'success' => 'Finalizada',
                    ])
                    ->toggleable()
                    ->searchable(),
                /*ToggleColumn::make('rc_estado')
                    ->label('Estado')
                    ->searchable(false)
                    ->toggleable(),*/
            ])
            ->filters([
                Filter::make('Filtro Rondas')
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
                                $query->whereDate('rc_fecha_inicio', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date) =>
                                $query->whereDate('rc_fecha_inicio', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Action::make('verDetalle')->label('Detalles')
                ->url(fn(ronda_cabecera $record)=>
                    RondaDetalleResource::getUrl(
                        'index', [ 'ronda' => $record->rc_id ]
                    )
                )
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->label('Exportar a Excel'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListRondaCabeceras::route('/'),
            //'create' => Pages\CreateRondaCabecera::route('/create'),
            //'edit' => Pages\EditRondaCabecera::route('/{record}/edit'),
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
            return $query->whereIn('rc_ins_code', $institucionesCodes);
        }

        // El Lider Operativo ve los locales de su(s) pais(es). Sin paises
        // asignados no ve nada: un lider mal configurado no debe terminar
        // con acceso global.
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('rc_ins_code', $localesDelPais);
        }
        return $query;
    }

}
