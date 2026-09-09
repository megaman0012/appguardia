<?php

namespace App\Filament\Resources;

use Filament\Actions;
use App\Filament\Tables\FiltroDeEstado;

use App\Support\PerfilPanel;

use Session;
use App\Filament\Resources\InvProductoResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Builder;
use Modules\Administracion\Models\ProductoCatalogo;
use Modules\Administracion\Models\UserHasInstitucion;
use App\Filament\Tables\Descarga;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms;
use Filament\Tables;

/**
 * Productos del inventario.
 *
 * Apunta a `inv_producto_catalogo` (el juego de tablas de FASE1), NO a
 * `inv_productos`. Antes leia el viejo, y la app movil escribe en el nuevo: un
 * producto creado desde el panel no existia para la tablet y al reves. Ver
 * AGENTS.md, seccion Inventario.
 *
 * Cambio de fondo respecto al modelo viejo: **los productos son por local**
 * (`ipc_ins_code`). En `inv_productos` eran globales y no habia nada que acotar;
 * aqui hay que filtrar por el alcance del perfil como el resto del panel, o un
 * supervisor veria el catalogo de locales que no le tocan.
 */
class InvProductoResource extends Resource
{
    protected static ?string $model = ProductoCatalogo::class;

    /**
     * Fijado para que la URL no cambie.
     *
     * Filament deriva la ruta del nombre del modelo: al pasar de InvProducto a
     * ProductoCatalogo, `/admin/inv-productos` se habria convertido en
     * `/admin/producto-catalogos`, rompiendo enlaces y marcadores.
     */
    protected static ?string $slug = 'inv-productos';

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila dispara
     * una consulta por relacion (N+1).
     */
    protected const RELACIONES_TABLA = ['institucion.cliente'];

    protected static string | \UnitEnum | null $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 1;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'producto';
    protected static ?string $pluralModelLabel = 'productos';
    protected static ?string $navigationLabel = 'Productos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('ipc_ins_code')
                ->label('Local')
                ->relationship(
                    'institucion',
                    'ins_descripcion',
                    function ($query) {
                        if (PerfilPanel::alcanceEsPorInstitucion()) {
                            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                                ->where('ui_state', 1)
                                ->pluck('ui_ins_code');
                            if ($institucionesCodes->isEmpty()) {
                                $query->whereRaw('1 = 0');
                                return;
                            }
                            $query->whereIn('ins_code', $institucionesCodes);
                        }
                    }
                )
                ->required()
                ->disabledOn('edit'),
            TextInput::make('ipc_nombre')
                ->label('Nombre')
                ->required()
                // El nombre es unico DENTRO del local, no en todo el sistema:
                // dos locales pueden tener su propio "Extintor 10 lb".
                // ⚠️ En Filament 3 el argumento se llama `modifyRuleUsing`, no
                // `callback`. Con el nombre viejo el formulario revienta al
                // dibujarse con «Unknown named parameter $callback».
                ->unique(table: static::$model, modifyRuleUsing: function ($rule, $get) {
                    return $rule->where('ipc_ins_code', $get('ipc_ins_code'));
                }, ignoreRecord: true),
            TextInput::make('ipc_especificacion')
                ->label('Especificación')
                ->required(),
            Textarea::make('ipc_descripcion')
                ->label('Descripción'),
            Toggle::make('ipc_activo')
                ->label('Activo')
                ->required()
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ipc_id')->size('sm')
                    ->label('ID')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                    ->label('Cliente')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Local')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ipc_nombre')->size('sm')
                    ->label('Producto')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ipc_especificacion')->size('sm')
                    ->label('Especificación')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ipc_descripcion')->size('sm')
                    ->label('Descripción')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ipc_stock_actual')->size('sm')
                    ->label('Stock')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('ipc_created_at')->size('sm')
                    ->label('Creado')
                    ->sortable()
                    ->searchable(),
                BooleanColumn::make('ipc_activo')
                    ->label('Activo')
                    ->toggleable()
                    ->searchable(false),
            ])
            ->filters([
                // Abre mostrando solo los activos. Ver App\Filament\Tables\FiltroDeEstado.
                FiltroDeEstado::make('ipc_activo', true, 'Estado'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Descarga::enLote('inv-producto'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvProductos::route('/'),
            'create' => Pages\CreateInvProducto::route('/create'),
            'edit' => Pages\EditInvProducto::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function shouldRegisterNavigation(): bool {
        return PerfilPanel::puedeConfigurarSistema();
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
        return PerfilPanel::puedeConfigurarSistema();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);

        if (PerfilPanel::alcanceEsPorInstitucion()) {
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('ipc_ins_code', $institucionesCodes);
        }

        // El Lider Operativo ve los locales de su(s) pais(es). Sin paises
        // asignados no ve nada: un lider mal configurado no debe terminar con
        // acceso global.
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('ipc_ins_code', $localesDelPais);
        }

        return $query;
    }
}
