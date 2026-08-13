<?php

namespace App\Filament\Resources;

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
                        if (in_array(Session::get('usuPF'), ['Supervisor'])) {
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

            TextColumn::make('institucion.organizacionSede.sede.ps_descripcion')->size('sm')
                ->label('Sede')
                ->searchable()
                ->toggleable(),
            TextColumn::make('institucion.organizacionSede.organizacion.org_descripcion')->size('sm')
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
        return in_array( Session::get('usuPF'), ['Administrador', 'Administrador General', 'Supervisor'] );
    }

    public static function getEloquentQuery(): Builder {
        $query = parent::getEloquentQuery();
        if(in_array( Session::get('usuPF'), ['Supervisor'] )){
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('lp_ins_code', $institucionesCodes);
        }
        return $query;
    }
}
