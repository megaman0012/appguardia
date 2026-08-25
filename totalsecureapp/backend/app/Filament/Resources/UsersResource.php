<?php

namespace App\Filament\Resources;

use App\Support\PerfilPanel;

use App\Filament\Resources\UsersResource\Pages;
use App\Filament\Resources\UsersResource\RelationManagers;
use Modules\Acceso\Models\users;

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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Session;

class UsersResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Configuracion Sistema';
    }
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $model = users::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function form(Form $form): Form {
        return $form
            ->schema([
                TextInput::make('usu_cedula')
                    ->label('Cedula')
                    ->required()
                    ->unique(table: static::$model, column: 'usu_cedula', ignoreRecord: true),
                TextInput::make('usu_tipdoc')
                    ->label('Tipo de Documento')
                    ->required(),
                TextInput::make('usu_nmbcom')
                    ->label('Nombre Completo')
                    ->required(),
                TextInput::make('usu_ape1')
                    ->label('Primer Apellido')
                    ->required(),
                TextInput::make('usu_ape2')
                    ->label('Segundo Apellido')
                    ->required(),
                TextInput::make('usu_nmb1')
                    ->label('Primer Nombre')
                    ->required(),
                TextInput::make('usu_nmb2')
                    ->label('Segundo Nombre')
                    ->required(),
                TextInput::make('usu_email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required()
                    ->unique(table: static::$model, column: 'usu_email', ignoreRecord: true),
                Toggle::make('usu_state')
                    ->label('Estado')
                    ->required()
                    ->default(true),

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
                    ->label('Codigo')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usu_cedula')->size('sm')
                    ->label('Cedula')
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
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),

                /*
                 * Una renuncia no es la falta de un día.
                 *
                 * Sin esto, el cuadrante seguiría mostrando al guardia asignado
                 * semanas enteras y cada mañana alguien descubriría el puesto
                 * vacío otra vez, uno por uno. Acá se libera todo de una vez.
                 */
                Tables\Actions\Action::make('darDeBaja')
                    ->label('Registrar baja')
                    ->icon('heroicon-o-user-remove')
                    ->color('danger')
                    ->modalHeading('Registrar la baja del guardia')
                    ->modalSubheading('Se cierran sus asignaciones del cuadrante y se abre una vacante por cada turno futuro que tenía programado.')
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
            ->bulkActions([]);
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

}
