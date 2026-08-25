<?php

namespace App\Filament\Resources;

use App\Support\PerfilPanel;

use App\Filament\Resources\UsersResource\Pages;
use App\Filament\Resources\UsersResource\RelationManagers;
use Modules\Acceso\Models\users;

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

class UsersResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Configuracion Sistema';
    }
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $model = users::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function form(Form $form): Form {
        return $form
            ->schema([
                TextInput::make('usu_cedula')
                    ->label('Cedula')
                    ->required()
                    ->unique(table: static::$model, column: 'usu_cedula', ignoreRecord: true),
                TextInput::make('usu_tipdoc')
                    ->label('Tipo de Documento')
                    ->required(),
                TextInput::make('usu_nmbcom')
                    ->label('Nombre Completo')
                    ->required(),
                TextInput::make('usu_ape1')
                    ->label('Primer Apellido')
                    ->required(),
                TextInput::make('usu_ape2')
                    ->label('Segundo Apellido')
                    ->required(),
                TextInput::make('usu_nmb1')
                    ->label('Primer Nombre')
                    ->required(),
                TextInput::make('usu_nmb2')
                    ->label('Segundo Nombre')
                    ->required(),
                TextInput::make('usu_email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required()
                    ->unique(table: static::$model, column: 'usu_email', ignoreRecord: true),
                Toggle::make('usu_state')
                    ->label('Estado')
                    ->required()
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->size('sm')
                    ->label('Codigo')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usu_cedula')->size('sm')
                    ->label('Cedula')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usu_nmbcom')->size('sm')
                    ->label('Nombres')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usu_email')->size('sm')
                    ->label('Correo')
                    ->toggleable()
                    ->searchable(),
                BooleanColumn::make('usu_state')
                    ->label('Estado')
                    ->sortable()
                    ->toggleable()
                    ->searchable(false),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUsers::route('/create'),
            'edit' => Pages\EditUsers::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

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

}
