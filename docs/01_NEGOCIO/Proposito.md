# Proposito y alcance

## Que es

**Total Secure App / DT360 Core** es el sistema de gestion operativa de
**guardias de seguridad**: control de accesos, rondas de vigilancia,
biometria, inventario, alertas y turnos, con una **aplicacion movil** que usan
los guardias en campo.

## Escala real — el sistema mas grande del servidor

| Dato | Valor |
|---|---|
| Tablas | **57** |
| **Usuarios** | **880** |
| Registros biometricos | **12.664** |
| Accesos registrados | **9.769** |
| Rondas (cabecera) | **8.667** |
| Detalle de rondas | ~37.617 |
| Movimientos de inventario | ~23.790 (detalle) / ~11.879 (cabecera) |
| Accesos de vehiculos | ~8.993 |
| Alertas | **278** |
| Relaciones usuario-institucion | ~38.247 |
| Tamano del proyecto en disco | **4,5 GB** |

Conteos exactos para `users`, `acceso`, `ronda_cabecera`,
`user_has_biometria` y `alertas`; los demas son estimaciones de
`pg_stat_user_tables` y deben confirmarse con `count(*)`.

**880 usuarios y 12.664 biometrias no son un piloto.** Es el sistema con mas
datos personales del servidor.

## Modulos

Laravel modular (`nwidart/laravel-modules`), los cuatro activos:

| Modulo | Para que |
|---|---|
| `Acceso` | control de accesos de personas y vehiculos |
| `Administracion` | gestion, catalogos, usuarios |
| `MobileApp` | API que consume la aplicacion de los guardias |
| `PortalApi` | API del portal |

Fuente: `modules_statuses.json`.

## Funcionalidad, por la evidencia de datos

| Area | Evidencia |
|---|---|
| Control de accesos | `acceso` (9.769), `acceso_persona`, `acceso_vehiculo` |
| Rondas de vigilancia | `ronda_cabecera` (8.667) + `ronda_detalle` |
| **Biometria** | `user_has_biometria` (**12.664**) |
| Inventario | `inv_movimiento_*`, `inv_producto_catalogo`, `inv_lista_item` |
| Alertas y emergencia | `alertas` (278); la app tiene boton EMERGENCIA |
| Multi-institucion | `user_has_institucion` (~38.247) |
| Roles y permisos | `user_has_roles` (923), `spatie/laravel-permission` |

## Multi-institucion

`user_has_institucion` con ~38.247 filas para 880 usuarios: **unos 43 vinculos
por usuario en promedio**. El sistema atiende a varias instituciones y un mismo
usuario opera en muchas.

Esto tiene consecuencia directa en seguridad: **el aislamiento entre
instituciones es una regla de negocio critica**, y verificarlo exige pruebas
que esta auditoria no pudo ejecutar (ver `13_PRUEBAS`).

## Historia: migracion desde V1

El proyecto incluye evidencia de una migracion desde un sistema anterior:

- `coredt360_bk.sql` — **32 MB** de respaldo
- `datos-v1/2025.rar` — **83 MB** de datos historicos
- Contenedor **`v1_analisis`** (MariaDB 10.4, creado el 2026-09-07), **suelto,
  sin proyecto compose**, conservando la base V1 para consulta
- `ANALISIS-MIGRACION-V1.md` (32 KB) y `ROADMAP-MIGRACION.md` (41 KB)

**El contenedor `v1_analisis` sigue corriendo.** Su proposito aparente es
consultar los datos historicos durante la migracion. Si esa migracion ya
concluyo, es candidato a apagarse tras respaldar su contenido.

## Naturaleza de los datos

⚠️ **Datos personales sensibles, en la categoria mas alta.** El sistema
almacena **biometria** (12.664 registros) de personas identificadas, ademas de
su ubicacion en rondas y sus registros de acceso. En Ecuador y en la mayoria de
marcos de proteccion de datos, la biometria tiene exigencias reforzadas.

Es el contexto en el que hay que leer el hallazgo SEC-01.

## Lo que NO se determino

- Si la migracion V1 concluyo: `NO DETERMINADO`.
- Cuantas instituciones cliente hay: `NO DETERMINADO`.
- Responsable funcional y del tratamiento de datos: `NO DETERMINADO`.
