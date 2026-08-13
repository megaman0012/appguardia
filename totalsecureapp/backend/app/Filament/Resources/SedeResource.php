<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SedeResource\Pages;
use App\Filament\Resources\SedeResource\RelationManagers;
use Modules\Administracion\Models\Sede;

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

class SedeResource extends Resource {

    public static function getNavigationGroup(): ?string {
        return 'Ubicacion Geografica';
    }


    protected static ?string $model = Sede::class;
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Sede';
    protected static ?string $navigationIcon = 'heroicon-o-globe';
    //protected static bool $shouldRegisterNavigation = $viewsection;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('ps_sigla')
                    ->label('Sigla')
                    ->maxLength(5)
                    ->unique(table: static::$model, column: 'ps_sigla', ignoreRecord: true)
                    ->validationAttribute('Sigla'),
                TextInput::make('ps_descripcion')
                    ->label('Descripción')
                    ->required()
                    ->maxLength(80)
                    ->unique(table: static::$model, column: 'ps_descripcion', ignoreRecord: true),
                Toggle::make('ps_estado')
                    ->label('Estado')
                    ->required()
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ps_code')->size('sm')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('ps_sigla')->size('sm')
                    ->label('Sigla')
                    ->searchable(),
                TextColumn::make('ps_descripcion')->size('sm')
                    ->label('Descripción')
                    ->searchable(),
                TextColumn::make('created_at')->size('sm')
                    ->label('Fecha de Creación')
                    ->sortable()
                    ->searchable(),
                BooleanColumn::make('ps_estado')
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
            'index' => Pages\ListSedes::route('/'),
            'create' => Pages\CreateSede::route('/create'),
            'edit' => Pages\EditSede::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    protected static function shouldRegisterNavigation(): bool {
        return in_array( Session::get('usuPF'), ['Administrador', 'Administrador General'] );
    }
}

