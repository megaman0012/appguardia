# Historial de Chat - Proyecto APP DE GUARDIA

## Resumen del Análisis Inicial

Se analizó el proyecto ubicado en: `C:\Users\siste\OneDrive\Documentos\360\PROYECTOS TPG\APP DE GUARDIA`

### Estructura del Proyecto

- **Backend**: Aplicación Laravel 8.75 con estructura modular
- **Módulos principales**:
  - Acceso (autenticación, login, perfiles)
  - Administracion
  - Formularios
  - MobileApp
- **APK existente**: `appdeguardias.apk` (106.83 MB) - sin código fuente disponible
- **APK extraída**: carpeta `apk_extracted` con recursos y código compilado

### Tecnologías Detectadas

#### Backend (Laravel)
- PHP ^8.1
- Laravel Framework 8.75
- Paquetes destacados:
  - filament/filament: ^2.17 (panel de administración)
  - livewire/livewire: ^2.12
  - spatie/laravel-permission: ^5.11 (roles y permisos)
  - barryvdh/laravel-dompdf: ^2.2 (generación de PDF)
  - yajra/laravel-datatables-oracle: ^9.21 (tablas interactivas)
  - chillerlan/php-qrcode: ^5.0 (generación de QR)
  - nwidart/laravel-modules: ^8.6 (estructura modular)
- Frontend: Laravel Mix con PostCSS
- Base de datos: MySQL (configurada por defecto), soporte para PostgreSQL, SQL Server, SQLite

#### Módulo "Acceso" (Autenticación)
- Controlador: `LoginController.php`
- Modelo: `users.php` (extiende Authenticatable, implementa FilamentUser and HasName)
- Vistas Blade:
  - Login: `resources/views/login/index.blade.php`
  - Selección de perfil: `resources/views/login/seleccionar_perfil.blade.php`
  - Cambio de contraseña: `resources/views/login/cambiar_password.blade.php`
  - Email de cambio de contraseña: `resources/views/mail/cambiar_password.blade.php`
- Rutas web:
  - `/acceso/login` (GET)
  - `/acceso/login_check` (POST)
  - `/acceso/seleccionar_perfil` (GET)
  - `/acceso/procesar_perfil` (POST)
  - `/acceso/logout` (GET)
  - `/acceso/solicitud_cambiopass` (POST)
  - `/acceso/cambiar_password/{numero}` (GET)
  - `/acceso/procesar_cambiopass` (POST)
- Funcionalidades:
  - Autenticación dual: contra tabla `tbl_usuario` en conexión `intranet` y contra modelo local `users`
  - Validación de estado de cuenta y asignación de gestión
  - Sistema de roles y permisos (Spatie)
  - Recuperación de contraseña por email con token temporal
  - Selección de perfil después del login

### Descubrimiento clave: La app original era React Native + Expo

Desde el análisis de `apk_extracted/assets/app.config`, se determinó que la aplicación móvil original estaba construida con:

- **Framework**: React Native + Expo SDK 54.0.0
- **Nombre de la app**: Total Secure App
- **Slug**: Dt360Core
- **Versión**: 1.0.0
- **Paquete Android**: `com.dt360.coreapp`
- **Permisos**: CAMERA, RECORD_AUDIO, ACCESS_FINE_LOCATION, ACCESS_COARSE_LOCATION
- **Funcionalidades inferidas**: escaneo de QR (ML Kit), GPS, notificaciones push (sonido `./assets/sounds/alerta.wav`), conexión al backend Laravel
- **Recursos**: `icon.png`, `splash.png`, `adaptive-icon.png`, `favicon.png`, `notificon.png`

> Nota: `apk_extracted` no contenía código fuente legible; el `app.config` fue la fuente clave para reconstruir la app.

## Reconstrucción de la App (Expo)

Se creó el proyecto `totalsecureapp/` (plantilla blank-typescript) dentro de `/home/server-gea/Documentos/appguardia/`.

### Avance acumulado

#### Etapa 1 — Base conectada al login real (commit `03843b8`)
- `src/services/api.ts` (axios → `API_URL` puerto 3031, interceptor Bearer)
- `src/context/AuthContext.tsx` (AsyncStorage: token/usuario)
- `src/screens/LoginScreen.tsx` y `PasswordResetRequestScreen.tsx`
- `src/navigation/AppNavigator.tsx` (stack navigator)
- `src/utils/constants.ts`, `AsyncStorageHelper.ts`

#### Etapa 2 — Módulos de la app conectados (commit `ce374bb`)
- Rondas: `RondaListScreen`, `RondaDetalleScreen`, `ScannerQRScreen`
- Accesos: `AccesoListScreen`, `AccesoFormScreen`
- Novedades: `NovedadListScreen`, `NovedadCreateScreen`
- Alertas: `AlertasScreen`
- Inventario: `InventarioScreen`, `InventarioDetalleScreen`
- Biometría: `BiometriaScreen`
- Perfil: `PerfilScreen`
- Selección de Institución: `SeleccionInstitucionScreen`
- `app.json` con paquete `com.dt360.coreapp`, permisos, plugins (cámara, ubicación, notificaciones)

#### Etapa 3 — Build nativo + notificaciones + password (en curso)
- APK de debug generado: `android/app/build/outputs/apk/debug/app-debug.apk`
- `src/services/notifications.ts` (registro de token push)
- Flujo de cambio de contraseña completado:
  - Backend: `POST api/solicitud_paswchg` devuelve `user_id` + `token`; nuevo `POST api/procesar_paswchg`
  - App: `PasswordResetScreen.tsx` (formulario de nueva clave)
- Versiones de SDK alineadas (`expo ~57.0.12`, `react-native-safe-area-context ~5.7.0`, `react-native-screens ~4.26.0`)
- Carpeta `android/` versionada para compilar el APK en otro servidor

### Estado Actual (última actualización: 12 de agosto de 2026)

- ✅ Backend Laravel dockerizado (nginx:3031 + php-fpm 8.3 + postgres 16) migrado y sembrado
- ✅ Todos los endpoints `POST api/*` probados con token real
- ✅ App Expo SDK 57 con login real y todos los módulos construidos y conectados
- ✅ `npx tsc --noEmit` sin errores
- ✅ Flujo completo de cambio de contraseña (app + API)
- ✅ Notificaciones push: registro/remoción de token (commit `544e2f1`)
- ✅ Puerto del backend movido de **3020 → 3031** (evita conflicto con otro servicio) — `docker-compose.yml`, `.env`, `constants.ts`
- ✅ Backend levantado y verificado en `http://localhost:3031` (login + instituciones OK)
- ⚠️ Pendiente: `google-services.json` (Firebase) para push en producción
- ⚠️ Pendiente: decidir si `ProfileSelectionScreen` (referencia con endpoints inexistentes) se elimina
- ❌ Código fuente de la APK original no disponible (solo inferido del `app.config`)

### Sesión 12/08/2026 (tarde)

1. **Commit Etapa 3** (`544e2f1`): notificaciones push (`src/services/notifications.ts`, registro en `AuthContext`), endpoint `procesar_paswchg` en constants, plugins `expo-splash-screen`/`expo-notifications` en `app.json`, deps actualizadas (expo ~57.0.12), carpeta `android/` versionada (49 archivos nativos, builds ignorados).
2. **Cambio de puerto 3020 → 3031**: backend (`docker-compose.yml`, `.env`, `.env.example`, commit `a4dde82`) y app (`constants.ts`, commit `5d0ad9d`).
3. **Backend levantado** con `docker compose up -d --build` (daemon Docker iniciado primero) y validado: `POST /api/login` + `POST /api/instituciones` con token real en el puerto 3031.

### Sesión 12/08/2026 (noche) — Validación de la interfaz web + APK

1. **La interfaz web estaba rota** (login devolvía 500). Causas y fixes (commit backend `d42858a`):
   - `Html::style/script` no existen en spatie/laravel-html v3 → reemplazados por `<link>`/`<script>` en 9 vistas Blade.
   - Faltaba `storage/framework/sessions` (rompía sesiones) → creado con `.gitignore`.
   - `column "visible" does not exist` en `seleccionar_perfil` → migración `add_visible_to_roles`.
   - `REPLACE(pr_posicion, ...)` fallaba en Postgres (`double`) → `orderBy('pr_posicion')`.
   - Faltaban `log_trafico`, `log` (usados por `control_trafico`) → migración.
   - Faltaban tablas de catálogos (`tipo_documento`, `tipo_genero`, `tipo_especialidad`, `tipo_servicio`, `referencia_motivo`) que rompían las páginas Persona/Epicrisis/Referencia → migración + seed.
   - Faltaban `sede`, `organizacion`, `organizacion_sede` (relaciones de los resources Filament) → migración.
   - `user_has_roles` sin PK → se agregó `ru_code` serial (Filament `getTableRecordKey`).
2. **Seeder `seedWebAccess`**: secciones y permisos del menú web, asignados a roles `Supervisor`/`Vigilante`; usuario demo con ambos perfiles. Catálogos + persona demo sembrados.
3. **Filament**: login personalizado que usa `usu_email` (`app/Http/Livewire/Auth/Login`). Se accede tras login web + seleccionar perfil `Supervisor` (`canAccessFilament()`). Los **20 resources renderizan HTTP 200**.
4. **Validación final**: login web → perfil → `persona.index`/`epicrisis`/`referencia` 200 + datatable con datos; `/admin` 200; API móvil (`api/login`, `api/instituciones`) OK; log de Laravel sin errores.
5. **APK debug compilado en este servidor**: `./gradlew :app:assembleDebug` → `android/app/build/outputs/apk/debug/app-debug.apk` (~190 MB, `com.dt360.coreapp` 1.0.0, targetSdk 36, permisos cámara/ubicación/audio/notificaciones). Herramientas usadas: Java 21, Android SDK 35/36 + NDK 27.1, Gradle 9.3.1 (cacheado).
6. **Estado web**: panel legacy + Filament funcionales para el usuario demo. Quedan fuera de alcance los flujos de captura legacy de salud (tables de epicrisis/referencia).

### Sesión 12/08/2026 (noche 2) — APK release standalone para celular

1. **El APK debug no abría en el celular** porque un build debug carga su JS desde Metro, y en el teléfono `localhost:8081` no existe (se necesita `adb reverse` o el launcher de `expo-dev-client`).
2. **Error de `sudo npx expo start`**: el fallo de React Native DevTools es porque Electron no corre como root (crash inofensivo; Metro igual levantó). La solución es **no usar sudo** (el proyecto es de `server-gea`).
3. **APK release standalone compilado** (`commit 0a93373`): `./gradlew :app:assembleRelease` → `android/app/build/outputs/apk/release/app-release.apk` (**~99 MB, JS embebido, no necesita Metro**, firmado con debug keystore).
   - `expo-build-properties` + `usesCleartextTraffic=true` (Android bloquea HTTP en release) → aplicado en `AndroidManifest.xml`.
   - `app.json` → `expo.extra.apiHost = 192.168.100.212` (IP del servidor). `constants.ts` lee `extra.apiHost` → fallback `hostUri` → `localhost`.
   - **Conexión del APK:** la app llama a `http://192.168.100.212:3031/api` (nginx Docker del backend). Si la IP cambia, editar `app.json` y recompilar.

### Sesión 12/08/2026 (noche 3) — Credenciales y URLs del piloto

1. **Credenciales del usuario demo** (app y web usan el mismo): cédula **`1234567890`**, clave **`123456`**. Perfiles: `Vigilante` y `Supervisor`.
2. **URLs de acceso**:
   - Web en el servidor: `http://localhost:3031/acceso/login` (panel legacy) y `http://localhost:3031/admin` (Filament).
   - Desde el celular (misma WiFi): `http://192.168.100.212:3031/acceso/login` y `http://192.168.100.212:3031/admin`.
   - App: se conecta sola a `http://192.168.100.212:3031/api` (vía `extra.apiHost`).
3. **Nota:** Filament (`/admin`) no tiene login propio; se entra tras el login web en `/acceso/login` eligiendo perfil **Supervisor**.

### Sesión 12/08/2026 (noche 4) — Respaldo en GitHub (repositorio único `appguardia`)

Se creó un **monorepo** en la raíz `/home/server-gea/Documentos/appguardia/` que integra toda la historia de los dos repos git previos (app y backend) mediante `git subtree`, y se pusheó a `git@github.com:megaman0012/appguardia.git`.

- **Estructura del monorepo**: raíz con `totalsecureapp/` (app, con su historial completo) y `totalsecureapp/backend/` (backend Laravel, con su historial completo), más `HISTORIAL_DE_CHAT.md` y `apk_extracted/` (APK original descompilada).
- **Excluido del repo** (vía `.gitignore` de la raíz y de los sub-proyectos): `node_modules/`, `vendor/`, builds de Android (`android/app/build/`), `.env`, y los binarios `*.apk` (el `appdeguardias.apk` de 112 MB y los APKs compilados superan el límite de 100 MB por archivo de GitHub). El APK release queda guardado localmente.
- **No se subió** el `.env` (secretos); para desplegar en otro equipo hay que copiar `.env.example` y definir `DB_PASSWORD` y `APP_KEY`.

### Tareas pendientes (registradas el 12/08/2026)

1. **Firebase / Google Play**: falta `google-services.json` para que las notificaciones push lleguen a teléfonos reales en producción. La app ya registra el token (`src/services/notifications.ts`); sin Firebase el push no llega.
2. **Firma de publicación**: el APK release actual está firmado con el **debug keystore** (solo para pruebas). Para publicar en Google Play hace falta un keystore propio (`*.jks`) y `assembleRelease` firmado.
3. **`ProfileSelectionScreen`**: decidir si se elimina; usa endpoints que no existen en la API (`/api/profile` del flujo demo).
4. **Configuración de producción**: definir `DB_PASSWORD`, `APP_KEY`, `APP_URL` reales y credenciales del correo (`MailTrait`) en `.env` de producción. El actual es solo demo.
5. **Remote de GitHub**: este monorepo ya queda con remote en `git@github.com:megaman0012/appguardia.git`; los repos locales (app y backend) pueden seguir trabajándose por separado o directamente en el monorepo. Recordar que los `.env` y binarios grandes nunca se suben.
6. **Piloto**: probar el APK release (`app-release.apk`) en el celular en la misma red del servidor (`192.168.100.212`, backend en `:3031`). Si cambia la IP del servidor, editar `expo.extra.apiHost` en `app.json` y recompilar.

---
*Historial actualizado el: miércoles, 12 de agosto de 2026*
