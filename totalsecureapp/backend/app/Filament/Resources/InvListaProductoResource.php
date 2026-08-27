<?php

namespace App\Filament\Resources;

use App\Support\PerfilPanel;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Modules\Administracion\Models\UserHasInstitucion;
use Session;
use App\Filament\Resources\InvListaProductoResource\Pages;
use App\Filament\Resources\InvListaProductoResource\RelationManagers\ProductosRelationManager;
use Modules\Administracion\Models\InvListaProducto;
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

class InvListaProductoResource extends Resource
{
    protected static ?string $model = InvListaProducto::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.cliente'];
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Listas > Productos';
    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('lp_ins_code')
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
            TextInput::make('lp_nombre')
                ->label('Nombre')
                ->required()
                ->unique(table: static::$model, callback: function ($rule, $get) {
                    return $rule->where('lp_ins_code', $get('lp_ins_code'));
                }, ignoreRecord: true),
            Textarea::make('lp_descripcion')
                ->label('Descripción'),
            Toggle::make('lp_estado')
                ->label('Estado')
                ->required()
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('lp_id')->size('sm')
                ->label('Codigo')
                ->sortable()
                ->searchable(),
            TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                ->label('Organizacion')
                ->searchable()
                ->toggleable(),
            TextColumn::make('institucion.ins_descripcion')->size('sm')
                ->label('Institucion')
                ->searchable()
                ->toggleable(),
            TextColumn::make('lp_nombre')->size('sm')
                ->label('Nombre')
                ->searchable(),
            TextColumn::make('lp_descripcion')->size('sm')
                ->label('Descripcion')
                ->searchable(),
            TextColumn::make('productos_count')
                ->counts('productos')
                ->sortable()
                ->label('Productos'),
            TextColumn::make('lp_created_at')->size('sm')
                ->label('Fecha de Creación')
                ->sortable()
                ->searchable(),
            BooleanColumn::make('lp_estado')
                ->label('Estado')
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
            return $query->whereIn('lp_ins_code', $institucionesCodes);
        }

        // El Lider Operativo ve los locales de su(s) pais(es). Sin paises
        // asignados no ve nada: un lider mal configurado no debe terminar
        // con acceso global.
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('lp_ins_code', $localesDelPais);
        }
        return $query;
    }
}
