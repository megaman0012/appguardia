<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserHasRolesResource\Pages;
use App\Filament\Resources\UserHasRolesResource\RelationManagers;
use App\helpers;
use Modules\Acceso\Models\user_has_roles;
use Modules\Acceso\Models\users;
use Modules\Acceso\Models\Role;

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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Session;

class UserHasRolesResource extends Resource
{
    public static function getNavigationGroup(): ?string{
        return 'Configuracion Sistema';
    }
    protected static ?int $navigationSort = 7;
    protected static ?string $navigationLabel = 'Usuarios > Perfiles';
    protected static ?string $model = user_has_roles::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-add';

    public static function form(Form $form): Form {
        return $form
            ->schema([
                Select::make('user_id')
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
                ->required(),
                Select::make('role_id')
                ->label('Perfil')
                ->options(
                    Role::where('estado', 1)->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->id => $item->name];
                    })
                )
                ->searchable()
                ->required()
                ->unique(table: static::$model, callback: function ($rule, $get) {
                    return $rule->where('user_id', $get('user_id'));
                }, ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('ru_code')->size('sm')
                    ->label('Codigo')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('users.usu_cedula')->size('sm')
                    ->label('Cedula')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('users.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('roles.name')->size('sm')
                    ->label('Perfil')
                    ->toggleable()
                    ->searchable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\DeleteAction::make()
                ->before(function ($record) {
                    helpers::control_log_filament($record->toArray(), 'UserHasRolesResource', 'Delete','NOTICE', 'Eliminar User Has Roles');
                }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserHasRoles::route('/'),
            'create' => Pages\CreateUserHasRoles::route('/create'),
            //'edit' => Pages\EditUserHasRoles::route('/{record}/edit'),
        ];
    }
    protected static function shouldRegisterNavigation(): bool {
        return in_array( Session::get('usuPF'), ['Administrador', 'Administrador General'] );
    }

}
