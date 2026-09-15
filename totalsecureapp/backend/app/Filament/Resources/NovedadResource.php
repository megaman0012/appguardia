<?php

namespace App\Filament\Resources;

use App\Support\PerfilPanel;

use App\Filament\Resources\NovedadResource\Pages;
use App\Filament\Resources\NovedadResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\Novedad;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\UserHasInstitucion;
use Modules\Acceso\Models\users;
use App\Filament\Tables\Descarga;
use Session;

class NovedadResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reportería';
    }
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'novedad';
    protected static ?string $pluralModelLabel = 'novedades';
    protected static ?string $navigationLabel = 'Novedades';
    protected static ?int $navigationSort = 5;
    protected static ?string $model = Novedad::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.cliente', 'users'];
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-question-mark-circle';

    /**
     * ⚠️ Esto era `return $schema->schema([ ]);` -- un formulario **vacio** -- y
     * aun asi el recurso tenia sus paginas de crear y editar registradas.
     *
     * El efecto era exactamente lo que se reporto: desde la web «se guardaba el
     * registro pero no la foto». No es que la foto fallara: **no habia ningun
     * campo**, ni de foto ni de nada, asi que se guardaba una novedad en blanco.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('nv_ins_code')
                ->label('Local')
                ->required()
                ->searchable()
                ->options(fn () => self::localesParaElFormulario())
                ->default(fn () => Session::get('insCode')),

            Select::make('nv_usu_id')
                ->label('Reportado por')
                ->required()
                ->searchable()
                ->options(fn () => users::where('usu_state', 1)
                    ->orderBy('usu_nmbcom')
                    ->pluck('usu_nmbcom', 'id'))
                ->default(fn () => Session::get('usuID')),

            DateTimePicker::make('nv_fecha_hora')
                ->label('Fecha y hora del hecho')
                ->required()
                ->seconds(false)
                ->default(now())
                // La carpeta de la foto se arma con esta fecha (ver
                // `Novedad::getImagenUrlAttribute`), asi que no puede quedar nula.
                ->maxDate(now()),

            Textarea::make('nv_observacion')
                ->label('Observación')
                ->required()
                ->rows(4)
                ->maxLength(1000)
                ->columnSpanFull(),

            FileUpload::make('nv_foto')
                ->label('Foto')
                ->image()
                ->disk('imagenes')
                // Misma convencion que `generalTrait::storeFiles()`, que es
                // donde las deja la app movil y donde las busca el accesor.
                ->directory('novedad/' . now()->format('Y/m/d'))
                ->visibility('public')
                ->maxSize(8192)
                ->helperText('Opcional. Máximo 8 MB.')
                ->columnSpanFull(),

            TextInput::make('nv_lat')->label('Latitud')->maxLength(255),
            TextInput::make('nv_lng')->label('Longitud')->maxLength(255),
        ])->columns(2);
    }

    /**
     * Los locales que el usuario puede elegir.
     *
     * Acotado por el mismo alcance que el listado: sin esto, un Supervisor
     * podria registrar una novedad en el local de otro cliente eligiendolo del
     * desplegable.
     */
    private static function localesParaElFormulario(): array
    {
        $locales = PerfilPanel::localesVisibles();

        $query = OrganizacionInstitucion::where('ins_estado', true);

        if ($locales !== null) {
            if (empty($locales)) {
                return [];
            }
            $query->whereIn('ins_code', $locales);
        }

        return $query->orderBy('ins_descripcion')->pluck('ins_descripcion', 'ins_code')->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nv_id')->size('sm')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                    ->label('Cliente')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Local')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('users.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->searchable(),
                TextColumn::make('nv_observacion')->size('sm')
                    ->label('Observación')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('nv_fecha_hora')->size('sm')
                    ->label('Fecha')
                    ->sortable()
                    ->dateTime(),
                ImageColumn::make('imagen_url')
                    ->label('Imagen')
                    ->width(35)
                    ->height(35)
                    ->circular()
                    ->view('tables.columns.imagen-modal'),
            ])
            ->filters([
                Filter::make('Filtro Novedades')
                    ->label('Rango de fecha')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde'),
                        DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date) =>
                                $query->whereDate('nv_fecha_hora', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date) =>
                                $query->whereDate('nv_fecha_hora', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Action::make('gmap')
                    ->label('Mapa')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->nv_lat},{$record->nv_lng}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary'),
            ])
            ->bulkActions([
                Descarga::enLote('novedad'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNovedads::route('/'),
            //'create' => Pages\CreateNovedad::route('/create'),
            //'edit' => Pages\EditNovedad::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
        if(PerfilPanel::alcanceEsPorInstitucion()){
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('nv_ins_code', $institucionesCodes);
        }

        // El Lider Operativo ve los locales de su(s) pais(es). Sin paises
        // asignados no ve nada: un lider mal configurado no debe terminar
        // con acceso global.
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('nv_ins_code', $localesDelPais);
        }
        return $query;
    }

}
