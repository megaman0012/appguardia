<?php

namespace App\Filament\Resources;

use App\Filament\Forms\SelectorDeUsuario;

use App\Support\PerfilPanel;

use App\Filament\Resources\UserHasRolesResource\Pages;
use App\Filament\Resources\UserHasRolesResource\RelationManagers;
use App\helpers;
use Modules\Acceso\Models\user_has_roles;
use Modules\Acceso\Models\users;
use Modules\Acceso\Models\Role;

use Filament\Resources\Form;
use App\Filament\Tables\Descarga;
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
        return 'Configuración';
    }
    protected static ?int $navigationSort = 2;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'asignación';
    protected static ?string $pluralModelLabel = 'asignaciones';
    protected static ?string $navigationLabel = 'Perfil por usuario';
    protected static ?string $model = user_has_roles::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['roles', 'users'];
    protected static ?string $navigationIcon = 'heroicon-o-user-add';

    public static function form(Form $form): Form {
        return $form
            ->schema([
                // Busca por nombre Y por cedula. Ver App\Filament\Forms\SelectorDeUsuario:
                // antes filtraba el texto de la etiqueta en el navegador, asi que
                // buscar por cedula solo funcionaba donde la etiqueta la incluia.
                SelectorDeUsuario::make('user_id', 'Usuario')
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
                    ->label('Código')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('users.usu_cedula')->size('sm')
                    ->label('Cédula')
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
            ->bulkActions([
                Descarga::enLote('user-has-roles'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserHasRoles::route('/'),
            'create' => Pages\CreateUserHasRoles::route('/create'),
            'edit' => Pages\EditUserHasRoles::route('/{record}/edit'),
        ];
    }
    protected static function shouldRegisterNavigation(): bool {
        return PerfilPanel::puedeGestionarPersonal();
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
        return PerfilPanel::puedeGestionarPersonal();
    }


    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
    }
}
