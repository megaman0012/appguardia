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
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Modules\Administracion\Models\ProductoCatalogo;

/**
 * Productos de una lista de inventario.
 *
 * Movido al juego de tablas de FASE1 (`inv_lista_item`), que es donde escribe la
 * app movil. Ver AGENTS.md, seccion Inventario.
 *
 * **La relacion se llama `items`, no `productos`.** En el modelo viejo
 * `productos()` era un hasMany a la tabla pivote, con el nombre equivocado. En
 * `Lista` los dos existen y significan cosas distintas: `items()` son las filas
 * del pivote (que es lo que se edita aqui) y `productos()` es un belongsToMany a
 * los productos en si. Apuntar a `productos` aqui daria modelos de producto y
 * las columnas de cantidad quedarian vacias.
 */
class InvProductosRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'Listas';

    public static function form($form): Form
    {
        return $form->schema([
            Hidden::make('lia_lista_id')
                ->default(fn ($livewire) => $livewire->ownerRecord->li_id),
            Select::make('lia_producto_id')
                ->label('Producto')
                // Los productos ahora son POR LOCAL (`ipc_ins_code`). Sin este
                // filtro se ofreceria el catalogo de otros locales y se armarian
                // listas con productos que ese local no tiene.
                ->options(function ($livewire) {
                    return ProductoCatalogo::query()
                        ->where('ipc_ins_code', $livewire->ownerRecord->li_ins_code)
                        ->where('ipc_activo', true)
                        ->orderBy('ipc_nombre')
                        ->pluck('ipc_nombre', 'ipc_id');
                })
                ->searchable()
                ->required()
                ->unique(table: 'inv_lista_item', callback: function ($rule, $get) {
                    return $rule->where('lia_lista_id', $get('lia_lista_id'));
                }, ignoreRecord: true),
            TextInput::make('lia_cantidad_default')
                ->label('Cantidad x Defecto')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required(),
            Toggle::make('lia_activo')
                ->label('Activo')
                ->default(true)
                ->required(),
        ]);
    }

    public static function table($table): Table
    {
        return $table->columns([
            TextColumn::make('producto.ipc_id')->size('sm')
                ->label('ID')
                ->sortable()
                ->toggleable()
                ->searchable(),
            TextColumn::make('producto.ipc_nombre')->size('sm')
                ->label('Producto')
                ->sortable()
                ->searchable(),
            TextColumn::make('producto.ipc_descripcion')->size('sm')
                ->label('Descripción')
                ->sortable()
                ->searchable(),
            TextColumn::make('producto.ipc_especificacion')->size('sm')
                ->label('Especificación')
                ->sortable()
                ->searchable(),
            TextColumn::make('lia_cantidad_default')->size('sm')
                ->label('Cantidad x Defecto')
                ->searchable(),
            BooleanColumn::make('lia_activo')
                ->label('Activo')
                ->toggleable(),
        ])
        ->actions([
            Tables\Actions\EditAction::make()
            ->mutateFormDataUsing(function (array $data): array {
                // Antes decia 'im_updated_user', que no es columna de esta tabla:
                // la auditoria de quien editaba se perdia en silencio.
                $data['lia_updated_user'] = auth()->id();
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
                $data['lia_created_user'] = auth()->id();
                $data['lia_updated_user'] = auth()->id();
                return $data;
            })
            ->after(function (\Illuminate\Database\Eloquent\Model $record) {
                helpers::control_log_filament($record->toArray(), 'InvProductosListasRelationManager', 'Create','NOTICE', 'Crear Lista Productos RelationManager');
            }),
        ])
        ->bulkActions([]);
    }
}
