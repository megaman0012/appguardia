<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BitacoraResource\Pages;
use App\Filament\Resources\BitacoraResource\RelationManagers;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\Bitacora;
use Modules\Administracion\Models\UserHasInstitucion;
use Session;

class BitacoraResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reporteria';
    }
    protected static ?string $navigationLabel = 'Bitacora';
    protected static ?int $navigationSort = 11;
    protected static ?string $model = Bitacora::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.cliente', 'users'];
    protected static ?string $navigationIcon = 'heroicon-o-collection';
    protected static bool $shouldRegisterNavigation = false;
    public static function form(Form $form): Form { return $form->schema([ ]); }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bt_id')->size('sm')
                    ->label('Codigo')
                    ->searchable()
                    ->sortable(),
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
                    ->searchable(),
                TextColumn::make('bt_observacion')->size('sm')
                    ->label('Observacion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('bt_fecha_hora')->size('sm')
                    ->label('Fecha')
                    ->sortable()
                    ->dateTime(),
                ImageColumn::make('imagen_url')
                    ->label('Imagen')
                    ->width(35)
                    ->height(35)
                    ->circular(),
            ])
            ->filters([

            ])
            ->actions([
                Action::make('gmap')
                ->label('Mapa')
                ->url(fn($record) => "https://www.google.com/maps?q={$record->bt_lat},{$record->bt_lng}")
                ->openUrlInNewTab()
                ->icon('heroicon-o-map')
                ->color('primary'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBitacoras::route('/'),
            //'create' => Pages\CreateBitacora::route('/create'),
            //'edit' => Pages\EditBitacora::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }



    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
    }
}
