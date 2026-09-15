# Changelog

## [Sin publicar]

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
