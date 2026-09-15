# Changelog

## [Sin publicar]

### Corregido
- **2026-09-15 — Respaldo diario, que no existía.** `backend/scripts/respaldo.sh`
  a las 03:00: base PostgreSQL, base MariaDB de la V1, secretos con `APP_KEY` y
  el keystore de firma; las fotos por espejo incremental. Verificado restaurando
  el índice del dump (58 tablas) y comparando el SHA-256 del keystore.
  Procedimiento en `backend/scripts/RESPALDO.md`. ⚠️ Sigue faltando la copia
  **fuera del servidor** (`DESTINO_EXTERNO`).
- **2026-09-15 — 🔴 Cerrada la toma de cuentas (SEC-00).** `POST /api/procesar_paswchg`
  cambiaba la contraseña de cualquier usuario mandando sólo su `user_id`, un entero
  secuencial, sin token ni autenticación, sobre un endpoint publicado en internet;
  `solicitud_paswchg` completaba el circuito devolviendo el `user_id` y el token a
  cambio de una cédula. **El portal web tenía el mismo agujero** en
  `POST /acceso/procesar_cambiopass`, que tomaba el `user_id` del formulario.
  Ahora el código es criptográfico, se guarda hasheado, vence, sirve una sola vez
  y no vuelve en la respuesta.
- ⚠️ **El autoservicio de recuperación queda sin efecto hasta configurar un canal**
  (SMTP o WhatsApp) desde `/admin/configuracion`. Mientras tanto el supervisor
  cambia la clave desde el panel. **El APK 1.0.2 no puede recuperar clave**: usa el
  contrato viejo y hay que recompilarlo.
- **2026-09-15 — El botón de EMERGENCIA ahora avisa a alguien.** La alerta se
  guardaba bien desde siempre, pero el único aviso era un evento
  `ShouldBroadcast` emitido con `BROADCAST_DRIVER=log`: cada pedido de auxilio
  terminaba como una línea en `storage/logs` y nadie se enteraba. Se agregó
  `NotificadorAlerta`, el listener que faltaba y el canal `panel` (la campanita
  de Filament, el único que no depende de Firebase ni del gateway de WhatsApp).
- **Escalar una alerta reventaba siempre**: `buscarSupervisorPorNivel()` usaba
  `whereHas('instituciones')`, una relación inexistente en ambos modelos `users`.
- El evento de alerta se emitía **dentro** de la transacción, con el envío de
  WhatsApp bloqueando una fila de la base durante su timeout.

### Agregado
- **2026-09-15** — Documentacion en `docs/` (12 areas; manuales de usuario y
  administrador pendientes), `INFORME_AUDITORIA.md` y este changelog.
- `docs/10_MOBILE/Aplicacion_Movil.md` — documentacion completa de la app.

### Verificado
- `APP_DEBUG=false` y `APP_ENV=production`: la fuga de la contrasena por las
  trazas de Ignition **esta cerrada**.
- El `.gitignore` excluye APK y keystore.

### Detectado
- El sistema responde desde internet en HTTP plano, con la app movil
  habilitando trafico sin cifrar. Biometria de 880 personas de por medio.
- Sin respaldo de base, secretos ni keystore de firma.

### Sin cambios
- No se modifico codigo, configuracion, Docker ni datos.

## [1.0.2] — 2026-09-09
- APK con seis correcciones, incluido el **boton EMERGENCIA**, que no existia.

## [1.0.1] — 2026-09-08
- ⚠️ **No usar**: se compilo antes de que entraran los arreglos.

## [1.0.0] — 2026-09-08
- Primera version de produccion de la app movil.

*Reconstruido desde `apk/LEEME-apk.txt` y git.*
