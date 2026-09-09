<?php

namespace App\Filament\Resources;

use Filament\Actions;
use App\Filament\Tables\FiltroDeEstado;

use App\Support\PerfilPanel;
use App\helpers;
use Illuminate\Support\Facades\Hash;

use App\Filament\Resources\UsersResource\Pages;
use App\Filament\Resources\UsersResource\RelationManagers;
use Modules\Acceso\Models\users;

use Filament\Schemas\Schema;
use App\Filament\Tables\Descarga;
use Filament\Resources\Resource;
use Filament\Tables\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\BadgeColumn;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Session;

use Illuminate\Database\Eloquent\Model;

class UsersResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Configuración';
    }
    protected static ?int $navigationSort = 1;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'usuario';
    protected static ?string $pluralModelLabel = 'usuarios';
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $model = users::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user';

    public static function form(Schema $schema): Schema {
        return $schema
            ->schema([
                TextInput::make('usu_cedula')
                    ->label('Cédula')
                    ->required()
                    ->unique(table: static::$model, column: 'usu_cedula', ignoreRecord: true),
                TextInput::make('usu_tipdoc')
                    ->label('Tipo de Documento')
                    ->required(),
                // ⚠️ **Dos campos, no cinco.** Este formulario pedia el nombre
                // CINCO veces y todas obligatorias -- Nombre Completo, Primer y
                // Segundo Apellido, Primer y Segundo Nombre --, mientras que
                // `usuario:crear` y la carga masiva pedian solo estos dos y
                // derivaban el resto. Ademas de la molestia, nada impedia que el
                // nombre completo dijera una cosa y las piezas otra, y el nombre
                // completo es el que se lee en 52 lugares del codigo.
                //
                // No son columnas: se componen y descomponen en las paginas de
                // alta y edicion con `App\Support\NombreDePersona`. De ahi el
                // `dehydrated(false)`, sin el cual Filament intentaria guardar
                // una columna `nombres` que no existe.
                // ⚠️ **NO lleva `dehydrated(false)`.** Parece lo correcto para un
                // campo que no es columna, pero hace que Filament lo saque de
                // `$data` ANTES de `mutateFormDataBeforeCreate`, asi que la
                // pagina recibia los dos vacios y guardaba el nombre en blanco.
                // El registro se creaba igual, sin error, y solo se notaba al
                // mirar el listado. Se dejan hidratados y las paginas los
                // consumen y los quitan.
                TextInput::make('apellidos')
                    ->label('Apellidos')
                    ->required()
                    ->maxLength(120)
                    ->helperText('Los dos apellidos, separados por un espacio.'),
                TextInput::make('nombres')
                    ->label('Nombres')
                    ->required()
                    ->maxLength(120),
                TextInput::make('usu_email')
                    ->label('Correo Electrónico')
                    // ⚠️ Era obligatorio, y **505 de los 880 usuarios no tienen
                    // correo**: la mayoria de los guardias no usa uno. Exigirlo
                    // obligaba a inventar direcciones falsas, que es peor que no
                    // tener ninguna -- una direccion inventada rompe el
                    // restablecimiento de clave sin que nadie se entere.
                    ->email()
                    // La columna es NOT NULL y los 505 sin correo estan guardados
                    // como cadena vacia, no como null. Por eso la unicidad se
                    // comprueba a mano: con `->unique()` de Filament, el segundo
                    // usuario sin correo chocaria contra los 505 vacios que ya
                    // hay. No hay indice unico en la base, asi que el vacio
                    // repetido no rompe nada.
                    ->rule(function (?Model $record) {
                        return function (string $attribute, $value, \Closure $fail) use ($record) {
                            if (blank($value)) {
                                return;
                            }

                            $existe = static::$model::where('usu_email', $value)
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($existe) {
                                $fail('Ese correo ya está registrado para otro usuario.');
                            }
                        };
                    }),
                Toggle::make('usu_state')
                    ->label('Estado')
                    ->required()
                    ->default(true),

                // ⚠️ **El rol y los locales van en el alta, y antes no estaban.**
                // Un usuario necesita cuatro piezas para poder entrar: la fila
                // en `users`, el rol, una gestion abierta y el vinculo al local.
                // Este formulario creaba **solo la primera**, asi que quien se
                // daba de alta desde aca no podia iniciar sesion -- el login
                // responde «El usuario no tiene una gestion activa» -- y la app
                // no le dejaba registrar nada. Las otras tres las arma
                // `CreateUsers::afterCreate()`.
                //
                // En edicion no se muestran: cambiar el rol o los locales de
                // alguien que ya existe se hace desde «Perfil por usuario» y
                // «Locales por usuario», que llevan su propio historial.
                Select::make('rol')
                    ->label('Perfil')
                    ->options(fn () => \Illuminate\Support\Facades\DB::table('roles')
                        ->where('estado', 1)
                        ->orderBy('name')
                        ->pluck('name', 'name'))
                    ->required()
                    ->default('Vigilante')
                    ->dehydrated(false)
                    ->visibleOn('create')
                    ->helperText('Define qué módulos ve en la app y en el panel'),

                Select::make('locales')
                    ->label('Locales')
                    ->multiple()
                    ->options(fn () => \Illuminate\Support\Facades\DB::table('organizacion_institucion')
                        ->where('ins_estado', true)
                        ->orderBy('ins_descripcion')
                        ->pluck('ins_descripcion', 'ins_code'))
                    ->searchable()
                    ->dehydrated(false)
                    ->visibleOn('create')
                    ->helperText('Sin al menos uno, la app no le permite registrar rondas, accesos ni marcajes'),

                TextInput::make('usu_whatsapp')
                    ->label('WhatsApp')
                    ->tel()
                    ->maxLength(20)
                    // Un número mal formado no da error: el gateway lo acepta y
                    // el mensaje nunca llega. Por eso se pide con código de país.
                    ->helperText('Con código de país: 593987654321. También acepta 0987654321.'),

                Toggle::make('usu_acepta_whatsapp')
                    ->label('Autoriza avisos por WhatsApp')
                    // Aparte de "quiero turnos extra": aceptar trabajar de más no
                    // es aceptar que le escriban al teléfono personal.
                    ->helperText('Debe pedírsele expresamente. Sin esto no se le escribe.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->size('sm')
                    ->label('Código')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usu_cedula')->size('sm')
                    ->label('Cédula')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usu_nmbcom')->size('sm')
                    ->label('Nombres')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usu_email')->size('sm')
                    ->label('Correo')
                    ->toggleable()
                    ->searchable(),
                BooleanColumn::make('usu_state')
                    ->label('Estado')
                    ->sortable()
                    ->toggleable()
                    ->searchable(false),
                TextColumn::make('usu_whatsapp')
                    ->label('WhatsApp')
                    ->toggleable()
                    ->searchable()
                    // Sin número no hay a dónde mandarle el aviso, y eso hay que
                    // poder verlo de un vistazo al armar la lista de cobertura.
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? ($record->usu_acepta_whatsapp ? $state : $state . ' (sin autorizar)')
                        : '—'),
            ])
            ->filters([
                // Abre mostrando solo los activos. Ver App\Filament\Tables\FiltroDeEstado.
                FiltroDeEstado::make('usu_state', 1, 'Estado'),
            ])
            ->actions([
                Actions\EditAction::make(),

                /*
                 * Una renuncia no es la falta de un día.
                 *
                 * Sin esto, el cuadrante seguiría mostrando al guardia asignado
                 * semanas enteras y cada mañana alguien descubriría el puesto
                 * vacío otra vez, uno por uno. Acá se libera todo de una vez.
                 */
                /**
                 * Cambiar la contraseña de un usuario.
                 *
                 * **Existe porque no habia forma de hacerlo.** El unico camino
                 * web era «Olvido su contraseña» en el login, que **manda un
                 * correo**; y 505 de los 878 usuarios no tienen correo cargado,
                 * asi que para ellos ese flujo no existe. La alternativa era
                 * entrar por linea de comandos al servidor.
                 *
                 * No afecta a la app movil: las dos leen el mismo hash de
                 * `users.usu_password`, asi que la clave nueva sirve en la
                 * tablet sin recompilar nada.
                 */
                Actions\Action::make('cambiarPassword')
                    ->label('Cambiar contraseña')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->modalHeading(fn (users $record) => 'Nueva contraseña para ' . $record->usu_nmbcom)
                    ->modalDescription('El usuario entra con su cédula y esta contraseña, tanto en el panel como en la app de la tablet.')
                    ->modalSubmitActionLabel('Cambiar')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña nueva')
                            ->password()
                            ->required()
                            ->minLength(8)
                            // Mismas reglas que el flujo de la app
                            // (MobileApp\LoginController::procesar_paswchg): si
                            // aqui se permitiera algo mas debil, el usuario
                            // quedaria con una clave que su propia app rechazaria
                            // al intentar cambiarla.
                            ->rule('regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/')
                            ->validationAttribute('contraseña')
                            ->helperText('Mínimo 8 caracteres, con una mayúscula, una minúscula y un número.')
                            ->autocomplete('new-password'),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Repetir contraseña')
                            ->password()
                            ->required()
                            ->same('password')
                            ->autocomplete('new-password'),
                    ])
                    ->visible(fn () => PerfilPanel::puedeGestionarPersonal())
                    ->action(function (users $record, array $data) {
                        // Hasheo explicito: este modelo (Modules\Acceso\Models\users)
                        // **no** tiene el mutador que si tiene el de MobileApp --
                        // lo tiene comentado, y encima forzaba '123456'. Guardar
                        // el texto plano dejaria al usuario sin poder entrar y la
                        // clave legible en la base.
                        $record->usu_password = Hash::make($data['password']);

                        // Se invalida el token de recuperacion pendiente: si habia
                        // un enlace de «olvide mi contraseña» sin usar, deja de
                        // servir. Es lo mismo que hace el flujo de la app.
                        $record->remember_token = null;
                        $record->save();

                        helpers::control_log_filament(
                            ['user_id' => $record->id, 'usu_cedula' => $record->usu_cedula],
                            'UsersResource',
                            'CambiarPassword',
                            'NOTICE',
                            'Cambio de contraseña desde el panel'
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Contraseña actualizada')
                            ->body('Avísale a ' . $record->usu_nmbcom . ' cuál es. No queda registrada en ninguna parte.')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('darDeBaja')
                    ->label('Registrar baja')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->modalHeading('Registrar la baja del guardia')
                    ->modalDescription('Se cierran sus asignaciones del cuadrante y se abre una vacante por cada turno futuro que tenía programado.')
                    ->form([
                        Select::make('motivo')
                            ->label('Motivo')
                            ->options([
                                'Renuncia'        => 'Renuncia',
                                'Desvinculación'  => 'Desvinculación',
                                'Traslado'        => 'Traslado a otro local',
                                'Otro'            => 'Otro',
                            ])
                            ->default('Renuncia')
                            ->required(),
                        Forms\Components\DatePicker::make('desde')
                            ->label('A partir de')
                            ->default(now())
                            ->required()
                            ->helperText('Sus turnos desde esta fecha quedan sin cubrir'),
                        Forms\Components\Textarea::make('observacion')
                            ->label('Observación')
                            ->rows(2)
                            ->columnSpan(2),
                        Toggle::make('desactivar')
                            ->label('Desactivar el usuario')
                            ->default(true)
                            ->helperText('Deja de poder entrar a la app. Su historial se conserva.'),
                    ])
                    ->visible(fn () => PerfilPanel::puedeGestionarPersonal())
                    ->action(function (users $record, array $data) {
                        $observacion = trim($data['motivo'] . '. ' . ($data['observacion'] ?? ''));

                        $r = app(\App\Services\VacanteService::class)->darDeBaja(
                            (int) $record->id,
                            \Carbon\Carbon::parse($data['desde']),
                            $observacion,
                            Session::get('usuID')
                        );

                        if (!empty($data['desactivar'])) {
                            $record->usu_state = 0;
                            $record->save();
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Baja registrada')
                            ->body(sprintf(
                                '%d turnos liberados y %d vacantes abiertas. %d asignaciones del cuadrante quedaron cerradas.',
                                $r['turnos'],
                                $r['vacantes'],
                                $r['asignaciones']
                            ))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Descarga::enLote('users'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUsers::route('/create'),
            'edit' => Pages\EditUsers::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function shouldRegisterNavigation(): bool {
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

}
