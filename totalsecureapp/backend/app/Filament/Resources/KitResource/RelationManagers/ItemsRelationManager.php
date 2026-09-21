<?php

namespace App\Filament\Resources\KitResource\RelationManagers;

use App\Services\Inventario\AplicadorDeKit;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Administracion\Models\ProductoCatalogo;

/**
 * Los productos del kit, con su cantidad.
 *
 * ⚠️ Apunta a `items` y **no a `productos`**, y la diferencia importa: `items`
 * es el hasMany al pivote --que lleva la cantidad-- y `productos` devuelve
 * modelos de producto, sin ella. Apuntar un RelationManager a `productos` deja
 * la columna de cantidad vacía; ya pasó en este proyecto con `Lista`.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Productos del kit';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'productos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kii_producto_id')
                ->label('Producto')
                // El catálogo es global desde el 2026-09-19: se ofrece entero.
                ->options(fn () => ProductoCatalogo::query()
                    ->where('ipc_activo', true)
                    ->orderBy('ipc_nombre')
                    ->pluck('ipc_nombre', 'ipc_id'))
                ->searchable()
                ->required()
                // El único de la base es (kit, producto). Sin esto el choque
                // llega como error 500 en vez de como mensaje en el campo.
                ->unique(
                    table: 'inv_kit_item',
                    column: 'kii_producto_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule) => $rule->where('kii_ki_id', $this->ownerRecord->ki_id),
                ),

            TextInput::make('kii_cantidad')
                ->label('Cantidad por puesto')
                ->numeric()
                ->minValue(0.01)
                ->default(1)
                ->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('kii_producto_id')
            ->columns([
                TextColumn::make('producto.ipc_nombre')->label('Producto')->size('sm')->searchable(),
                TextColumn::make('kii_cantidad')->label('Cantidad')->size('sm')->numeric(decimalPlaces: 0),
            ])
            ->headerActions([
                Actions\CreateAction::make()->label('Agregar producto')->after($this->avisarDeSincronizar(...)),
            ])
            ->actions([
                Actions\EditAction::make()->after($this->avisarDeSincronizar(...)),
                Actions\DeleteAction::make()->after($this->avisarDeSincronizar(...)),
            ])
            ->emptyStateHeading('El kit no tiene productos todavía');
    }

    /**
     * Cambiar el kit **no** toca las listas por sí solo, y hay que decirlo.
     *
     * ⚠️ Propagar en automático sobre 130 puestos desde un formulario de
     * edición es demasiado silencioso: quien corrige una cantidad no espera
     * reescribir el inventario de toda la operación en ese mismo clic. La
     * propagación es un acto aparte --«Aplicar a locales»--, y este aviso es lo
     * que evita que alguien se quede creyendo que ya se aplicó.
     */
    private function avisarDeSincronizar(): void
    {
        $pendientes = $this->ownerRecord->listas()->where('li_modificada', false)->count();

        if ($pendientes === 0) {
            return;
        }

        Notification::make()
            ->title('El kit cambió')
            ->body("Hay {$pendientes} puesto(s) siguiendo este kit. Use «Aplicar a locales» "
                . 'para ponerlos al día: el cambio no se propaga solo.')
            ->warning()
            ->persistent()
            ->send();
    }
}
