# Contexto para quien lea este paquete

Sistema de gestión de guardias de seguridad (Total Secure App). En producción:
21 clientes, 137 locales, 878 usuarios, 45.319 fotos. Migrado desde una v1 en
MariaDB que sigue viva en otro servidor.

## Versiones, y por qué importan

- **Laravel 8.75** (sin soporte de seguridad desde enero 2023).
- **Filament 2.17** + Livewire 2.12. **No es Filament 3**: no existen
  `getTabs()` ni la clase `Tab`, `StatsOverviewWidget` no acepta `$heading`, y
  los argumentos de las clausuras se inyectan **por nombre** (el parámetro de
  `getSearchResultsUsing` *tiene* que llamarse `$search`).
- **PostgreSQL 16**, con `America/Guayaquil` como zona de la aplicación y UTC en
  el motor.
- **`nwidart/laravel-modules`**: el código de negocio vive en `Modules/`, no solo
  en `app/`.
- App móvil: **Expo SDK 57** / React Native, con `android/` **versionado** — los
  plugins de Expo NO vuelven a correr, así que cambiar `app.json` no regenera
  los recursos nativos.

## Trampas que ya costaron trabajo

- **La base es la de producción.** Nada se borra: los registros se desactivan
  (`usu_state`, `ins_estado`, `ipc_activo`…) porque el historial tiene que
  seguir consultable.
- **El APK ya está instalado en tablets.** Todo campo nuevo de la API entra como
  **opcional**; hay tests cuyo único fin es probar que sin el campo el
  comportamiento no cambia.
- **Zona horaria**: la app manda instantes con offset (`...-05:00`) y el servidor
  los convierte. Un `toISOString()` pelado se leía como hora local, caía 5 horas
  en el futuro y se descartaba en silencio.
- **Nunca `git add -A` en este repo**: hay volcados y fotos sin versionar al lado
  del código, y ya se colaron 63 MB una vez.
