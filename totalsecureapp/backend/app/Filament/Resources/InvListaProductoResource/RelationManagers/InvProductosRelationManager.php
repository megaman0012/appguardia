<?php

namespace App\Filament\Resources\InvListaProductoResource\RelationManagers;

use App\helpers;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\Concerns\CanCreate;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\InvProducto;

class InvProductosRelationManager extends RelationManager
{
    protected static string $relationship = 'productos';

    protected static ?string $recordTitleAttribute = 'Listas';

    public static function form($form): Form
    {
        return $form->schema([
            Hidden::make('lpi_lp_id')
                ->default(fn ($livewire) => $livewire->ownerRecord->lp_id),
            /*Hidden::make('lpi_lp_id')
                ->default(fn ($livewire) => $livewire->ownerRecord->lp_id),
            Hidden::make('lpi_pr_id')
                ->default(fn ($livewire) => $livewire->ownerRecord->pr_id),*/
            /*Select::make('lpi_lp_id')
                ->label('Listas')
                ->relationship('lista', 'lp_nombre')
                ->searchable()
                ->required(),*/
            Select::make('lpi_pr_id')
                ->relationship('producto', 'pr_nombre')
                ->required()
                ->unique(table: 'inv_lista_producto_items', callback: function ($rule, $get) {
                    return $rule->where('lpi_lp_id', $get('lpi_lp_id'));
                }, ignoreRecord: true),
            TextInput::make('lpi_cantidad')
                ->label('Cantidad x Defecto')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required(),
            Toggle::make('lpi_estado')
                ->label('Estado')
                ->default(true)
                ->required(),
        ]);
    }

    public static function table($table): Table
    {
        return $table->columns([
            TextColumn::make('producto.pr_id')->size('sm')
                ->label('ID')
                ->sortable()
                ->toggleable()
                ->searchable(),
            TextColumn::make('producto.pr_nombre')->size('sm')
                ->label('Producto')
                ->sortable()
                ->searchable(),
            TextColumn::make('producto.pr_descripcion')->size('sm')
                ->label('Descipcion')
                ->sortable()
                ->searchable(),
            TextColumn::make('producto.pr_especificacion')->size('sm')
                ->label('Especificacion')
                ->sortable()
                ->searchable(),
            TextColumn::make('lpi_cantidad')->size('sm')
                ->label('Cantidad x Defecto')
                ->searchable(),
            BooleanColumn::make('lpi_estado')
                ->label('Activo')
                ->toggleable(),
        ])
        ->actions([
            Tables\Actions\EditAction::make()
            ->mutateFormDataUsing(function (array $data): array {
                $data['im_updated_user'] = auth()->id();
                return $data;
            })
            ->after(function (\Illuminate\Database\Eloquent\Model $record) {
                helpers::control_log_filament($record->toArray(), 'InvProductosListasRelationManager', 'Edit','NOTICE', 'Editar Lista Productos RelationManager');
            }),
        ])
        ->headerActions([
            CreateAction::make()
            ->label('Agregar Producto')
            ->mutateFormDataUsing(function (array $data): array {
                $data['lpi_created_user'] = auth()->id();
                $data['lpi_updated_user'] = auth()->id();
                return $data;
            })
            ->after(function (\Illuminate\Database\Eloquent\Model $record) {
                helpers::control_log_filament($record->toArray(), 'InvProductosListasRelationManager', 'Create','NOTICE', 'Crear Lista Productos RelationManager');
            }),
        ])
        ->bulkActions([]);
    }
}

