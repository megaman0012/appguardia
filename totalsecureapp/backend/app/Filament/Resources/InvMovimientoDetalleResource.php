<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvMovimientoDetalleResource\Pages;
use App\Filament\Resources\InvMovimientoDetalleResource\RelationManagers;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\InvMovimientoDetalle;

class InvMovimientoDetalleResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reporteria';
    }
    protected static ?string $model = InvMovimientoDetalle::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['producto'];
    protected static ?int $navigationSort = 14;
    protected static ?string $navigationLabel = 'Movimiento Detalle';
    protected static ?string $navigationIcon = 'heroicon-o-collection';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form { return $form->schema([]); }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('md_id')->size('sm')
                    ->label('Codigo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('producto.pr_nombre')->size('sm')
                    ->label('Producto')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('producto.pr_descripcion')->size('sm')
                    ->label('Decripcion')
                    ->searchable()
                    ->toggleable()
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->producto->pr_descripcion),
                TextColumn::make('producto.pr_especificacion')->size('sm')
                    ->label('Especificacion')
                    ->searchable()
                    ->toggleable(),

                BooleanColumn::make('md_exist')
                    ->label('Recibido')
                    ->searchable(false),

                TextColumn::make('md_cant_asign')->size('sm')
                    ->label('Cantidad Default')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('md_cant_recep')->size('sm')
                    ->label('Cantidad Recepcion')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('md_recep_obsv')->size('sm')
                    ->label('Observacion')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->md_recep_obsv),
                /*TextColumn::make('md_cant_devol')->size('sm')
                    ->label('Cant. Devolucion')
                    ->searchable()
                    ->toggleable(),*/
                /*TextColumn::make('md_cant_final')->size('sm')
                    ->label('Cant. Finalizado')
                    ->searchable()
                    ->toggleable(),*/
                BooleanColumn::make('md_estado')
                    ->label('Estado')
                    ->sortable()
                    ->searchable(false),

            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array { return [ ]; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvMovimientoDetalles::route('/'),
            //'create' => Pages\CreateInvMovimientoDetalle::route('/create'),
            //'edit' => Pages\EditInvMovimientoDetalle::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder {
        $mov_id = request()->query('mov');
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA)
            ->where('md_mov_id', $mov_id );
    }
}
