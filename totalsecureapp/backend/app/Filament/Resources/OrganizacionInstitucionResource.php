<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizacionInstitucionResource\Pages;
use App\Filament\Resources\OrganizacionInstitucionResource\RelationManagers\InstitucionMarcadoresRelationManager;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\Sede;
use Modules\Administracion\Models\Organizacion;
use Modules\Administracion\Models\OrganizacionSede;
use Session;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tab;

use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\UserHasInstitucion;

class OrganizacionInstitucionResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Centros de Operacion';
    }

    protected static ?string $model = OrganizacionInstitucion::class;
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Organizacion > Institucion';
    protected static ?string $navigationIcon = 'heroicon-o-flag';

    public static function form(Form $form): Form {
        return $form->schema([

            Select::make('ins_so_code')
                ->label('Sede / Organizacion')
                ->options(
                    OrganizacionSede::where('so_estado', 1)
                        ->with(['organizacion', 'sede'])->get()
                        ->mapWithKeys(function ($item, $key) {
                            //dd($item->organizacion->org_descripcion);
                            $sede = $item->sede->ps_descripcion ?? 'Sin Sede';
                            $organizacion = $item->organizacion->org_descripcion ?? 'Sin Organizacion';
                            return [$item->so_code => $sede . ' / ' . $organizacion];
                        })

                )
                ->searchable()
                ->required()
                ->disabledOn('edit'),
            TextInput::make('ins_descripcion')
                ->label('Descripcion')
                ->required()
                ->maxLength(255)
                ->unique(table: static::$model, callback: function ($rule, $get) {
                    return $rule->where('ins_so_code', $get('ins_so_code'));
                }, ignoreRecord: true),

            TextInput::make('ins_razon_social')
                ->label('Razon Social')
                ->required()
                ->maxLength(255),

            TextInput::make('ins_direccion')
                ->label('Direccion')
                ->required()
                ->maxLength(255),

            TextInput::make('ins_ciudad')
                ->label('Ciudad')
                ->required()
                ->maxLength(250),

            TextInput::make('ins_telefono')
                ->label('Telefono')
                ->required()
                ->maxLength(20),

            TextInput::make('ins_email')
                ->label('Email')
                ->required()
                ->maxLength(255)
                ->email(),

            TextInput::make('ins_tipo')
                ->label('Tipo')
                ->required()
                ->maxLength(100),

            Toggle::make('ins_estado')
                ->label('Activo')
                ->required()
                ->default(true),

        ]);
    }

    public static function table(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('ins_code')->size('sm')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organizacionsede.sede.ps_descripcion')->size('sm')
                    ->label('Sede')
                    ->searchable(false)
                    ->toggleable(),
                TextColumn::make('organizacionsede.organizacion.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->searchable(false)
                    ->toggleable(),
                TextColumn::make('ins_descripcion')->size('sm')
                    ->label('Institución')
                    ->searchable(),
                TextColumn::make('ins_ciudad')->size('sm')
                    ->label('Ciudad/Estado')
                    ->searchable(false)
                    ->toggleable(),
                TextColumn::make('ins_direccion')->size('sm')
                    ->label('Dirección')
                    ->searchable(false)
                    ->toggleable(),
                TextColumn::make('ins_email')->size('sm')
                    ->label('Email')
                    ->searchable(false)
                    ->toggleable(),
                BooleanColumn::make('ins_estado')
                    ->label('Estado')
                    ->sortable()
                    ->toggleable()
                    ->searchable(false),

            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => in_array( Session::get('usuPF'), ['Administrador', 'Administrador General'] )),
                Action::make('marcadores')
                ->label('Marcadores')
                ->icon('heroicon-o-map')  // Icono de editar
                ->url(fn ($record) => route('filament.resources.organizacion-institucions.edit', $record))
                ->color('primary')
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array {
        return [
            InstitucionMarcadoresRelationManager::class
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListOrganizacionInstitucions::route('/'),
            'create' => Pages\CreateOrganizacionInstitucion::route('/create'),
            'edit' => Pages\EditOrganizacionInstitucion::route('/{record}/edit'),
            //'marcadores' => Pages\MarcadoresPage::route('/{record}/marcadores'),
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
            return $query->whereIn('ins_code', $institucionesCodes);
        }
        return $query;
    }

}
