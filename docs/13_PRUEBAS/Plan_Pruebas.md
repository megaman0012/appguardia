# Plan de pruebas

Version 1.0 · 2026-09-15

## Restriccion

Sistema en produccion con **880 usuarios, 12.664 biometrias y guardias
operando en campo**. No se ejecuto ninguna prueba que modifique datos ni
prueba autenticada: habria exigido credenciales reales que no se solicitaron
ni se deben inventar.

## Ejecutado

| ID | Caso | Tipo | Esperado | Obtenido | Resultado |
|---|---|---|---|---|---|
| T-01 | El portal responde localmente | Disponibilidad | 200/302 | 302 | ✅ |
| T-02 | **Responde desde internet** | **Seguridad** | no deberia sin TLS | **302 en `181.198.245.50:3031`** | ❌ SEC-01 |
| T-03 | `APP_DEBUG` en produccion | **Seguridad** | `false` | **`false`** | ✅ corregido |
| T-04 | `APP_ENV` | Seguridad | `production` | `production` | ✅ |
| T-05 | PostgreSQL solo local | **Seguridad** | `127.0.0.1` | `127.0.0.1:5434` | ✅ |
| T-06 | `DB_PASSWORD` con fail-fast | Seguridad | declarado | declarado | ✅ |
| T-07 | Keystore excluido de git | **Seguridad** | si | `/apk/` y `*.jks` en `.gitignore` | ✅ |
| T-08 | Permisos del keystore | Seguridad | restringidos | `600` | ✅ |
| T-09 | Volumen de datos | Inventario | coherente | 57 tablas, 880 usuarios | ✅ |
| T-10 | Trafico sin cifrar en la app | **Seguridad** | deshabilitado | **`usesCleartextTraffic: true`** | ❌ SEC-01 |
| T-11 | Respaldo automatico | **Continuidad** | existente | **no existe** | ❌ |
| T-12 | Contenedor V1 bajo control | Operacion | en compose | **suelto** | ❌ |

**7 de 12 ✅.** Los cinco fallos son, en orden: exposicion sin cifrar (dos
casos), ausencia de respaldo y contenedor fuera de control.

## Pendientes — funcionales

| ID | Caso | Area |
|---|---|---|
| T-20 | Login web y movil | Auth |
| T-21 | Expiracion del token a los 3600 s | Auth |
| T-22 | Registro de acceso de persona | Acceso |
| T-23 | Registro de acceso de vehiculo | Acceso |
| T-24 | Ronda completa con detalle | Rondas |
| T-25 | Captura biometrica desde la app | Biometria |
| T-26 | **Boton EMERGENCIA → `/alert/crear`** | Alertas |
| T-27 | Movimiento de inventario | Inventario |
| T-28 | Generacion de PDF con QR y logo | Reportes |

## Pendientes — seguridad, y la mas importante

| ID | Caso | Por que importa |
|---|---|---|
| **T-30** | **Un usuario de la institucion A no ve datos de la institucion B** | **Es la prueba critica.** ~38.247 vinculos usuario-institucion: si el aislamiento falla, una institucion ve los datos de otra |
| T-31 | Un rol sin permiso recibe 403 | `spatie/laravel-permission` |
| T-32 | Token manipulado es rechazado | Sanctum |
| T-33 | Captura del trafico entre tablet y servidor | **Confirmaria SEC-01**: deberia mostrar credenciales y biometria legibles |
| T-34 | `/admin` sin sesion | Control de acceso |

**T-30 y T-33 son las dos que este sistema necesita antes que ninguna otra.**

## Pendientes — moviles

| ID | Caso |
|---|---|
| T-40 | Instalar 1.0.2 sobre 1.0.0 sin perder datos locales |
| T-41 | Verificar la huella SHA-256 antes de instalar |
| T-42 | Funcionamiento sin conexion y sincronizacion posterior |
| T-43 | Permisos de camara, ubicacion y notificaciones |
| T-44 | Las 22 pantallas con el tema claro |
| T-45 | **Censo de versiones instaladas en las tablets** — confirmar que ninguna siga por debajo de 1.0.2 (sin boton de panico) |

## Recuperacion

| ID | Caso | Estado |
|---|---|---|
| T-50 | Restaurar la base | **no ejecutable: no hay respaldos** |
| T-51 | Restaurar la base V1 | parcial: existe `coredt360_bk.sql` |
| T-52 | Recuperar el keystore | **no ejecutable: no hay copia** |
