<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizacionResource\Pages;
use App\Filament\Resources\OrganizacionResource\RelationManagers;
use Modules\Administracion\Models\Organizacion;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Session;

class OrganizacionResource extends Resource {

    public static function getNavigationGroup(): ?string {
        return 'Centros de Operacion';
    }

    protected static ?string $model = Organizacion::class;
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Organizacion';
    protected static ?string $navigationIcon = 'heroicon-o-office-building';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('org_descripcion')
                    ->label('Nombre de la Organización')
                    ->required()
                    ->maxLength(255)
                    ->unique(table: static::$model, column: 'org_descripcion', ignoreRecord: true),
                TextInput::make('org_razon_social')
                    ->label('Razón Social')
                    ->maxLength(255),
                TextInput::make('org_telefono')
                    ->label('Teléfono')
                    ->maxLength(20),
                TextInput::make('org_email')
                    ->label('Correo Electrónico')
                    ->maxLength(255)
                    ->email()
                    ->required()
                    ->unique(table: static::$model, column: 'org_email', ignoreRecord: true),
                TextInput::make('org_website')
                    ->label('Sitio Web')
                    ->maxLength(255),
                TextInput::make('org_numero_registro')
                    ->label('Número de Registro')
                    ->maxLength(100)
                    ->required()
                    ->unique(table: static::$model, column: 'org_numero_registro', ignoreRecord: true),
                Toggle::make('org_estado')
                    ->label('Estado')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('org_code')->size('sm')
                    ->label('Código')
                    ->sortable()
                    ->searchable(false),
                TextColumn::make('org_numero_registro')->size('sm')
                    ->label('Número de Registro')
                    ->searchable(),
                TextColumn::make('org_descripcion')->size('sm')
                    ->label('Organización')
                    ->searchable(),
                TextColumn::make('org_email')->size('sm')
                    ->label('Correo Electrónico')
                    ->searchable(false),
                TextColumn::make('org_website')->size('sm')
                    ->label('Sitio Web')
                    ->searchable(false),
                BooleanColumn::make('org_estado')
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
            'index' => Pages\ListOrganizacions::route('/'),
            'create' => Pages\CreateOrganizacion::route('/create'),
            'edit' => Pages\EditOrganizacion::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    protected static function shouldRegisterNavigation(): bool {
        return in_array( Session::get('usuPF'), ['Administrador', 'Administrador General'] );
    }
}
