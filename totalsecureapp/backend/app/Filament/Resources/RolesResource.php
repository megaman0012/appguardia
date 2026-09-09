<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RolesResource\Pages;
use App\Filament\Resources\RolesResource\RelationManagers;
use Modules\Acceso\Models\roles;
use Modules\Acceso\Models\users;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Tables\Descarga;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RolesResource extends Resource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Configuración'; // Agrupar bajo "Geografía"
    }
    protected static ?int $navigationSort = 5;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'perfil';
    protected static ?string $pluralModelLabel = 'perfiles';
    protected static ?string $navigationLabel = 'Perfiles y permisos';
    protected static ?string $model = roles::class;
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Perfil'),
                Tables\Columns\TextColumn::make('name')->label('Perfil'),
                Tables\Columns\TextColumn::make('descripcion')->label('Descripción'),
                Tables\Columns\BooleanColumn::make('estado')
                ->label('Estado')
                ->sortable() // Si deseas que la columna sea ordenable
                ->toggleable() // Permite cambiar el valor haciendo clic en el ícono
                ->searchable(false),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Descarga::enLote('roles'),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRoles::route('/create'),
            'edit' => Pages\EditRoles::route('/{record}/edit'),
        ];
    }
}
