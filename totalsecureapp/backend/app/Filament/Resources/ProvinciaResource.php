<?php

namespace App\Filament\Resources;

use Filament\Actions;
use App\Filament\Resources\ProvinciaResource\Pages;
use App\Support\PerfilPanel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use App\Filament\Tables\Descarga;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Modules\Administracion\Models\Provincia;

class ProvinciaResource extends Resource
{
    protected static ?string $model = Provincia::class;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'provincia';
    protected static ?string $pluralModelLabel = 'provincias';
    protected static ?string $navigationLabel = 'Provincias';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map';
    protected static ?int $navigationSort = 2;

    /** Relacion que usa la columna de pais: sin esto, una consulta por fila. */
    protected const RELACIONES_TABLA = ['pais'];

    public static function getNavigationGroup(): ?string
    {
        return 'Ubicación geográfica';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('pr_pa_id')
                ->label('País')
                ->relationship('pais', 'pa_nombre')
                ->required()
                ->searchable(),
            TextInput::make('pr_nombre')->label('Provincia')->required()->maxLength(80),
            TextInput::make('pr_codigo')->label('Código')->maxLength(8)
                ->helperText('ISO 3166-2, p. ej. G para Guayas'),
            Toggle::make('pr_estado')->label('Activa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pais.pa_nombre')->label('País')->sortable()->searchable(),
                TextColumn::make('pr_nombre')->label('Provincia')->sortable()->searchable(),
                TextColumn::make('pr_codigo')->label('Código')->toggleable(),
                TextColumn::make('ciudades_count')->label('Ciudades')->counts('ciudades'),
                BooleanColumn::make('pr_estado')->label('Activa')->toggleable(),
            ])
            ->defaultSort('pr_nombre')
            ->actions([Actions\EditAction::make()])
            ->bulkActions([
                Descarga::enLote('provincia'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProvincias::route('/'),
            'create' => Pages\CreateProvincia::route('/create'),
            'edit'   => Pages\EditProvincia::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
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
