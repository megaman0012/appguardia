<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\MobileApp\Models\users;

/**
 * Crea un usuario real (Fase dominio).
 *
 * En una base de produccion nueva las migraciones dejan roles y permisos, pero
 * ningun usuario, asi que nadie puede entrar. Lo unico que creaba el primero era
 * DatabaseSeeder, con la cedula 1234567890 y una credencial que quedo publicada
 * en el repositorio: no sirve para produccion.
 *
 * Este comando arma las tres piezas que el login necesita y que es facil olvidar
 * por separado:
 *   1. la fila en users con usu_state = 1
 *   2. el rol en user_has_roles
 *   3. una gestion ABIERTA en user_has_gestions (ug_finish = 0); sin ella el
 *      login responde "El usuario no tiene una gestion activa"
 * y opcionalmente el vinculo a instituciones, sin el cual la app movil no puede
 * registrar nada.
 */
class CrearUsuario extends Command
{
    protected $signature = 'usuario:crear
                            {--cedula= : Cedula (usuario de acceso)}
                            {--nombres= : Nombres}
                            {--apellidos= : Apellidos}
                            {--email= : Correo}
                            {--rol=Supervisor : Rol a asignar}
                            {--instituciones= : Codigos de institucion separados por coma}
                            {--password= : Contraseña (si se omite, se pide sin mostrarla en pantalla)}';

    protected $description = 'Crea un usuario con su rol, gestion activa y vinculo a instituciones';

    public function handle(): int
    {
        $cedula    = $this->option('cedula')    ?: $this->ask('Cedula');
        $nombres   = $this->option('nombres')   ?: $this->ask('Nombres');
        $apellidos = $this->option('apellidos') ?: $this->ask('Apellidos');
        $email     = $this->option('email')     ?: $this->ask('Correo');
        $rol       = $this->option('rol');

        // secret() no muestra lo que se escribe ni queda en el historial del shell.
        $password = $this->option('password') ?: $this->secret('Contraseña');
        if (!$this->option('password')) {
            if ($password !== $this->secret('Repetir contraseña')) {
                $this->error('Las contraseñas no coinciden.');
                return self::FAILURE;
            }
        }

        $validator = Validator::make(compact('cedula', 'nombres', 'apellidos', 'email', 'password'), [
            'cedula'    => 'required|string|max:255',
            'nombres'   => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            // Mismas reglas que el cambio de contraseña de la app.
            'password'  => 'required|string|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/',
        ], [
            'password.min'   => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe incluir mayuscula, minuscula y numero.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $rolFila = DB::table('roles')->where('name', $rol)->first();
        if (!$rolFila) {
            $disponibles = DB::table('roles')->orderBy('name')->pluck('name')->implode(', ');
            $this->error("El rol '{$rol}' no existe. Disponibles: {$disponibles}");
            $this->line('Si la lista sale vacia, faltan las migraciones: php artisan migrate');
            return self::FAILURE;
        }

        if (users::where('usu_cedula', $cedula)->exists()) {
            $this->error("Ya existe un usuario con la cedula {$cedula}.");
            return self::FAILURE;
        }

        $instituciones = $this->resolverInstituciones();
        if ($instituciones === null) {
            return self::FAILURE;
        }

        $partesNombre    = preg_split('/\s+/', trim($nombres), -1, PREG_SPLIT_NO_EMPTY) ?: [$nombres];
        $partesApellido  = preg_split('/\s+/', trim($apellidos), -1, PREG_SPLIT_NO_EMPTY) ?: [$apellidos];

        $usuario = DB::transaction(function () use (
            $cedula, $nombres, $apellidos, $email, $password, $rolFila, $instituciones,
            $partesNombre, $partesApellido
        ) {
            // usu_password esta en $hidden y no en $fillable, asi que create()
            // lo descartaria en silencio: se asigna directo, que evita la
            // proteccion de asignacion masiva. El evento saving del modelo lo
            // hashea.
            $usuario = new users();
            $usuario->usu_cedula   = $cedula;
            $usuario->usu_tipdoc   = 'C';
            $usuario->usu_nmbcom   = trim("{$nombres} {$apellidos}");
            $usuario->usu_nmb1     = $partesNombre[0];
            $usuario->usu_nmb2     = $partesNombre[1] ?? $partesNombre[0];
            $usuario->usu_ape1     = $partesApellido[0];
            $usuario->usu_ape2     = $partesApellido[1] ?? $partesApellido[0];
            $usuario->usu_email    = $email;
            $usuario->usu_state    = 1;
            $usuario->usu_password = $password;
            $usuario->save();

            DB::table('user_has_roles')->updateOrInsert(
                ['user_id' => $usuario->id, 'role_id' => $rolFila->id],
                ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
            );

            // Gestion abierta: el login la exige (ug_finish = 0).
            DB::table('user_has_gestions')->updateOrInsert(
                ['ug_user_id' => $usuario->id, 'ug_finish' => false],
                [
                    'ug_ingreso'      => now(),
                    'ug_state'        => 1,
                    'ug_created_user' => $usuario->id,
                    'ug_created_at'   => now(),
                    'ug_updated_at'   => now(),
                ]
            );

            foreach ($instituciones as $insCode) {
                DB::table('user_has_institucion')->updateOrInsert(
                    ['ui_usu_id' => $usuario->id, 'ui_ins_code' => $insCode],
                    ['ui_state' => 1, 'ui_created_at' => now(), 'ui_updated_at' => now()]
                );
            }

            return $usuario;
        });

        $this->info("Usuario creado (id {$usuario->id}).");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Cedula', $cedula],
                ['Nombre', $usuario->usu_nmbcom],
                ['Correo', $email],
                ['Rol', $rolFila->name],
                ['Gestion', 'abierta'],
                ['Instituciones', $instituciones ? implode(', ', $instituciones) : 'ninguna'],
            ]
        );

        if (empty($instituciones)) {
            $this->warn('Sin instituciones vinculadas la app movil no puede registrar nada, y la API del portal responde 403.');
            $this->line('Se agregan luego con: php artisan usuario:crear --help (o desde el panel).');
        }

        return self::SUCCESS;
    }

    /**
     * @return int[]|null  null si algun codigo no existe
     */
    private function resolverInstituciones(): ?array
    {
        $opcion = $this->option('instituciones');

        if ($opcion === null) {
            $existentes = DB::table('organizacion_institucion')
                ->where('ins_estado', true)
                ->orderBy('ins_code')
                ->get(['ins_code', 'ins_descripcion']);

            if ($existentes->isEmpty()) {
                $this->warn('No hay instituciones activas todavia; el usuario se crea sin vinculos.');
                return [];
            }

            $this->line('Instituciones activas:');
            foreach ($existentes as $ins) {
                $this->line("  {$ins->ins_code}  {$ins->ins_descripcion}");
            }
            $opcion = (string) $this->ask('Codigos a vincular (separados por coma, vacio para ninguno)', '');
        }

        if (trim($opcion) === '') {
            return [];
        }

        $codigos = array_values(array_unique(array_map(
            'intval',
            array_filter(array_map('trim', explode(',', $opcion)), fn ($v) => $v !== '')
        )));

        foreach ($codigos as $codigo) {
            if (!DB::table('organizacion_institucion')->where('ins_code', $codigo)->exists()) {
                $this->error("La institucion {$codigo} no existe.");
                return null;
            }
        }

        return $codigos;
    }
}
