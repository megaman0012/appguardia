<?php

namespace App\Filament\Resources;

use App\Filament\Tables\FiltroDeEstado;

use App\Support\PerfilPanel;

use App\Filament\Resources\OrganizacionInstitucionResource\Pages;
use App\Filament\Resources\OrganizacionInstitucionResource\RelationManagers\InstitucionMarcadoresRelationManager;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\Organizacion;
use Session;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tab;

use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\UserHasInstitucion;

class OrganizacionInstitucionResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Centros de operación';
    }

    protected static ?string $model = OrganizacionInstitucion::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['ciudad.provincia.pais', 'cliente'];
    protected static ?int $navigationSort = 2;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase.
    protected static ?string $modelLabel = 'local';
    protected static ?string $pluralModelLabel = 'locales';
    protected static ?string $navigationLabel = 'Locales';
    protected static ?string $navigationIcon = 'heroicon-o-flag';

    public static function form(Form $form): Form {
        return $form->schema([

            TextInput::make('ins_descripcion')
                ->label('Descripción')
                ->required()
                ->maxLength(255)
                // Antes la unicidad colgaba de la sede. Sin sede, lo que
                // distingue a dos locales homonimos es la ciudad: "Bodega
                // Norte" puede existir en Quito y en Guayaquil.
                ->unique(table: static::$model, callback: function ($rule, $get) {
                    return $rule->where('ins_cd_id', $get('ins_cd_id'));
                }, ignoreRecord: true),

            // ⚠️ **Esta columna se llama «razon social» pero no lo es.** La
            // razon social del cliente vive en el cliente (`org_razon_social`),
            // y aca repetia ese nombre en 73 de 137 locales -- o decia «Total
            // Security Company», que es la empresa de guardias: relleno de un
            // campo obligatorio que nadie sabia como llenar.
            //
            // Lo interesante es lo que hicieron los otros 53: escribieron el
            // **sitio**. En el cliente JBGYE dice «Hospital Alfredo Paulson» u
            // «Hospital Roberto Gilbert», y eso separa limpio dos hospitales
            // que comparten la direccion «Av. Democracia». Es la señal mas
            // fiable para deducir que locales son en realidad puestos del mismo
            // lugar (ver `php artisan puestos:analizar`).
            //
            // Asi que se queda, pero con el nombre de lo que de verdad guarda y
            // sin ser obligatorio: obligar es lo que lo lleno de relleno.
            TextInput::make('ins_razon_social')
                ->label('Sitio')
                ->maxLength(255)
                ->helperText('El lugar físico que agrupa varios locales: «Hospital Alfredo Paulson». Dejar vacío si el local es un sitio por sí solo'),

            TextInput::make('ins_direccion')
                ->label('Dirección')
                ->required()
                ->maxLength(255),

            Select::make('ins_cliente_id')
                ->label('Cliente')
                ->relationship('cliente', 'org_descripcion')
                ->searchable()
                ->preload()
                ->helperText('Un cliente es uno solo aunque opere en varios países'),

            Select::make('ins_cd_id')
                ->label('Ciudad')
                ->relationship('ciudad', 'cd_nombre')
                ->searchable()
                ->preload()
                // De la ciudad se sube a provincia y país: es lo que define el
                // alcance del Líder Operativo y los cortes de reportería.
                ->getOptionLabelFromRecordUsing(fn ($record) => sprintf(
                    '%s — %s, %s',
                    $record->cd_nombre,
                    optional($record->provincia)->pr_nombre ?? 's/provincia',
                    optional(optional($record->provincia)->pais)->pa_nombre ?? 's/país'
                ))
                ->helperText('Si falta la ciudad, se crea en Ubicación Geográfica'),

            // `ins_ciudad` (texto libre) ya NO esta en el formulario: se veian
            // dos campos «Ciudad» seguidos y habia que leer la letra chica para
            // saber cual era el que contaba. La columna se conserva -- la
            // rellena el ETL y la devuelve la PortalApi -- pero el unico dato
            // que se edita y que define el alcance del Lider Operativo y los
            // cortes de reporteria es el selector de arriba, que sale del
            // catalogo de ciudades. Los 137 locales tienen los dos llenos.

            TextInput::make('ins_telefono')
                ->label('Teléfono')
                ->required()
                ->maxLength(20),

            TextInput::make('ins_email')
                ->label('Email')
                ->required()
                ->maxLength(255)
                ->email(),

            TextInput::make('ins_tipo')
                ->label('Tipo')
                ->required()
                ->maxLength(100),

            Toggle::make('ins_estado')
                ->label('Activo')
                ->required()
                ->default(true),

        ]);
    }

    public static function table(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('ins_code')->size('sm')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ins_descripcion')->size('sm')
                    ->label('Local')
                    ->searchable(),
                TextColumn::make('cliente.org_descripcion')->size('sm')
                    ->label('Cliente')->sortable()->searchable()->toggleable(),
                TextColumn::make('ciudad.provincia.pais.pa_nombre')->size('sm')
                    ->label('País')->sortable()->toggleable(),
                TextColumn::make('ciudad.cd_nombre')->size('sm')
                    ->label('Ciudad')->sortable()->toggleable(),
                TextColumn::make('ins_direccion')->size('sm')
                    ->label('Dirección')
                    ->searchable(false)
                    ->toggleable(),
                TextColumn::make('ins_email')->size('sm')
                    ->label('Email')
                    ->searchable(false)
                    ->toggleable(),
                BooleanColumn::make('ins_estado')
                    ->label('Estado')
                    ->sortable()
                    ->toggleable()
                    ->searchable(false),

            ])
            ->filters([
                // Abre mostrando solo los activos. Ver App\Filament\Tables\FiltroDeEstado.
                FiltroDeEstado::make('ins_estado', true, 'Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),
                Action::make('marcadores')
                ->label('Marcadores')
                ->icon('heroicon-o-map')
                // Quien puede editar va a la edicion; el Supervisor a la vista de
                // solo lectura, que muestra los mismos marcadores sin permitir
                // modificarlos.
                ->url(fn ($record) => PerfilPanel::puedeAdministrarLocales()
                    ? route('filament.resources.organizacion-institucions.edit', $record)
                    : route('filament.resources.organizacion-institucions.view', $record))
                ->color('primary')
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array {
        return [
            InstitucionMarcadoresRelationManager::class
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListOrganizacionInstitucions::route('/'),
            'create' => Pages\CreateOrganizacionInstitucion::route('/create'),
            'view' => Pages\ViewOrganizacionInstitucion::route('/{record}'),
            'edit' => Pages\EditOrganizacionInstitucion::route('/{record}/edit'),
            //'marcadores' => Pages\MarcadoresPage::route('/{record}/marcadores'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    /**
     * Crear y editar locales es del Administrador y del Lider Operativo.
     * El Supervisor los ve pero no los modifica: observa la operacion, no la
     * define. Se aplica aqui y no solo ocultando botones, para que tampoco
     * pueda entrar por URL.
     */
    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return PerfilPanel::puedeOperar();
    }

    public static function canCreate(): bool
    {
        return PerfilPanel::puedeAdministrarLocales();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return PerfilPanel::puedeAdministrarLocales();
    }

    protected static function shouldRegisterNavigation(): bool {
        return PerfilPanel::puedeOperar();
    }

    /**
     * Bloquea la RUTA, no solo el menu.
     *
     * shouldRegisterNavigation() solo oculta el item del menu lateral: quien
     * escribiera la URL a mano entraba igual. Filament aborta con 403 cuando
     * canViewAny() es false (Pages\Page::authorizeResourceAccess).
     */
    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    public static function getEloquentQuery(): Builder {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
        if(PerfilPanel::alcanceEsPorInstitucion()){
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('ins_code', $institucionesCodes);
        }

        // El Lider Operativo ve los locales de su(s) pais(es). Sin paises
        // asignados no ve nada: un lider mal configurado no debe terminar
        // con acceso global.
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('ins_code', $localesDelPais);
        }
        return $query;
    }

}
