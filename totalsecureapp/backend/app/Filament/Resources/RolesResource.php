<?php

namespace App\Filament\Resources;

use Filament\Actions;
use App\Filament\Resources\RolesResource\Pages;
use App\Filament\Resources\RolesResource\RelationManagers;
use App\Support\PerfilPanel;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Modules\Acceso\Models\Permission;
use Modules\Acceso\Models\permission_section;
use Modules\Acceso\Models\roles;
use Modules\Acceso\Models\users;
use Filament\Forms;
use Filament\Schemas\Schema;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * Los perfiles que `PerfilPanel` reconoce por su NOMBRE literal.
     *
     * Renombrar uno de estos deja fuera del panel a todos sus usuarios: las
     * comprobaciones son `in_array(self::actual(), self::OPERACION, true)` sobre
     * cadenas fijas. El sintoma seria «no puedo entrar» sin ningun error, asi que
     * el campo se bloquea en vez de avisar.
     */
    private const NOMBRES_QUE_USA_EL_CODIGO = [
        PerfilPanel::ADMINISTRADOR,
        PerfilPanel::ADMINISTRADOR_GENERAL,
        PerfilPanel::LIDER_OPERATIVO,
        PerfilPanel::SUPERVISOR,
        PerfilPanel::CONSOLA,
    ];

    /**
     * ⚠️ Esto era `return $schema->schema([ ]);` -- un formulario **vacio**.
     *
     * La pantalla se llama «Perfiles y permisos», tiene su boton de editar y su
     * pagina, y al abrirla no habia nada: ni un campo. Los 111 vinculos de
     * `role_has_permissions` solo se podian tocar por SQL. Es el mismo descuido
     * que ya tenia `NovedadResource` y que se corrigio el 2026-09-15; a este se
     * le paso.
     *
     * Dos cosas que conviene saber antes de tocar esta pantalla:
     *
     *  1. **Los permisos de aca NO abren ni cierran el panel.** `PerfilPanel`
     *     decide por nombre de perfil. Lo que se edita son los permisos
     *     granulares que consume la **app movil** (`PermisosApiService` y el
     *     middleware `permission.api`), mas el Portal Cliente.
     *  2. **El nombre si lo decide todo**, y por eso esta bloqueado en los cinco
     *     perfiles que el codigo conoce. Ver NOMBRES_QUE_USA_EL_CODIGO.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Perfil')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(125)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?roles $record): bool => self::nombreProtegido($record))
                            /*
                             * ⚠️ `dehydrated(false)` cuando esta protegido, y no
                             * solo `disabled()`.
                             *
                             * `disabled()` apaga el campo en la pantalla, pero el
                             * estado de Livewire sigue siendo escribible desde
                             * fuera. Sin esto, quien arme la peticion a mano
                             * renombra «Administrador» igual -- y eso deja fuera
                             * del panel a todos sus usuarios. Es el mismo error
                             * que el `Hidden` de marcadores: oculto no es
                             * inaccesible. Lo cazo un test.
                             */
                            ->dehydrated(fn (?roles $record): bool => ! self::nombreProtegido($record))
                            ->helperText(fn (?roles $record): ?string => self::nombreProtegido($record)
                                ? 'Bloqueado: el código reconoce este perfil por su nombre. '
                                    . 'Cambiarlo dejaría fuera del panel a todos sus usuarios.'
                                : null),

                        Textarea::make('descripcion')
                            ->label('Descripción')
                            ->rows(2)
                            ->maxLength(255)
                            ->helperText('Para qué sirve este perfil. Es lo que se lee en el listado.'),

                        Toggle::make('estado')
                            ->label('Activo')
                            ->default(true),

                        Toggle::make('visible')
                            ->label('Visible al elegir perfil')
                            ->default(true)
                            ->helperText('Si se apaga, el perfil sigue funcionando pero no aparece '
                                . 'en la pantalla de selección posterior al login.'),
                    ])
                    ->columns(2),

                Section::make('Permisos')
                    ->description('Lo que este perfil puede hacer en la aplicación móvil y en el '
                        . 'Portal Cliente. No afecta el acceso al panel web, que se decide por perfil.')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->hiddenLabel()
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'pr_descripcion',
                                // Solo los vigentes, y en el orden en que se
                                // agruparon: la etiqueta lleva la seccion
                                // delante, asi que el orden ES el agrupamiento.
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('pr_state', 1)
                                    ->orderBy('ps_codigo')
                                    ->orderBy('pr_posicion'),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Permission $record): string => self::seccionDe($record->ps_codigo)
                                    . ' · ' . $record->pr_descripcion,
                            )
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(2)
                            ->noSearchResultsMessage('Ningún permiso coincide.'),
                    ]),
            ]);
    }

    /** ¿Es uno de los perfiles que `PerfilPanel` compara por cadena literal? */
    private static function nombreProtegido(?roles $record): bool
    {
        return $record !== null
            && in_array($record->name, self::NOMBRES_QUE_USA_EL_CODIGO, true);
    }

    /**
     * Nombre de la seccion, cacheado.
     *
     * Son 48 permisos en la lista y 13 secciones: sin cachear, dibujar el
     * formulario hacia 48 consultas para resolver 13 nombres.
     */
    private static function seccionDe(?int $psCodigo): string
    {
        static $secciones = null;

        if ($secciones === null) {
            $secciones = permission_section::query()->pluck('ps_nombre', 'ps_codigo')->all();
        }

        return $secciones[$psCodigo] ?? 'Sin sección';
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
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Descarga::enLote('roles'),
                Actions\DeleteBulkAction::make(),
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
