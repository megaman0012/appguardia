<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaisResource\Pages;
use App\Support\PerfilPanel;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Modules\Administracion\Models\Pais;

/**
 * Paises donde la empresa opera. Es el nivel por el que se acota al Lider
 * Operativo, asi que solo Sistemas los define.
 */
class PaisResource extends Resource
{
    protected static ?string $model = Pais::class;
    protected static ?string $navigationLabel = 'País';
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Ubicacion Geografica';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('pa_iso2')
                ->label('Código ISO (2 letras)')
                ->required()
                ->maxLength(2)
                ->helperText('EC, CO, PE…')
                ->unique(table: static::$model, column: 'pa_iso2', ignoreRecord: true),
            TextInput::make('pa_iso3')
                ->label('Código ISO (3 letras)')
                ->maxLength(3)
                ->helperText('ECU, COL, PER…'),
            TextInput::make('pa_nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(80),
            Toggle::make('pa_estado')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pa_iso2')->label('ISO')->sortable()->searchable(),
                TextColumn::make('pa_nombre')->label('País')->sortable()->searchable(),
                TextColumn::make('provincias_count')->label('Provincias')->counts('provincias'),
                BooleanColumn::make('pa_estado')->label('Activo')->toggleable(),
            ])
            ->defaultSort('pa_nombre')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPais::route('/'),
            'create' => Pages\CreatePais::route('/create'),
            'edit'   => Pages\EditPais::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    protected static function shouldRegisterNavigation(): bool
    {
        return PerfilPanel::puedeConfigurarSistema();
    }

    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeConfigurarSistema();
    }
}
