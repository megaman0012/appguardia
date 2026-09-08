<?php

namespace App\Filament\Resources;

use App\Filament\Tables\FiltroDeEstado;

use App\Filament\Resources\InstitucionMarcadoresResource\Pages;
use App\Filament\Resources\InstitucionMarcadoresResource\RelationManagers;
use Modules\Administracion\Models\InstitucionMarcadores;
use Modules\Administracion\Models\OrganizacionInstitucion;

use Filament\Resources\Form;
use App\Filament\Tables\Descarga;
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

                // ⚠️ **El rango no es decorativo.** 64 marcadores llegaron de v1
                // con latitud y longitud positivas; Ecuador esta al oeste y al
                // sur, asi que ambas son negativas (la latitud, salvo en el
                // norte del pais). Con el signo invertido la geocerca calculaba
                // 17.764 km y **en 61 de 110 locales el marcaje biometrico era
                // imposible**: el guardia solo veia «Fuera de geocerca».
                // Corregido en 2026_09_08_220001; esto evita que vuelva a
                // entrar escribiendolo a mano.
                TextInput::make('im_lat')
                    ->label('Latitud')
                    ->numeric()
                    ->minValue(-5)
                    ->maxValue(1.5)
                    ->required()
                    ->helperText('Ecuador: entre -5 y 1.5. Guayaquil ronda -2.19 (con el signo menos)'),

                TextInput::make('im_lng')
                    ->label('Longitud')
                    ->numeric()
                    ->minValue(-81.2)
                    ->maxValue(-75.2)
                    ->required()
                    ->helperText('Ecuador: entre -81.2 y -75.2. Siempre negativa'),

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
                    ->label('Código'),
                TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                    ->label('Cliente')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Local')
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
            ->filters([
                // Abre mostrando solo los activos. Ver App\Filament\Tables\FiltroDeEstado.
                FiltroDeEstado::make('im_estado', true, 'Estado'),
            ])
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
            ->bulkActions([
                Descarga::enLote('institucion-marcadores'),
            ]);
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
