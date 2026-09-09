<?php

namespace App\Filament\Resources;

use App\Filament\Tables\Etiqueta;
use App\Support\PerfilPanel;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Modules\Administracion\Models\UserHasInstitucion;
use App\Filament\Tables\Descarga;
use Session;
use App\Filament\Resources\InvMovimientoResource\Pages;
use Modules\Administracion\Models\MovimientoCabecera;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

/**
 * Movimientos de inventario.
 *
 * Apunta a `inv_movimiento_cabecera` (el juego de FASE1), NO a
 * `inv_movimientos`. Es donde escribe la app movil: antes el guardia hacia el
 * inventario en la tablet y esta pantalla no mostraba nada, porque leia la tabla
 * vieja. Ver AGENTS.md, seccion Inventario.
 *
 * **No fue un cambio de nombres, fue un cambio de forma.** En `inv_movimientos`
 * una fila era el ciclo COMPLETO, con cuatro juegos de columnas
 * (`mov_recep_asig_*`, `mov_recep_*`, `mov_devol_*`, `mov_devol_entreg_*`). En
 * el modelo nuevo **cada fila es UN evento**, y su tipo esta en `mc_tipo`
 * (recepcion / devolucion / baja). Por eso ya no hay columnas "fecha de
 * recepcion" y "fecha de devolucion" en la misma fila: hay una `mc_fecha` y el
 * tipo dice de que es.
 *
 * De paso desaparece el filtro de toggles que mostraba y ocultaba los "campos de
 * recepcion" y los "campos de devolucion": con una fila por evento lo natural es
 * filtrar por tipo, no esconder columnas vacias.
 */
class InvMovimientoResource extends Resource
{
    protected static ?string $model = MovimientoCabecera::class;

    /**
     * Fijado para que la URL no cambie: Filament deriva la ruta del modelo, y al
     * pasar de InvMovimiento a MovimientoCabecera `/admin/inv-movimientos` se
     * habria convertido en `/admin/movimiento-cabeceras`.
     */
    protected static ?string $slug = 'inv-movimientos';

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila dispara
     * una consulta por relacion (N+1).
     */
    protected const RELACIONES_TABLA = ['institucion.cliente', 'lista', 'usuario'];

    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 3;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'movimiento';
    protected static ?string $pluralModelLabel = 'movimientos';
    protected static ?string $navigationLabel = 'Movimientos';
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function form(Form $form): Form {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mc_id')->size('sm')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                BadgeColumn::make('mc_tipo')->size('sm')
                    ->label('Tipo')
                    ->formatStateUsing(Etiqueta::de([
                        MovimientoCabecera::TIPO_RECEPCION  => 'Recepción',
                        MovimientoCabecera::TIPO_DEVOLUCION => 'Devolución',
                        MovimientoCabecera::TIPO_BAJA       => 'Baja',
                    ]))
                    ->colors([
                        'warning'   => MovimientoCabecera::TIPO_RECEPCION,
                        'success'   => MovimientoCabecera::TIPO_DEVOLUCION,
                        'danger'    => MovimientoCabecera::TIPO_BAJA,
                    ])
                    ->toggleable(),
                TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                    ->label('Cliente')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Local')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('lista.li_nombre')->size('sm')
                    ->label('Lista')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_nmbcom')->size('sm')
                    ->label('Registrado por')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('mc_fecha')->size('sm')
                    ->label('Fecha')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('mc_observaciones')->size('sm')
                    ->label('Observaciones')
                    ->toggleable()
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->mc_observaciones),
                BadgeColumn::make('mc_estado')->size('sm')
                    ->label('Estado')
                    ->formatStateUsing(Etiqueta::de([
                        MovimientoCabecera::ESTADO_PENDIENTE  => 'Pendiente',
                        MovimientoCabecera::ESTADO_COMPLETADO => 'Completado',
                        MovimientoCabecera::ESTADO_CANCELADO  => 'Cancelado',
                    ]))
                    ->colors([
                        'warning'   => MovimientoCabecera::ESTADO_PENDIENTE,
                        'success'   => MovimientoCabecera::ESTADO_COMPLETADO,
                        'danger'    => MovimientoCabecera::ESTADO_CANCELADO,
                    ])
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('mc_tipo')
                    ->label('Tipo')
                    ->options([
                        MovimientoCabecera::TIPO_RECEPCION  => 'Recepción',
                        MovimientoCabecera::TIPO_DEVOLUCION => 'Devolución',
                        MovimientoCabecera::TIPO_BAJA       => 'Baja',
                    ]),
                SelectFilter::make('mc_estado')
                    ->label('Estado')
                    ->options([
                        MovimientoCabecera::ESTADO_PENDIENTE  => 'Pendiente',
                        MovimientoCabecera::ESTADO_COMPLETADO => 'Completado',
                        MovimientoCabecera::ESTADO_CANCELADO  => 'Cancelado',
                    ]),
                // El filtro anterior consultaba `mov_fecha_recepcion`, que NO
                // existe como columna: usarlo reventaba con un error de SQL.
                // Aqui va contra `mc_fecha`, que es la fecha del evento.
                Filter::make('mc_fecha')
                    ->label('Rango de fecha')
                    ->form([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date) =>
                                $query->whereDate('mc_fecha', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date) =>
                                $query->whereDate('mc_fecha', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Action::make('verDetalle')->label('Detalles')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn (MovimientoCabecera $record) =>
                        InvMovimientoDetalleResource::getUrl(
                            'index', ['mov' => $record->mc_id]
                        )
                    ),
                // El movimiento se registra en el puesto y guarda su GPS, igual
                // que un marcaje. Poder abrirlo en el mapa es lo que permite
                // revisar un inventario que se hizo desde donde no debia.
                Action::make('gmap')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('primary')
                    ->url(fn ($record) => "https://www.google.com/maps?q={$record->mc_lat},{$record->mc_lng}")
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => filled($record->mc_lat) && filled($record->mc_lng)),
            ])
            ->bulkActions([
                Descarga::enLote('inv-movimiento'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvMovimientos::route('/'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function shouldRegisterNavigation(): bool {
        return PerfilPanel::puedeOperar();
    }

    /**
     * Bloquea la RUTA, no solo el menu.
     *
     * shouldRegisterNavigation() solo oculta el item del menu lateral: quien
     * escribiera la URL a mano entraba igual. Filament aborta con 403 cuando
     * canViewAny() es false (Pages\Page::authorizeResourceAccess).
     */
    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    public static function getEloquentQuery(): Builder {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);

        if (PerfilPanel::alcanceEsPorInstitucion()) {
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('mc_ins_code', $institucionesCodes);
        }

        // El Lider Operativo ve los locales de su(s) pais(es). Sin paises
        // asignados no ve nada: un lider mal configurado no debe terminar
        // con acceso global.
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('mc_ins_code', $localesDelPais);
        }

        return $query;
    }
}
