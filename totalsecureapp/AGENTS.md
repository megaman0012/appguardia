# AGENTS.md — Total Secure App

## Estructura del proyecto

- `backend/` — API Laravel 8.75 (modular) que consume la app. Es el backend `coredt360` migrado, con historia git local completa. Módulo principal de la app: `Modules/MobileApp`.
- `src/` — Código de la app Expo (SDK 57).
- `android/` — Proyecto nativo Android generado con `expo run:android` (se versiona para que el APK se pueda compilar en otro servidor sin `expo prebuild`).
- `apk_extracted/` — APK original descompilada (referencia). `appdeguardias.apk` es el APK original.
- `HISTORIAL_DE_CHAT.md` — Resumen del trabajo previo (en la carpeta padre).

## Backend

- **Importante:** el repositorio es 100% local. No hay `remote origin` configurado y NO debe agregarse uno sin autorización del usuario. No hacer fetch/push/pull.
- **Ejecución con Docker (recomendado).** Todo el stack (nginx + PHP-FPM 8.3 + PostgreSQL 16) corre con Docker Compose desde `backend/`:
  - `docker compose up -d` — levanta todo. Backend en http://localhost:3031, Postgres en `127.0.0.1:5434` (host) / `db:5432` (red docker).
  - `docker compose exec backend php artisan migrate` — comandos de Laravel dentro del contenedor.
  - `docker compose logs -f` — logs; `docker compose down` — detiene sin borrar datos (volumen `pgdata`).
  - Las credenciales de BD se toman del `.env` (variables `DB_*`). `DB_HOST` se sobrescribe a `db` dentro de los contenedores.
- `.env` está en `.gitignore` (contiene credenciales SMTP reales, no versionar). `composer.phar` también ignorado. `.env.example` está actualizado para Postgres sin secretos.
- Autenticación API: Sanctum (bearer token). Las rutas de la app están en `backend/Modules/MobileApp/Routes/api.php` (`POST api/login`, `api/instituciones`, `api/rondas`, …). El prefijo real es `api/` (el `i/` del APK original era un alias del proxy).
- Middleware CORS/seguridad personalizados (`App\Http\Middleware\HandleCors`, `SecurityHeaders`, `App\Services\CorsService`) se corrigieron para funcionar en PHP 8.3.
- **BD migrada y sembrada.** `php artisan migrate` crea todo el esquema (incluye tablas de negocio de la app). `php artisan db:seed` crea:
  - Usuario demo: cédula `1234567890` / contraseña `123456` (rol Vigilante, con gestión activa).
  - Institución demo con 2 marcadores QR y un checklist de inventario con 2 productos.
  - Parámetro `access` (login) y roles `Supervisor`/`Vigilante`.
- **Correcciones hechas a migraciones/modelos heredados:** tabla `user_has_gestions` (antes `users_gestions`, columnas `ug_finish`/auditoría añadidas), columnas `tokenable_gs`/`refresh_token`/`expires_at` en `personal_access_tokens`, migración de permisos ya no fuerza `mysql`, `config/auth.php` tiene provider `mobile_users` (el `sanctum` guard valida contra `Modules\MobileApp\Models\users`), y el modelo `users` ya no fuerza la contraseña a `123456` (solo la hashea al cambiarla).
## Interfaz Web (panel)

El backend trae dos capas web sobre el mismo dominio `http://localhost:3031`:

- **Panel legacy (`/acceso/login`)** — login por cédula (web `login_check` → selección de perfil → menú). Usa las vistas AdminLTE de `Modules/{Acceso,Administracion,Formularios}`.
  - Usuario demo: cédula `1234567890` / `123456` (tiene perfiles `Vigilante` y `Supervisor`).
  - El menú se arma desde `role_has_permissions → permissions → permission_section` (seeder `seedWebAccess`).
  - Páginas operativas: `administracion/persona.index` (personas + datatable), `formularios/epicrisis.index`, `formularios/referencia.index` (catálogos `tipo_documento`, `tipo_genero`, `tipo_especialidad`, `tipo_servicio`, `referencia_motivo` sembrados).
- **Panel Filament (`/admin`)** — el panel de administración real del negocio de la app (Rondas, Accesos, Novedades, Alertas, Inventario, Bitácora, Usuarios, Perfiles, Instituciones, Sedes, etc.). Está protegido por `canAccessFilament()` (requiere perfil `Supervisor`/`Administrador`), por lo que se entra **después** de hacer el login web y seleccionar el perfil Supervisor. El login propio de Filament apunta a `usu_email` (`App\Http\Livewire\Auth\Login`) y además la ruta `/admin/login` redirige a `/acceso/login` (diseño intencional).

Fixes aplicados a la web (commit `d42858a`):
- `Html::style/script` fueron eliminados en spatie/laravel-html v3 → reemplazados por `<link>`/`<script>` en las vistas Blade.
- Se creó `storage/framework/sessions` (faltaba y rompía sesiones en Postgres/Docker).
- Migraciones nuevas: `visible` en roles, catálogos web, `log`/`log_trafico`, `organizacion`/`sede`/`organizacion_sede`, `ru_code` en `user_has_roles`.
- `orderBy` de `pr_posicion` corregido para Postgres (`REPLACE` no aplica a `double`).

## Flujo de cambio de contraseña (`Modules/MobileApp`):
  - `POST api/solicitud_paswchg` (`usu_cedula`) → envía correo con el link de cambio y además devuelve `user_id` y `token` para que la app continúe el flujo sin depender del correo.
  - `POST api/procesar_paswchg` (`user_id`, `password`, `password2`) → valida (mínimo 8 caracteres, mayúscula/minúscula/número, coincidencia, distinta a la anterior) y guarda el hash nuevo. Limpia `remember_token`.
  - El `LoginController` de MobileApp **no** debe usar `message_json()` como función global (no existe); siempre `$this->message_json()`.

## App Expo

Expo SDK 57. Leer docs versionadas en https://docs.expo.dev/versions/v57.0.0/ antes de escribir código.

- **Conectada al backend real.** `src/services/api.ts` apunta a `API_URL` de `src/utils/constants.ts`: el host se toma en orden de prioridad de `Constants.expoConfig.extra.apiHost` (app.json, `192.168.100.212`), luego del `hostUri` de Expo, y como último recurso `localhost`. Puerto 3031 → funciona en web, emulador y dispositivo físico en la misma red.
- **APK release standalone:** `./gradlew :app:assembleRelease` genera `android/app/build/outputs/apk/release/app-release.apk` (~99 MB, firmado con el debug keystore, JS embebido → **no necesita Metro**). La IP del servidor se configura en `expo.extra.apiHost` de `app.json` (si cambia la IP, editarla y recompilar). `expo-build-properties` habilita `usesCleartextTraffic` (HTTP local). El APK debug (`app-debug.apk`) en cambio SÍ requiere Metro corriendo.
- **Flujo actual completo:**
  - Login (`POST api/login` con `usu_cedula`/`usu_password`) → guarda `access_token` + `usuario` en AsyncStorage (interceptor agrega `Authorization: Bearer`).
  - Selección de Institución → Home.
  - Home con los módulos **construidos y conectados**: Rondas (lista, detalle, escaneo QR), Accesos (lista + formulario), Novedades (lista + creación), Alertas, Inventario (lista + detalle/checklist), Biometría y Perfil.
  - Recuperación de contraseña: `PasswordResetRequestScreen` → `PasswordResetScreen` (formulario de nueva clave) → login.
  - Notificaciones push: `src/services/notifications.ts` registra el token push (`POST api/token/save`).
- Correcciones de esta etapa: `tsconfig.json` excluye `backend/` (vendor rompía `tsc`), `HomeScreen` usa el usuario del contexto, `ProfileSelectionScreen` quedó como referencia (usa endpoints inexistentes, no está en el navegador).
- **Versiones alineadas con SDK 57** vía `npx expo install` (`expo`, `react-native-safe-area-context`, `react-native-screens`).
- `android/` se versiona para portabilidad. Las salidas de build (`**/build/`, `.gradle`, `local.properties`) están ignoradas con `.gitignore` propio dentro de `android/`.

## Verificación de funcionamiento (2026-08-18)

Se verificó el estado completo del proyecto:

| Componente | Estado | Detalle |
|------------|--------|---------|
| Backend Docker | ✅ Funcionando | Contenedores `ts_app`, `ts_db`, `ts_nginx` activos en puerto 3031 |
| API REST | ✅ Funcionando | `POST /api/login` responde correctamente con JSON |
| Frontend Expo | ✅ Compila | TypeScript sin errores, bundle genera ~1MB |
| Web export | ✅ Funcionando | Export estático en `dist/`, servido en `0.0.0.0:8081` |
| Flujo Login → Home | ✅ Completo | Login → Selección institución → Home → Módulos |
| Pantallas módulos | ✅ Todas operativas | Rondas, Accesos, Novedades, Alertas, Inventario, Biometría, Perfil |

**Para acceder desde otra PC en la misma red:**
1. Levantar Expo: `npx expo start --web --port 8081` (o export estático: `npx expo export --platform web`)
2. Abrir `http://192.168.100.212:8081` desde el navegador

**Nota:** El error "React Native DevTools" al iniciar Expo es esperado en servidores Linux sin interfaz gráfica (Electron no tiene `--no-sandbox`). No afecta al funcionamiento de la app.

## Pendientes conocidos

- **Firebase/Google Play:** falta `google-services.json` para notificaciones push en dispositivos reales en producción (la app ya registra el token; sin Firebase no llega la notificación a teléfonos). Se debe evitar versionar el archivo con credenciales reales en el repo.
- **Cambio de contraseña por email:** la app puede completar el flujo porque la API devuelve el token; si se quiere estricto por correo, usar deep linking (`Linking` + scheme).
- **Compilar APK:** el APK **release standalone** ya se compiló (`android/app/build/outputs/apk/release/app-release.apk`, ~99 MB, firmado con debug keystore, `com.dt360.coreapp` v1.0.0, targetSdk 36). Para producción real: generar keystore propio y usar `npx expo prebuild`/EAS. El APK debug (`app-debug.apk`, ~190 MB) solo funciona con Metro levantado (`npx expo start`).
- **Páginas legacy (Persona/Epicrisis/Referencia):** provienen de la app original (`coredt360`/HagpAsist). Los catálogos están sembrados, pero los flujos de captura (`getbydoc`, `document`, guardado) apuntan a tablas de salud que no forman parte de este proyecto → fuera de alcance.
- **`ProfileSelectionScreen`** (referencia con endpoints inexistentes, no está en el navegador): decidir si se elimina.
