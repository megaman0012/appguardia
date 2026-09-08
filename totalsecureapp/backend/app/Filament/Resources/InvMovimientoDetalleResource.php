<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvMovimientoDetalleResource\Pages;
use App\Support\PerfilPanel;
use Filament\Forms;
use Filament\Resources\Form;
use App\Filament\Tables\Descarga;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Modules\Administracion\Models\MovimientoDetalle;
use Modules\Administracion\Models\UserHasInstitucion;
use Session;

/**
 * Detalle de un movimiento de inventario.
 *
 * Apunta a `inv_movimiento_detalle` (el juego de FASE1), NO a
 * `inv_movimiento_detalles`. Ver AGENTS.md, seccion Inventario.
 *
 * Cambio de forma respecto al modelo viejo: en `inv_movimiento_detalles` una
 * fila llevaba las cantidades de TODAS las etapas (`md_cant_asign`,
 * `md_cant_recep`, `md_cant_devol`, `md_cant_final`). Aqui la fila pertenece a un
 * evento, asi que hay dos cantidades: la esperada (`md_cantidad_default`, que
 * viene de la lista) y la contada (`md_cantidad_real`). Y `md_estado` dejo de ser
 * un booleano: es `ok` / `falta` / `danado`.
 */
class InvMovimientoDetalleResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Inventario';
    }

    protected static ?string $model = MovimientoDetalle::class;

    /**
     * Fijado para que la URL no cambie: Filament deriva la ruta del modelo, y al
     * pasar de InvMovimientoDetalle a MovimientoDetalle
     * `/admin/inv-movimiento-detalles` se habria convertido en
     * `/admin/movimiento-detalles`, rompiendo el enlace "Detalles" de la pantalla
     * de movimientos.
     */
    protected static ?string $slug = 'inv-movimiento-detalles';

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1).
     */
    protected const RELACIONES_TABLA = ['producto'];

    protected static ?int $navigationSort = 4;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'detalle';
    protected static ?string $pluralModelLabel = 'detalles';
    protected static ?string $navigationLabel = 'Detalle de movimiento';
    protected static ?string $navigationIcon = 'heroicon-o-collection';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form { return $form->schema([]); }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('md_id')->size('sm')
                    ->label('Código')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('producto.ipc_nombre')->size('sm')
                    ->label('Producto')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('producto.ipc_descripcion')->size('sm')
                    ->label('Descripción')
                    ->searchable()
                    ->toggleable()
                    ->limit(20)
                    // Con optional(): un detalle cuyo producto se dio de baja
                    // dejaba la pantalla en 500 al construir el tooltip.
                    ->tooltip(fn ($record) => optional($record->producto)->ipc_descripcion),
                TextColumn::make('producto.ipc_especificacion')->size('sm')
                    ->label('Especificación')
                    ->searchable()
                    ->toggleable(),
                BooleanColumn::make('md_recibido')
                    ->label('Recibido')
                    ->searchable(false),
                TextColumn::make('md_cantidad_default')->size('sm')
                    ->label('Cantidad Default')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('md_cantidad_real')->size('sm')
                    ->label('Cantidad Contada')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('md_observacion')->size('sm')
                    ->label('Observación')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->md_observacion),
                BadgeColumn::make('md_estado')->size('sm')
                    ->label('Estado')
                    ->enum([
                        MovimientoDetalle::ESTADO_OK     => 'OK',
                        MovimientoDetalle::ESTADO_FALTA  => 'Falta',
                        MovimientoDetalle::ESTADO_DANADO => 'Dañado',
                    ])
                    ->colors([
                        'success' => MovimientoDetalle::ESTADO_OK,
                        'warning' => MovimientoDetalle::ESTADO_FALTA,
                        'danger'  => MovimientoDetalle::ESTADO_DANADO,
                    ])
                    ->sortable()
                    ->searchable(false),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([
                Descarga::enLote('inv-movimiento-detalle'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvMovimientoDetalles::route('/'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    /**
     * Bloquea la RUTA, no solo el menu.
     *
     * `$shouldRegisterNavigation = false` la saca del menu lateral, pero la ruta
     * seguia abierta y esta pantalla **no tenia canViewAny()**: cualquiera que
     * pudiera entrar al panel la abria escribiendo la URL.
     */
    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    public static function getEloquentQuery(): Builder
    {
        $movId = request()->query('mov');

        $query = parent::getEloquentQuery()
            ->with(self::RELACIONES_TABLA)
            ->where('md_movimiento_id', $movId);

        // El alcance se aplica SUBIENDO al movimiento, porque el detalle no
        // guarda el local.
        //
        // Antes esta consulta solo filtraba por el `?mov=` de la URL, sin acotar
        // nada mas: un supervisor de un local podia leer el inventario de otro
        // cambiando el numero a mano. El id es un entero consecutivo, asi que no
        // habia nada que adivinar.
        if (PerfilPanel::alcanceEsPorInstitucion()) {
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');

            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('movimiento', function (Builder $q) use ($institucionesCodes) {
                $q->whereIn('mc_ins_code', $institucionesCodes);
            });
        }

        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            if (empty($localesDelPais)) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereHas('movimiento', function (Builder $q) use ($localesDelPais) {
                $q->whereIn('mc_ins_code', $localesDelPais);
            });
        }

        return $query;
    }
}
