<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstitucionMarcadoresResource\Pages;
use App\Filament\Resources\InstitucionMarcadoresResource\RelationManagers;
use Modules\Administracion\Models\InstitucionMarcadores;
use Modules\Administracion\Models\OrganizacionInstitucion;

use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;

use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\BadgeColumn;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InstitucionMarcadoresResource extends Resource
{

    public static function getNavigationGroup(): ?string {
        return 'Centros de Operacion';
    }
    protected static ?string $model = InstitucionMarcadores::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.cliente'];
    protected static ?string $navigationIcon = 'heroicon-o-collection';
    protected static bool $shouldRegisterNavigation = false;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Hidden::make('im_ins_code')
                ->default(request()->query('codigo')),

                Select::make('im_tipo')
                ->label('Tipo')
                ->options([
                    'Entrada' => 'Entrada',
                    'Punto Control' => 'Punto Control'
                ])
                ->required(),

                TextInput::make('im_descripcion')
                    ->label('Descripción')
                    ->required(),

                TextInput::make('im_lat')
                    ->label('Latitud')
                    ->numeric()
                    ->required(),

                TextInput::make('im_lng')
                    ->label('Longitud')
                    ->numeric()
                    ->required(),

                Toggle::make('im_estado')
                    ->label('Activo')
                    ->default(true),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('im_code')->size('sm')
                    ->sortable()
                    ->label('Codigo'),
                TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Institucion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('im_tipo')->size('sm')
                    ->label('Tipo'),
                TextColumn::make('im_descripcion')->size('sm')
                    ->label('Descripción'),
                TextColumn::make('im_lat')->size('sm')
                    ->label('Latitud'),
                TextColumn::make('im_lng')->size('sm')
                    ->label('Longitud'),
                BooleanColumn::make('im_estado')->label('Activo'),
            ])
            ->filters([])
            ->actions([
                Action::make('gmap')
                    ->label('Mapa')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->im_lat},{$record->im_lng}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary'),
                Tables\Actions\EditAction::make()
                ->url(fn($record)=>
                    InstitucionMarcadoresResource::getUrl(
                        'edit', [ 'record' => $record->im_code,'codigo' => $record->im_ins_code ]
                    )
                ),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListInstitucionMarcadores::route('/'),
            'create' => Pages\CreateInstitucionMarcadores::route('/create'),
            'edit' => Pages\EditInstitucionMarcadores::route('/{record}/edit/'),
        ];
    }

    public static function getEloquentQuery(): Builder {
        $ins_code = request()->query('codigo');
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA)
        ->where('im_ins_code', $ins_code );
    }

}
