# API

## Existe una especificacion previa

    openapi.yaml    (15 KB, 2026-09-07, en la raiz del proyecto)

**Es la fuente de referencia de la API.** Esta auditoria **no la contrasto
endpoint por endpoint** con el codigo: se clasifica como 🟡 **VERIFICAR**,
porque es del 2026-09-07 y el proyecto siguio cambiando (los APK son del 08 y
09 de septiembre).

**Recomendacion:** contrastarla contra las rutas reales de los cuatro modulos.

## Autenticacion

| Elemento | Valor |
|---|---|
| Libreria | **`laravel/sanctum` ^4.0** |
| Vigencia del token | `TOKEN_EXPIRE_IN: 3600` (1 hora) |
| Refresco | `TOKEN_REFRESH_EXPIRE_IN: 3600` |
| Roles y permisos | **`spatie/laravel-permission` ^8.0** |

Ambas son las librerias estandar del ecosistema Laravel, no implementaciones
propias. Es la decision correcta.

## Modulos que exponen API

| Modulo | Consumidor |
|---|---|
| `MobileApp` | la aplicacion de los guardias |
| `PortalApi` | el portal web |
| `Acceso` | control de accesos |
| `Administracion` | gestion |

## Endpoints conocidos por evidencia

Solo se documenta lo verificado:

| Ruta | Evidencia |
|---|---|
| `/alert/crear` | `apk/LEEME-apk.txt`: el boton EMERGENCIA de la app 1.0.2 lo llama |
| `/admin` | mencionado en el comentario del compose sobre el incidente de `APP_DEBUG` |

El resto esta en `openapi.yaml` y en las rutas de los modulos.

## Sincronizacion sin conexion

`API-OFFLINE-SYNC.md` documenta como la app sincroniza cuando recupera
conexion. **No verificado**: `NO DETERMINADO`.

## Generacion de PDF

`barryvdh/laravel-dompdf`. El comentario del compose registra un problema real
y ya resuelto: con `APP_URL` en `localhost`, **el logo de la hoja del QR no
cargaba** porque se resolvia por `asset()` y dompdf no podia descargarlo. Por
eso `APP_URL` apunta hoy a la IP publica.

> Es un buen ejemplo de por que `APP_URL` importa mas de lo que parece: no solo
> afecta enlaces, tambien la generacion de documentos.

## WhatsApp

`WHATSAPP-EVOLUTION.md` documenta una integracion con Evolution API.
**No verificada**: `NO DETERMINADO`.
