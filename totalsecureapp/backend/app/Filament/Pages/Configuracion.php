<?php

namespace App\Filament\Pages;

use App\Services\Avisos\EvolutionApi;
use App\Support\Ajustes;
use App\Support\PerfilPanel;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Correo saliente y WhatsApp, editables sin entrar al servidor.
 *
 * Antes esto vivia solo en el `.env`: cambiar el SMTP obligaba a entrar por
 * SSH, editar un archivo y reiniciar el contenedor. Y hasta hoy nunca se
 * cambio, asi que el sistema quedo con `MAIL_HOST=mailhog` y
 * `MAIL_FROM_ADDRESS` vacio -- **el correo no sale a ningun lado y restablecer
 * la contraseña no funciona para nadie**.
 *
 * **Solo Administrador.** No es una preferencia: quien edita esto puede
 * redirigir los correos de restablecimiento de clave de 880 personas a un
 * servidor propio.
 *
 * **Los botones de probar no son un adorno.** Sin ellos se guarda una
 * configuracion equivocada y nadie se entera hasta que alguien no puede
 * recuperar su clave, semanas despues y sin relacionarlo con este formulario.
 */
class Configuracion extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static \UnitEnum|string|null $navigationGroup  = 'Configuración';
    protected static ?string $navigationLabel                = 'Correo y WhatsApp';
    protected static ?string $title                          = 'Correo y WhatsApp';
    protected static ?int $navigationSort                     = 90;

    protected string $view = 'filament.pages.configuracion';

    /** @var array<string, mixed> */
    public array $datos = [];

    public static function canAccess(): bool
    {
        return PerfilPanel::es(PerfilPanel::ADMINISTRADOR, PerfilPanel::ADMINISTRADOR_GENERAL);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public function mount(): void
    {
        $guardados = Ajustes::todos();

        // Se muestra lo guardado y, si no hay nada, lo que hoy vale de verdad
        // (el `.env`). Asi el formulario abre reflejando la realidad y no en
        // blanco, que haria pensar que no hay nada configurado.
        $valores = [];

        foreach (Ajustes::defectos() as $clave => $defecto) {
            $valores[$this->aCampo($clave)] = $guardados[$clave] ?? $defecto;
        }

        $this->form->fill($valores);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('datos')
            ->components([
                Section::make('Correo saliente (SMTP)')
                    ->description('Con esto se envían los correos de restablecimiento de contraseña.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mail__host')
                            ->label('Servidor')
                            ->placeholder('smtp.gmail.com')
                            ->helperText('Hoy: ' . (config('mail.mailers.smtp.host') ?: '—')),
                        TextInput::make('mail__port')
                            ->label('Puerto')
                            ->numeric()
                            ->placeholder('587'),
                        TextInput::make('mail__username')
                            ->label('Usuario'),
                        TextInput::make('mail__password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            // ⚠️ Se guarda CIFRADA en la base, con `APP_KEY` --
                            // la misma que cifra los QR de las rondas.
                            ->helperText('Se guarda cifrada.'),
                        Select::make('mail__encryption')
                            ->label('Cifrado')
                            ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => 'Ninguno'])
                            ->native(false),
                        TextInput::make('mail__from_address')
                            ->label('Remitente')
                            ->email()
                            // Sin esto Laravel no puede armar el correo y falla
                            // con «Address in mailbox given [] does not comply»,
                            // que no le dice nada a nadie.
                            ->helperText('Obligatorio para que el correo salga.'),
                        TextInput::make('mail__from_name')
                            ->label('Nombre del remitente')
                            ->placeholder('Total Secure'),
                    ]),

                Section::make('WhatsApp (Evolution API)')
                    ->description('Canal principal para convocar cobertura de un puesto vacío.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('whatsapp__url')
                            ->label('URL del gateway')
                            ->url()
                            ->placeholder('http://evolution:8080'),
                        TextInput::make('whatsapp__instancia')
                            ->label('Instancia'),
                        TextInput::make('whatsapp__api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->helperText('Se guarda cifrada.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardar')
                ->label('Guardar')
                ->icon('heroicon-o-check')
                ->action('guardar'),

            Action::make('probarCorreo')
                ->label('Enviar correo de prueba')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->form([
                    TextInput::make('destino')
                        ->label('Enviar a')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()?->usu_email ?: null),
                ])
                // ⚠️ Prueba lo GUARDADO, no lo que hay en pantalla: guardar
                // primero y probar despues es lo que responde la pregunta real,
                // «¿va a funcionar el restablecimiento de clave?».
                ->action(fn (array $data) => $this->probarCorreo($data['destino'])),

            Action::make('probarWhatsapp')
                ->label('Probar WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->action('probarWhatsapp'),
        ];
    }

    public function guardar(): void
    {
        $datos = $this->form->getState();

        $valores = [];

        foreach (array_keys(Ajustes::defectos()) as $clave) {
            $valores[$clave] = $datos[$this->aCampo($clave)] ?? null;
        }

        Ajustes::guardar($valores, auth()->id());

        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->body('Ya está activa. Probá el envío antes de darla por buena.')
            ->send();
    }

    public function probarCorreo(string $destino): void
    {
        try {
            Mail::raw(
                "Si estás leyendo esto, el correo saliente de Total Secure App funciona.\n\n"
                . 'Servidor: ' . config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port'),
                fn ($m) => $m->to($destino)->subject('Prueba de correo — Total Secure App'),
            );

            Notification::make()
                ->success()
                ->title('Correo enviado')
                ->body("Salió hacia {$destino}. Revisá la bandeja, y también el correo no deseado.")
                ->send();
        } catch (\Throwable $e) {
            Log::warning('Falló la prueba de correo: ' . $e->getMessage());

            Notification::make()
                ->danger()
                ->title('No se pudo enviar')
                // El mensaje crudo del transporte es feo pero es el unico que
                // dice si fue la clave, el puerto o el certificado.
                ->body(str($e->getMessage())->limit(300))
                ->persistent()
                ->send();
        }
    }

    public function probarWhatsapp(): void
    {
        $estado = app(EvolutionApi::class)->estado();

        $ok = ($estado['estado'] ?? '') === 'conectado';

        Notification::make()
            ->status($ok ? 'success' : 'warning')
            ->title($ok ? 'WhatsApp conectado' : 'WhatsApp no está listo')
            ->body(trim(($estado['detalle'] ?? '') . ' ' . ($estado['numero'] ? "Número: {$estado['numero']}" : '')))
            ->persistent(!$ok)
            ->send();
    }

    /**
     * `mail.host` -> `mail__host`.
     *
     * Filament interpreta el punto como acceso anidado en el estado del
     * formulario, asi que un campo llamado `mail.host` crearia un array
     * `['mail' => ['host' => …]]` y no el valor plano que se guarda.
     */
    private function aCampo(string $clave): string
    {
        return str_replace('.', '__', $clave);
    }
}
