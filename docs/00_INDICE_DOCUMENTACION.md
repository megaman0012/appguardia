# Indice de documentacion — Total Secure App (DT360 Core)

Auditoria del **2026-09-15**.

> Es el sistema **mas grande del servidor** (57 tablas, 880 usuarios) y el
> **unico expuesto a internet**.

| Area | Estado | Documento |
|---|---|---|
| NEGOCIO | 🟢 COMPLETO | [01_NEGOCIO/Proposito.md](01_NEGOCIO/Proposito.md) |
| ARQUITECTURA | 🟢 COMPLETO | [02_ARQUITECTURA/Arquitectura.md](02_ARQUITECTURA/Arquitectura.md) |
| DESARROLLO | 🟡 PARCIAL | [03_DESARROLLO/API.md](03_DESARROLLO/API.md) — existe `openapi.yaml` previo |
| DOCKER | 🟢 COMPLETO | [04_DOCKER/Docker.md](04_DOCKER/Docker.md) |
| SEGURIDAD | 🟢 COMPLETO | [05_SEGURIDAD/Analisis_Seguridad.md](05_SEGURIDAD/Analisis_Seguridad.md) |
| BASE DE DATOS | 🟡 PARCIAL | [06_BASE_DATOS/Modelo_Datos.md](06_BASE_DATOS/Modelo_Datos.md) — 57 tablas, no todas documentadas |
| MANUAL USUARIO | 🟢 COMPLETO (guardia) | [07_MANUAL_USUARIO/Manual_Guardia.md](07_MANUAL_USUARIO/Manual_Guardia.md) — el uso de la app en la tablet |
| MANUAL ADMINISTRADOR | 🔴 PENDIENTE | el panel está cambiando; ver nota |
| MANUAL SOPORTE | 🟢 COMPLETO | [09_MANUAL_SOPORTE/Manual_Soporte.md](09_MANUAL_SOPORTE/Manual_Soporte.md) |
| **MOBILE** | 🟢 **COMPLETO** | [10_MOBILE/Aplicacion_Movil.md](10_MOBILE/Aplicacion_Movil.md) |
| OPERACION | 🟢 COMPLETO | [11_OPERACION/Operacion.md](11_OPERACION/Operacion.md) |
| CONTINUIDAD | 🟢 COMPLETO | [12_CONTINUIDAD/Backup_Recuperacion.md](12_CONTINUIDAD/Backup_Recuperacion.md) |
| PRUEBAS | 🟡 PARCIAL | [13_PRUEBAS/Plan_Pruebas.md](13_PRUEBAS/Plan_Pruebas.md) |
| GESTION | 🟢 COMPLETO | [14_GESTION/Historial_Versiones.md](14_GESTION/Historial_Versiones.md) |

## Manual del guardia: hecho el 2026-09-22

`07_MANUAL_USUARIO/Manual_Guardia.md` cubre **el uso de la app en la tablet del
puesto**: entrar, el arranque de la jornada, marcacion, inventario, rondas,
accesos, novedades, el boton de emergencia, turnos y perfil.

Se escribio **leyendo las pantallas una por una en el codigo fuente**, no
navegando produccion ni de memoria: los textos, los avisos y las validaciones que
menciona son los que la app muestra de verdad. Eso resuelve el bloqueo que tenia
--hacia falta un usuario real-- sin tocar datos de nadie.

⚠️ **Dice que la aplicacion NECESITA CONEXION**, porque es la verdad: el contrato
de sincronizacion sin senal existe en el backend (`API-OFFLINE-SYNC.md`) pero
**la cola no esta implementada en la APK**. Prometer lo contrario en un manual
haria que un guardia diera por registrado algo que se perdio.

## Por que el manual de administrador sigue pendiente

El panel web tiene 28 recursos y su documentacion util depende de decisiones de
operacion que todavia se estan moviendo --el kit de puesto, los locales, la
consolidacion pendiente--. Documentarlo hoy seria describir pantallas que van a
cambiar.

**Lo que si esta documentado** es todo lo verificable: arquitectura, base de
datos, seguridad, Docker, operacion, continuidad, la aplicacion movil y ahora su
manual de uso.

## Documentacion previa: clasificacion

Es el proyecto con **mas documentacion previa** del servidor. Toda se conserva.

| Documento | Estado | Observacion |
|---|---|---|
| `apk/LEEME-apk.txt` | 🟢 **VIGENTE, excelente** | incluye la huella SHA-256 del certificado y la historia de por que la 1.0.1 no servia |
| `apk/LEEME-keystore.txt` | 🟢 VIGENTE | manejo del keystore de firma |
| `openapi.yaml` (15 KB) | 🟡 **VERIFICAR** | del 2026-09-07; contrastar con las rutas actuales |
| `README.md` | 🟢 VIGENTE | |
| `DESPLIEGUE.md` / `DESPLIEGUE-DOMINIO.md` | 🟢 VIGENTE | |
| `DOCUMENTACION_PROYECTO.md` | 🟢 VIGENTE | |
| `ROADMAP-MIGRACION.md` (41 KB) | 🟢 VIGENTE | migracion desde V1 |
| `ANALISIS-MIGRACION-V1.md` (32 KB) | 🟢 VIGENTE | |
| `CHECKLIST-DESPLIEGUE-V2.md` | 🟢 VIGENTE | |
| `API-OFFLINE-SYNC.md` | 🟢 VIGENTE | sincronizacion sin conexion |
| `WHATSAPP-EVOLUTION.md` | 🟡 VERIFICAR | integracion no comprobada |
| `docs/historia/FASE1..FASE6` | 🟢 VIGENTE | historia de construccion por fases |
| `repomix-output.xml` (1,2 MB) | ⚪ NO ES DOCUMENTACION | volcado del codigo para herramientas |
| `HISTORIAL_DE_CHAT.md` | ⚪ NO VERIFICABLE | transcripcion |


## Formatos disponibles

Estos documentos existen ademas en **PDF** y **DOCX**, junto a cada `.md`:

| Documento | PDF | DOCX |
|---|---|---|
| `INFORME_AUDITORIA` | ✅ | ✅ |
| `02_ARQUITECTURA/Arquitectura` | ✅ | ✅ |
| `07_MANUAL_USUARIO/Manual_Guardia` | ✅ | ✅ |
| `08_MANUAL_ADMINISTRADOR/Manual_Administrador` | ✅ | ✅ |
| `09_MANUAL_SOPORTE/Manual_Soporte` | ✅ | ✅ |
| `14_GESTION/Historial_Versiones` | ✅ | ✅ |

**El Markdown es la fuente.** El PDF y el DOCX se regeneran desde el `.md`; no
se editan a mano, porque el siguiente regenerado los pisa.

Para regenerarlos:

    python3 /usr/local/share/auditoria/md2pdf.py  <documento>.md --proyecto "<Nombre>"
    python3 /usr/local/share/auditoria/md2docx.py <documento>.md --proyecto "<Nombre>"

Ver `/usr/local/share/auditoria/LEEME.md`.
