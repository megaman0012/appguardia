<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizacionSedeResource\Pages;
use App\Filament\Resources\OrganizacionSedeResource\RelationManagers;
use Modules\Administracion\Models\OrganizacionSede;
use Modules\Administracion\Models\Sede;
use Modules\Administracion\Models\Organizacion;

use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;

use Filament\Tables\Filters\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Session;

class OrganizacionSedeResource extends Resource {

    public static function getNavigationGroup(): ?string {
        return 'Centros de Operacion';
    }

    protected static ?string $model = OrganizacionSede::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['organizacion', 'sede'];
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Organizacion < Sede';
    protected static ?string $navigationIcon = 'heroicon-o-view-grid-add';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('so_ps_code')
                ->label('Sede')
                ->options(
                    Sede::where('ps_estado', 1)
                        ->pluck('ps_descripcion', 'ps_code')
                        ->mapWithKeys(function ($item, $key) {
                            return [$key => $item ?? 'Sin Sede'];
                        })
                    )
                ->searchable()
                ->required(),
                Select::make('so_org_code')
                ->label('Organización')
                ->options(
                    Organizacion::where('org_estado', 1)
                        ->pluck('org_descripcion', 'org_code')
                        ->mapWithKeys(function ($item, $key) {
                            return [$key => $item ?? 'Sin organización'];
                        })
                    )
                ->searchable()
                ->required()
                ->unique(table: static::$model, callback: function ($rule, $get) {
                    return $rule->where('so_ps_code', $get('so_ps_code'));
                }, ignoreRecord: true),
                Toggle::make('so_estado')
                    ->label('Estado')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('so_code')->size('sm')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('sede.ps_descripcion')->size('sm')
                    ->label('Sede')
                    ->searchable(false),
                TextColumn::make('organizacion.org_descripcion')->size('sm')
                    ->label('Organización')
                    ->searchable(),
                TextColumn::make('created_at')->size('sm')
                    ->label('Vinculacion')
                    ->searchable()
                    ->sortable(),
                BooleanColumn::make('so_estado')
                    ->label('Estado')
                    ->sortable()
                    ->toggleable()
                    ->searchable(false),
            ])
            ->filters([])
            ->actions([ Tables\Actions\EditAction::make() ])
            ->bulkActions([]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListOrganizacionSedes::route('/'),
            'create' => Pages\CreateOrganizacionSede::route('/create'),
            'edit' => Pages\EditOrganizacionSede::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
        //->whereHas('sede', function ($query) { $query->where('ps_estado', 1); })
        //->whereHas('organizacion', function ($query) { $query->where('org_estado', 1); });
    }

    protected static function shouldRegisterNavigation(): bool {
        return in_array( Session::get('usuPF'), ['Administrador', 'Administrador General'] );
    }

}
