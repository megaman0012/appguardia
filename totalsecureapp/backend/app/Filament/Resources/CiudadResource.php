<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CiudadResource\Pages;
use App\Support\PerfilPanel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Modules\Administracion\Models\Ciudad;

/**
 * Ciudad: el nivel al que se engancha cada Local.
 *
 * De aqui sale el pais del local (ciudad -> provincia -> pais), que es lo que
 * acota al Lider Operativo.
 */
class CiudadResource extends Resource
{
    protected static ?string $model = Ciudad::class;
    protected static ?string $navigationLabel = 'Ciudad';
    protected static ?string $navigationIcon = 'heroicon-o-office-building';
    protected static ?int $navigationSort = 3;

    protected const RELACIONES_TABLA = ['provincia.pais'];

    public static function getNavigationGroup(): ?string
    {
        return 'Ubicacion Geografica';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('cd_pr_id')
                ->label('Provincia')
                ->relationship('provincia', 'pr_nombre')
                ->required()
                ->searchable(),
            TextInput::make('cd_nombre')->label('Ciudad')->required()->maxLength(80),
            Toggle::make('cd_estado')->label('Activa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provincia.pais.pa_nombre')->label('País')->sortable()->searchable(),
                TextColumn::make('provincia.pr_nombre')->label('Provincia')->sortable()->searchable(),
                TextColumn::make('cd_nombre')->label('Ciudad')->sortable()->searchable(),
                TextColumn::make('locales_count')->label('Locales')->counts('locales'),
                BooleanColumn::make('cd_estado')->label('Activa')->toggleable(),
            ])
            ->defaultSort('cd_nombre')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCiudades::route('/'),
            'create' => Pages\CreateCiudad::route('/create'),
            'edit'   => Pages\EditCiudad::route('/{record}/edit'),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
    }
}
