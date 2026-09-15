# Historial de versiones

## Documentacion

| Version | Fecha | Cambio | Responsable |
|---|---|---|---|
| 1.0 | 2026-09-15 | Auditoria y documentacion. Manuales de usuario y administrador pendientes por requerir credenciales. Toda la documentacion previa se conserva. | Auditoria tecnica |

## Aplicacion movil

| Version | versionCode | Fecha | Tamano | Estado |
|---|---|---|---|---|
| 1.0.0 | 1 | 2026-09-08 07:28 | 100 MB | superada |
| 1.0.1 | 2 | 2026-09-08 11:52 | 99 MB | ⚠️ **NO USAR** — se compilo antes de que entraran los arreglos |
| **1.0.2** | **3** | 2026-09-09 02:05 | 53 MB | ✅ **vigente** |

Todas con el mismo certificado (huella SHA-256 en `apk/LEEME-apk.txt`), asi que
se instalan una sobre otra sin perder datos locales.

**El salto de 99 MB a 53 MB** entre la 1.0.1 y la 1.0.2 sugiere optimizacion
del bundle, no solo correcciones.

## Backend

Sin etiquetas de version. Laravel ^13.0, PHP ^8.3.

## Historia de construccion

`docs/historia/` documenta seis fases:

| Fase | Contenido |
|---|---|
| FASE1 | inventario unificado |
| FASE2 | validacion de presencia |
| FASE3 | turnos |
| FASE4 | alertas |
| FASE5 | accesos |
| FASE6 | RBAC (roles y permisos) |

Mas `PROPUESTA-JERARQUIA-Y-ROLES.md` y `PROPUESTA-PLANTILLA-TURNOS.md`.

**Es la mejor documentacion de proceso del servidor**: se puede reconstruir
como se llego al sistema actual.

## Migracion desde V1

| Evidencia | Tamano |
|---|---|
| `ANALISIS-MIGRACION-V1.md` | 32 KB |
| `ROADMAP-MIGRACION.md` | 41 KB |
| `coredt360_bk.sql` | 32 MB |
| `datos-v1/2025.rar` | 83 MB |
| Contenedor `v1_analisis` | creado 2026-09-07 |

**Estado de la migracion: `NO DETERMINADO`.** Es una decision pendiente con
efecto directo sobre el respaldo y la superficie del servidor.

## Repositorio

| | |
|---|---|
| Ruta | `/home/server-dt/Documentos/totalsecureapp` |
| Ultimo cambio | 2026-09-10 |
| Remoto | ninguno configurado |
| `.gitignore` | ✅ excluye APK y keystore, con el motivo documentado |

**Riesgo de gestion:** sin remoto, el codigo **y el keystore** existen solo en
este servidor.

## Nota sobre `repomix-output.xml`

1,2 MB con un volcado del codigo para herramientas de analisis. **No es
documentacion** y conviene excluirlo de git si no lo esta: duplica el codigo y
se desactualiza solo.

## Responsables

`NO DETERMINADO`. Dado que el sistema trata datos biometricos, conviene
designar formalmente un **responsable del tratamiento de datos**.
