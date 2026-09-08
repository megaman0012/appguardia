<?php

namespace App\Filament\Resources;

use App\Support\PerfilPanel;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Modules\Administracion\Models\UserHasInstitucion;
use Session;
use App\Filament\Resources\InvListaProductoResource\Pages;
use App\Filament\Resources\InvListaProductoResource\RelationManagers\ProductosRelationManager;
use Modules\Administracion\Models\Lista;
use Filament\Resources\Resource;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms;

/**
 * Listas de inventario.
 *
 * Apunta a `inv_lista` (el juego de FASE1), NO a `inv_listas_productos`. Antes
 * leia el viejo y la app movil escribe en el nuevo, asi que una lista creada
 * desde el panel no existia para la tablet. Ver AGENTS.md, seccion Inventario.
 */
class InvListaProductoResource extends Resource
{
    protected static ?string $model = Lista::class;

    /**
     * Fijado para que la URL no cambie: Filament deriva la ruta del modelo, y
     * al pasar de InvListaProducto a Lista `/admin/inv-lista-productos` se
     * habria convertido en `/admin/listas`.
     */
    protected static ?string $slug = 'inv-lista-productos';

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.cliente'];
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 2;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'lista';
    protected static ?string $pluralModelLabel = 'listas';
    protected static ?string $navigationLabel = 'Listas';
    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('li_ins_code')
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
                //->searchable()
                ->required()
                ->disabledOn('edit'),
            TextInput::make('li_nombre')
                ->label('Nombre')
                ->required()
                ->unique(table: static::$model, callback: function ($rule, $get) {
                    return $rule->where('li_ins_code', $get('li_ins_code'));
                }, ignoreRecord: true),
            Textarea::make('li_descripcion')
                ->label('Descripción'),
            Toggle::make('li_activo')
                ->label('Activa')
                ->required()
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('li_id')->size('sm')
                ->label('Código')
                ->sortable()
                ->searchable(),
            TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                ->label('Cliente')
                ->searchable()
                ->toggleable(),
            TextColumn::make('institucion.ins_descripcion')->size('sm')
                ->label('Local')
                ->searchable()
                ->toggleable(),
            TextColumn::make('li_nombre')->size('sm')
                ->label('Nombre')
                ->searchable(),
            TextColumn::make('li_descripcion')->size('sm')
                ->label('Descripción')
                ->searchable(),
            // Cuenta 'items' y no 'productos': en Lista los dos existen y
            // 'productos' es un belongsToMany que incluiria los items inactivos,
            // asi que el numero no coincidiria con las filas que se listan abajo.
            TextColumn::make('items_count')
                ->counts('items')
                ->sortable()
                ->label('Productos'),
            TextColumn::make('li_created_at')->size('sm')
                ->label('Creado')
                ->sortable()
                ->searchable(),
            BooleanColumn::make('li_activo')
                ->label('Activa')
                ->toggleable()
                ->searchable(false),
        ])->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            InvListaProductoResource\RelationManagers\InvProductosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvListaProductos::route('/'),
            'create' => Pages\CreateInvListaProducto::route('/create'),
            'edit' => Pages\EditInvListaProducto::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    protected static function shouldRegisterNavigation(): bool {
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
        if(PerfilPanel::alcanceEsPorInstitucion()){
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('li_ins_code', $institucionesCodes);
        }

        // El Lider Operativo ve los locales de su(s) pais(es). Sin paises
        // asignados no ve nada: un lider mal configurado no debe terminar
        // con acceso global.
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('li_ins_code', $localesDelPais);
        }
        return $query;
    }
}
