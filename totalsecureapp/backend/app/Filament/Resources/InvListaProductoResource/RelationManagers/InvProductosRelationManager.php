<?php

namespace App\Filament\Resources\InvListaProductoResource\RelationManagers;

use Filament\Actions;
use App\helpers;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use App\Services\Inventario\AplicadorDeKit;
use Filament\Actions\CreateAction;
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

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Hidden::make('lia_lista_id')
                ->default(fn ($livewire) => $livewire->ownerRecord->li_id),
            Select::make('lia_producto_id')
                ->label('Producto')
                /*
                 * ⚠️ Esto filtraba por `ipc_ins_code` cuando el catalogo era por
                 * local. **Desde el 2026-09-19 el catalogo es global y esa
                 * columna es nula en todas las filas**, asi que ese filtro
                 * dejaba el selector COMPLETAMENTE VACIO -- sin error, sin
                 * aviso: simplemente no se podia agregar ningun producto a
                 * ninguna lista.
                 *
                 * Ahora se ofrece el catalogo entero, que es justo el sentido de
                 * la fusion: el producto es el mismo en los 133 locales y lo que
                 * cambia es la cantidad de cada lista.
                 */
                ->options(fn () => ProductoCatalogo::query()
                    ->where('ipc_activo', true)
                    ->orderBy('ipc_nombre')
                    ->pluck('ipc_nombre', 'ipc_id'))
                ->searchable()
                ->required()
                ->unique(table: 'inv_lista_item', modifyRuleUsing: function ($rule, $get) {
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

    public function table($table): Table
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
            Actions\EditAction::make()
                ->after($this->marcarApartadaDelKit(...))
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
                ->after($this->marcarApartadaDelKit(...))
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

    /**
     * Tocar los productos de una lista la aparta de su kit.
     *
     * ⚠️ Sin esto, la siguiente vez que alguien pulse «Aplicar a locales» el
     * kit **pisaría este cambio en silencio**: la lista se vería igual a las
     * demás y nada indicaría que ese puesto era distinto a propósito.
     *
     * Se recalcula comparando contenidos, no se confía en levantar una bandera
     * a mano — una bandera que hay que acordarse de poner se olvida.
     */
    private function marcarApartadaDelKit(): void
    {
        app(AplicadorDeKit::class)->refrescarBandera($this->ownerRecord->fresh());
    }
}
