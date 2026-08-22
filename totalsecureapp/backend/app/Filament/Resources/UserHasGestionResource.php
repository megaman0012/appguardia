<?php

namespace App\Filament\Resources;
use Closure;
use App\Filament\Resources\UserHasGestionResource\Pages;
use App\Filament\Resources\UserHasGestionResource\RelationManagers;
use Filament\Tables\Actions\Action;
use Modules\Acceso\Models\user_has_gestions;
use Modules\Acceso\Models\users;

use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\BadgeColumn;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Session;

class UserHasGestionResource extends Resource{

    public static function getNavigationGroup(): ?string{
        return 'Configuracion Sistema';
    }
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationLabel = 'Usuarios > Gestion';
    protected static ?string $model = user_has_gestions::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['usuario'];
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    public static function form(Form $form): Form {
        return $form->schema([
            Select::make('ug_user_id')
                ->label('Usuario')
                ->options(
                    users::where('usu_state', 1)
                    ->orderBy('usu_nmbcom')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [$item->id => $item->usu_nmbcom];
                    })
                )
                ->searchable()
                ->disabledOn('edit')
                ->required(),
            DatePicker::make('ug_ingreso')
                ->label('Ingreso')
                ->disabledOn('edit'),
            DatePicker::make('ug_egreso')
                ->label('Egreso')
                ->disabledOn('create'),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ug_code')->size('sm')
                    ->label('Codigo')
                    ->searchable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_cedula')->size('sm')
                    ->label('Cedula')
                    ->searchable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_nmbcom')->size('sm')
                    ->label('Nombres')
                    ->searchable()
                    ->toggleable()
                    ->searchable(),
                BooleanColumn::make('ug_finish')->size('sm')
                    ->label('Finalizada')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ug_ingreso')->size('sm')
                    ->label('Inicio')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ug_egreso')->size('sm')
                    ->label('Fin')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
            ])
            ->filters([ ])
            ->actions([
                /*Tables\Actions\EditAction::make()
                ->disabled(fn ($record) => $record->ug_egreso !== null),*/
                Action::make('Editar')
                    ->label('Editar')
                    ->visible(fn ($record) => $record->ug_finish == 0)
                    ->icon('heroicon-o-pencil')  // Icono de editar
                    ->url(fn ($record) => route('filament.resources.user-has-gestions.edit1', $record))
                    ->color('primary')
            ])
            ->bulkActions([ ])
            ;
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListUserHasGestions::route('/'),
            'create' => Pages\CreateUserHasGestion::route('/create'),
            'edit1' => Pages\EditUserHasGestion::route('/{record}/edit'),
        ];
    }

    protected static function shouldRegisterNavigation(): bool {
        return in_array( Session::get('usuPF'), ['Administrador', 'Administrador General'] );
    }



    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
    }
}
