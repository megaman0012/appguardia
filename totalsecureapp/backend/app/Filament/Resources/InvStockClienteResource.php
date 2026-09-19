<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvStockClienteResource\Pages;
use App\Filament\Tables\Descarga;
use App\Filament\Tables\FiltroDeEstado;
use App\Services\Inventario\ResumenDeInventario;
use App\Support\PerfilPanel;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Administracion\Models\Organizacion;
use Modules\Administracion\Models\ProductoCatalogo;
use Modules\Administracion\Models\StockCliente;

/**
 * Cuantos equipos de cada producto tiene asignados cada cliente.
 *
 * Es el nivel que faltaba entre el catalogo global y las listas de cada local:
 * el catalogo dice **que** existe, esto **cuanto** le toca al cliente, y la
 * lista de cada local **donde** esta.
 *
 * La columna «Repartido» no sale de esta tabla: la calcula
 * `ResumenDeInventario` sumando las listas de los locales de ese cliente. Se
 * pone aca al lado de lo asignado porque la comparacion es justo el motivo de
 * que esta pantalla exista: **si se repartio mas de lo que hay, que se vea al
 * escribirlo**, no cuando alguien vaya a buscar la camara y no este.
 *
 * ⚠️ Cantidad **declarada**, no contador. No se mueve con recepciones ni
 * devoluciones.
 */
class InvStockClienteResource extends Resource
{
    protected static ?string $model = StockCliente::class;

    protected static ?string $slug = 'inv-stock-cliente';

    protected static string | \UnitEnum | null $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'asignación';
    protected static ?string $pluralModelLabel = 'asignaciones';
    protected static ?string $navigationLabel = 'Stock por cliente';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected const RELACIONES_TABLA = ['cliente', 'producto'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('isc_org_code')
                ->label('Cliente')
                ->options(fn () => Organizacion::query()
                    ->orderBy('org_descripcion')
                    ->pluck('org_descripcion', 'org_code'))
                ->searchable()
                ->required()
                // No se cambia en edición: mover una asignación de cliente es
                // borrarla y crearla en el otro, no editarla.
                ->disabledOn('edit'),

            Select::make('isc_producto_id')
                ->label('Producto')
                ->options(fn () => ProductoCatalogo::query()
                    ->where('ipc_activo', true)
                    ->orderBy('ipc_nombre')
                    ->pluck('ipc_nombre', 'ipc_id'))
                ->searchable()
                ->required()
                ->disabledOn('edit')
                // El único de la base es (cliente, producto). Sin esto el
                // choque llega como error 500 de PostgreSQL en vez de como un
                // mensaje en el campo.
                ->unique(
                    table: 'inv_stock_cliente',
                    column: 'isc_producto_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule, $get) => $rule->where('isc_org_code', $get('isc_org_code')),
                ),

            TextInput::make('isc_cantidad')
                ->label('Cantidad asignada')
                ->numeric()
                ->minValue(0)
                ->required()
                ->helperText('Lo que la empresa entregó a este cliente. '
                    . 'El reparto por local se hace en las listas de cada uno.'),

            Textarea::make('isc_observacion')
                ->label('Observación')
                ->rows(2),

            Toggle::make('isc_activo')
                ->label('Activo')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        // Una sola consulta para todas las filas: pedirlo por fila serían 21×4
        // consultas para dibujar una página.
        $resumen = app(ResumenDeInventario::class)
            ->porClienteYProducto(PerfilPanel::localesVisibles())
            ->keyBy(fn ($r) => $r->org_code . ':' . $r->producto_id);

        return $table
            ->columns([
                TextColumn::make('cliente.org_descripcion')->size('sm')
                    ->label('Cliente')->searchable()->sortable(),

                TextColumn::make('producto.ipc_nombre')->size('sm')
                    ->label('Producto')->searchable()->sortable(),

                TextColumn::make('isc_cantidad')->size('sm')
                    ->label('Asignado')->numeric(decimalPlaces: 0)->sortable(),

                TextColumn::make('repartido')
                    ->label('Repartido')->size('sm')
                    ->state(fn (StockCliente $r) => (float) (
                        $resumen[$r->isc_org_code . ':' . $r->isc_producto_id]->distribuido ?? 0
                    ))
                    ->numeric(decimalPlaces: 0),

                TextColumn::make('diferencia')
                    ->label('Diferencia')->size('sm')
                    ->state(fn (StockCliente $r) => (float) (
                        $resumen[$r->isc_org_code . ':' . $r->isc_producto_id]->diferencia
                            ?? (float) $r->isc_cantidad
                    ))
                    ->numeric(decimalPlaces: 0)
                    // Rojo = se repartió más de lo asignado. Es lo único de esta
                    // pantalla que exige una acción.
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'gray' : 'success'))
                    ->tooltip(fn ($state) => $state < 0
                        ? 'Se repartió más de lo asignado a este cliente'
                        : null),

                TextColumn::make('isc_observacion')->size('sm')
                    ->label('Observación')->toggleable(isToggledHiddenByDefault: true)
                    ->wrap()->limit(60),
            ])
            ->filters([
                FiltroDeEstado::make('isc_activo', true, 'Estado'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Descarga::enLote('inv-stock-cliente'),
            ])
            ->defaultSort('isc_org_code');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);

        /*
         * El alcance viene en locales y esta tabla es por cliente, asi que hay
         * que traducir. `null` = ve todo, `[]` = **no ve nada**: confundirlos
         * convierte a un supervisor sin locales vinculados en acceso global.
         */
        $locales = PerfilPanel::localesVisibles();

        if ($locales === null) {
            return $query;
        }

        if (empty($locales)) {
            return $query->whereRaw('1 = 0');
        }

        $clientes = \Illuminate\Support\Facades\DB::table('organizacion_institucion')
            ->whereIn('ins_code', $locales)
            ->whereNotNull('ins_cliente_id')
            ->distinct()->pluck('ins_cliente_id');

        return $clientes->isEmpty()
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('isc_org_code', $clientes);
    }

    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    /** Asignar equipo a un cliente es configuración, no operación diaria. */
    public static function canCreate(): bool
    {
        return PerfilPanel::puedeConfigurarSistema();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInvStockCliente::route('/'),
            'create' => Pages\CreateInvStockCliente::route('/create'),
            'edit'   => Pages\EditInvStockCliente::route('/{record}/edit'),
        ];
    }
}
