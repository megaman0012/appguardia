<?php

namespace App\Filament\Resources;

use App\Support\PerfilPanel;

use App\Filament\Resources\NovedadResource\Pages;
use App\Filament\Resources\NovedadResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\Novedad;
use Modules\Administracion\Models\UserHasInstitucion;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Session;

class NovedadResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reporteria';
    }
    protected static ?string $navigationLabel = 'Novedades';
    protected static ?int $navigationSort = 12;
    protected static ?string $model = Novedad::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.organizacionSede.organizacion', 'institucion.organizacionSede.sede', 'users'];
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    public static function form(Form $form): Form { return $form->schema([ ]); }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nv_id')->size('sm')
                    ->label('Codigo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('institucion.organizacionSede.sede.ps_descripcion')->size('sm')
                    ->label('Sede')
                    ->toggleable(),
                TextColumn::make('institucion.organizacionSede.organizacion.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Institucion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('users.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->searchable(),
                TextColumn::make('nv_observacion')->size('sm')
                    ->label('Observacion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('nv_fecha_hora')->size('sm')
                    ->label('Fecha')
                    ->sortable()
                    ->dateTime(),
                ImageColumn::make('imagen_url')
                    ->label('Imagen')
                    ->width(35)
                    ->height(35)
                    ->circular()
                    ->view('tables.columns.imagen-modal'),
            ])
            ->filters([
                Filter::make('Filtro Novedades')
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
                                $query->whereDate('nv_fecha_hora', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date) =>
                                $query->whereDate('nv_fecha_hora', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Action::make('gmap')
                    ->label('Mapa')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->nv_lat},{$record->nv_lng}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary'),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->label('Exportar a Excel'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNovedads::route('/'),
            //'create' => Pages\CreateNovedad::route('/create'),
            //'edit' => Pages\EditNovedad::route('/{record}/edit'),
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
            return $query->whereIn('nv_ins_code', $institucionesCodes);
        }

        // El Lider Operativo ve los locales de su(s) pais(es). Sin paises
        // asignados no ve nada: un lider mal configurado no debe terminar
        // con acceso global.
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('nv_ins_code', $localesDelPais);
        }
        return $query;
    }

}
