<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserHasInstitucionResource\Pages;
use App\Filament\Resources\UserHasInstitucionResource\RelationManagers;

use App\helpers;
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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Acceso\Models\users;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\UserHasInstitucion;
use Session;

class UserHasInstitucionResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Configuracion Sistema';
    }
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationLabel = 'Usuarios > Institucion';
    protected static ?string $model = UserHasInstitucion::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('ui_usu_id')
                    ->label('Usuario')
                    ->options(
                        users::where('usu_state', 1)
                        ->orderBy('usu_nmbcom')
                        ->get()
                        ->mapWithKeys(function ($item) {
                            return [$item->id => $item->usu_nmbcom];
                        })
                    )
                    ->searchable()
                    ->disabledOn('edit')
                    ->required(),
                Select::make('ui_ins_code')
                    ->label('Institucion')
                    ->options(
                        OrganizacionInstitucion::where('ins_estado', 1)->get()
                            ->mapWithKeys(function ($item) {
                                return [$item->ins_code => $item->ins_descripcion];
                            })
                    )
                    ->searchable()
                    ->disabledOn('edit')
                    ->required()
                    ->unique(table: static::$model, callback: function ($rule, $get) {
                        return $rule->where('ui_usu_id', $get('ui_usu_id'));
                    }, ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ui_code')->size('sm')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('institucion.organizacionSede.sede.ps_descripcion')->size('sm')
                    ->label('Sede')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.organizacionSede.organizacion.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Institucion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_cedula')->size('sm')
                    ->label('Cedula')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ui_created_at')->size('sm')
                    ->label('Creacion')
                    ->toggleable()
                    ->searchable(),
                BooleanColumn::make('ui_state')
                    ->label('Estado')
                    ->sortable()
                    ->toggleable()
                    ->searchable(false),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        helpers::control_log_filament($record->toArray(), 'UserHasInstitucionResource', 'Delete','NOTICE', 'Eliminar User Has Institucion');
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserHasInstitucions::route('/'),
            'create' => Pages\CreateUserHasInstitucion::route('/create'),
            //'edit' => Pages\EditUserHasInstitucion::route('/{record}/edit'),
        ];
    }

    protected static function shouldRegisterNavigation(): bool {
        return in_array( Session::get('usuPF'), ['Administrador', 'Administrador General'] );
    }
}
