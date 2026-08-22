<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccesoResource\Pages;
use App\Filament\Resources\AccesoResource\RelationManagers;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Modules\Administracion\Models\Acceso;

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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\UserHasInstitucion;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Session;
class AccesoResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reporteria';
    }
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationLabel = 'Acceso';
    protected static ?string $model = Acceso::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['accesoPersona', 'institucion.organizacionSede.organizacion', 'institucion.organizacionSede.sede'];
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    public static function form(Form $form): Form{ return $form->schema([]); }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ac_code')->size('sm')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('institucion.organizacionSede.sede.ps_descripcion')->size('sm')
                    ->label('Sede')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.organizacionSede.organizacion.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Institucion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('accesoPersona.ap_documento')->size('sm')
                    ->label('Documento')
                    ->searchable(),
                TextColumn::make('accesoPersona.ap_tip_doc')->size('sm')
                    ->label('Tipo')
                    ->searchable(),
                TextColumn::make('accesoPersona.ap_nombres')->size('sm')
                    ->label('Nombres')
                    ->searchable(),
                TextColumn::make('accesoPersona.ap_apellidos')->size('sm')
                    ->label('Apellidos')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ac_temperatura')->size('sm')
                    ->label('Temperatura')
                    ->toggleable(),
                IconColumn::make('ac_bicicleta')->size('sm')
                    ->label('Bicicleta')
                    ->boolean(),
                TextColumn::make('ac_patente')->size('sm')
                    ->label('Patente')
                    ->toggleable()
                    ->searchable(),
                BooleanColumn::make('ac_is_sello')->size('sm')
                    ->label('Sello')
                    ->toggleable(),
                BooleanColumn::make('ac_is_neumatico')->size('sm')
                    ->label('Neumaticos')
                    ->toggleable(),
                BooleanColumn::make('ac_is_carro')->size('sm')
                    ->label('Carro')
                    ->toggleable(),
                BooleanColumn::make('ac_pta_llave')->size('sm')
                    ->label('P.Carga Llave')
                    ->toggleable(),
                TextColumn::make('ac_kms')->size('sm')
                    ->label('Kms')
                    ->toggleable(),
                ImageColumn::make('imagen_url')
                    ->label('Imagen')
                    ->width(35)
                    ->height(35)
                    ->circular()
                    ->view('tables.columns.imagen-modal'),
                TextColumn::make('ac_created_at')->size('sm')
                    ->label('Fecha Entrada')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ac_is_salida_fecha')->size('sm')
                    ->label('Fecha Salida')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ac_observaciones')->size('sm')
                    ->label('Observaciones')
                    ->sortable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->ac_observaciones),
                /*ToggleColumn::make('ac_estado')
                    ->label('Estado')
                    ->sortable(),*/
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
                                $query->whereDate('ac_created_at', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date) =>
                                $query->whereDate('ac_created_at', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('gmaping')
                    ->label('Ingreso')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->ac_lat},{$record->ac_lng}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary'),
                Tables\Actions\Action::make('gmapegr')
                    ->label('Salida')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->ac_lat_sal},{$record->ac_lng_sal}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary')
                    ->visible(fn ($record) => filled($record->ac_lat_sal)),
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
            'index' => Pages\ListAccesos::route('/'),
            //'create' => Pages\CreateAcceso::route('/create'),
            //'edit' => Pages\EditAcceso::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
        if(in_array( Session::get('usuPF'), ['Supervisor'] )){
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('ac_ins_code', $institucionesCodes);
        }
        return $query;
    }

}
