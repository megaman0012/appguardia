<?php

namespace App\Filament\Resources;

use App\Filament\Tables\FiltroDeEstado;

use App\Filament\Forms\SelectorDeUsuario;

use App\Support\PerfilPanel;

use App\Filament\Resources\UserHasInstitucionResource\Pages;
use App\Filament\Resources\UserHasInstitucionResource\RelationManagers;

use App\helpers;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Acceso\Models\users;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\UserHasInstitucion;
use Session;

class UserHasInstitucionResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Configuración';
    }
    protected static ?int $navigationSort = 3;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'asignación';
    protected static ?string $pluralModelLabel = 'asignaciones';
    protected static ?string $navigationLabel = 'Locales por usuario';
    protected static ?string $model = UserHasInstitucion::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.cliente', 'usuario'];
    protected static ?string $navigationIcon = 'heroicon-o-identification';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Busca por nombre Y por cedula. Ver App\Filament\Forms\SelectorDeUsuario:
                // antes filtraba el texto de la etiqueta en el navegador, asi que
                // buscar por cedula solo funcionaba donde la etiqueta la incluia.
                SelectorDeUsuario::make('ui_usu_id', 'Usuario')
                    ->disabledOn('edit')
                    ->required(),
                Select::make('ui_ins_code')
                    ->label('Local')
                    // Al crear se pueden elegir varios de una vez: asignar un
                    // guardia a cuatro locales exigia repetir el alta cuatro
                    // veces. Al editar sigue siendo uno, porque cada fila de
                    // user_has_institucion es un vinculo concreto.
                    ->multiple(fn (string $context) => $context === 'create')
                    ->options(
                        OrganizacionInstitucion::where('ins_estado', 1)
                            ->with('ciudad.provincia.pais')
                            ->orderBy('ins_descripcion')
                            ->get()
                            ->mapWithKeys(function ($item) {
                                // Se muestra la ubicacion porque hay locales con
                                // el mismo nombre en distintas ciudades.
                                $ciudad = optional($item->ciudad)->cd_nombre;
                                $pais = optional(optional(optional($item->ciudad)->provincia))->pais;
                                $ubic = $ciudad
                                    ? sprintf(' — %s, %s', $ciudad, optional($pais)->pa_nombre ?? 's/país')
                                    : ' — sin ciudad asignada';

                                return [$item->ins_code => $item->ins_descripcion . $ubic];
                            })
                    )
                    ->searchable()
                    ->disabledOn('edit')
                    ->required()
                    ->helperText(fn (string $context) => $context === 'create'
                        ? 'Se puede seleccionar más de un local: se crea un vínculo por cada uno'
                        : null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ui_code')->size('sm')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('institucion.cliente.org_descripcion')->size('sm')
                    ->label('Cliente')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Local')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_cedula')->size('sm')
                    ->label('Cédula')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ui_created_at')->size('sm')
                    ->label('Creacion')
                    ->toggleable()
                    ->searchable(),
                BooleanColumn::make('ui_state')
                    ->label('Estado')
                    ->sortable()
                    ->toggleable()
                    ->searchable(false),
            ])
            ->filters([
                // Abre mostrando solo los activos. Ver App\Filament\Tables\FiltroDeEstado.
                FiltroDeEstado::make('ui_state', 1, 'Estado'),
                //
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        helpers::control_log_filament($record->toArray(), 'UserHasInstitucionResource', 'Delete','NOTICE', 'Eliminar User Has Institucion');
                    }),
            ])
            ->bulkActions([]);
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
            'index' => Pages\ListUserHasInstitucions::route('/'),
            'create' => Pages\CreateUserHasInstitucion::route('/create'),
            'edit' => Pages\EditUserHasInstitucion::route('/{record}/edit'),
        ];
    }

    protected static function shouldRegisterNavigation(): bool {
        return PerfilPanel::puedeGestionarPersonal();
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
        return PerfilPanel::puedeGestionarPersonal();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
    }
}
