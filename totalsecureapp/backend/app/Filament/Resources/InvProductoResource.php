<?php

namespace App\Filament\Resources;

use Session;
use App\Filament\Resources\InvProductoResource\Pages;
use Filament\Forms\Components\Toggle;
use Modules\Administracion\Models\InvProducto;
use Filament\Resources\Resource;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms;
use Filament\Tables;

class InvProductoResource extends Resource
{
    protected static ?string $model = InvProducto::class;

    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Productos';
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('pr_nombre')
                ->label('Nombre')
                ->unique(ignoreRecord: true)
                ->required(),
            TextInput::make('pr_especificacion')
                ->label('Especificacion')
                ->required(),
            Textarea::make('pr_descripcion')
                ->label('Descripcion'),
            Toggle::make('pr_estado')
                ->label('Estado')
                ->required()
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pr_id')->size('sm')
                    ->label('ID')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pr_nombre')->size('sm')
                    ->label('Producto')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('pr_especificacion')->size('sm')
                    ->label('Especificacion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('pr_descripcion')->size('sm')
                    ->label('Descripcion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('pr_created_at')->size('sm')
                    ->label('Fecha de Creación')
                    ->sortable()
                    ->searchable(),
                BooleanColumn::make('pr_estado')
                    ->label('Estado')
                    ->toggleable()
                    ->searchable(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvProductos::route('/'),
            'create' => Pages\CreateInvProducto::route('/create'),
            'edit' => Pages\EditInvProducto::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    protected static function shouldRegisterNavigation(): bool {
        return in_array( Session::get('usuPF'), ['Administrador', 'Administrador General'] );
    }
}

