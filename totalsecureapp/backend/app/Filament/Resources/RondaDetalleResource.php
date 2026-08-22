<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RondaDetalleResource\Pages;
use App\Filament\Resources\RondaDetalleResource\RelationManagers;
use Modules\Administracion\Models\ronda_detalle;

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
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RondaDetalleResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reporteria';
    }
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Ronda Detalle';
    protected static ?string $model = ronda_detalle::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['rondaCabecera.institucion.organizacionSede.organizacion', 'rondaCabecera.institucion.organizacionSede.sede', 'users'];
    protected static ?string $navigationIcon = 'heroicon-o-collection';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form{ return $form->schema([]); }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rondaCabecera.institucion.organizacionSede.sede.ps_descripcion')->size('sm')
                    ->label('Sede')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('rondaCabecera.institucion.organizacionSede.organizacion.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('rondaCabecera.institucion.ins_descripcion')->size('sm')
                    ->label('Institucion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('users.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('rd_fecha_hora')->size('sm')
                    ->label('Fecha')
                    ->sortable()
                    ->toggleable()
                    ->dateTime(),

                ImageColumn::make('imagen_url')
                    ->label('Imagen')
                    ->width(35)
                    ->height(35)
                    ->circular()
                    ->view('tables.columns.imagen-modal'),

                TextColumn::make('rd_observacion')->size('sm')
                    ->label('Observacion')
                    ->toggleable()
                    ->searchable(),

                /*ToggleColumn::make('rd_estado')
                    ->label('Estado')
                    ->searchable(false)
                    ->toggleable(),*/
            ])
            ->filters([])
            ->actions([
                Action::make('gmap')
                    ->label('Ubicacion')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->rd_lat},{$record->rd_lng}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary')
                    ->visible(fn($record) => is_null($record->rd_im_code)),
                Action::make('marcador_map')
                    ->label('Marcador')
                    ->url(fn($record) => $record->marcador
                        ? "https://www.google.com/maps/dir/{$record->rd_lat},{$record->rd_lng}/{$record->marcador->im_lat},{$record->marcador->im_lng}"
                        : '#'
                    )
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-flag')
                    ->color('success')
                    ->visible(fn($record) => !is_null($record->rd_im_code))
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListRondaDetalles::route('/'),
            //'create' => Pages\CreateRondaDetalle::route('/create'),
            //'edit' => Pages\EditRondaDetalle::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder {
        $ronda_id = request()->query('ronda');
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA)
        ->where('rd_rc_id', $ronda_id );
    }

}
