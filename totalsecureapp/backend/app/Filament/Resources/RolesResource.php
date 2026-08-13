<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RolesResource\Pages;
use App\Filament\Resources\RolesResource\RelationManagers;
use Modules\Acceso\Models\roles;
use Modules\Acceso\Models\users;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RolesResource extends Resource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Configuracion Sistema'; // Agrupar bajo "Geografía"
    }
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Perfiles';
    protected static ?string $model = roles::class;
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

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
