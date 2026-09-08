# Documentos históricos

Nada de acá describe el sistema **actual**. Son documentos de diseño previo y
registros de avance, guardados porque **tienen el «por qué» de decisiones** que
la documentación viva da por sentadas.

**Para saber cómo funciona el sistema hoy:** `totalsecureapp/AGENTS.md`.

> ⚠️ **Varios describen un estado que ya no existe.** El caso más claro es
> FASE2, cuya tabla dice que ningún controlador valida GPS: era cierto cuando se
> escribió y es falso desde que se implementó. Leerlos como documentación actual
> lleva a conclusiones equivocadas.

## Diseño de fases (previo a implementar)

| Documento | Qué proponía | Cómo quedó |
|---|---|---|
| `FASE1-INVENTARIO-UNIFICADO.md` | Reemplazar las tablas de inventario por un juego nuevo | Implementado. Y quedó a medias hasta el 2026-09-07: la app usaba las nuevas y el panel las viejas. Ver AGENTS.md, «Inventario: un solo juego de tablas» |
| `FASE2-PRESENCE-VALIDATION.md` | Un servicio único para validar QR + GPS + geocerca | Implementado (`PresenceValidationService`). Su tabla de «estado actual» ya no aplica |
| `FASE3-TURNOS.md` | Modelo de turnos y planificación | Implementado. Las tablas existen y están **vacías**: v1 no tenía turnos |
| `FASE4-ALERTAS.md` | Alertas con escalamiento | Implementado |
| `FASE5-ACCESOS.md` | Acceso vehicular + peatonal + proveedor | Implementado. Normalizó columnas de `acceso` hacia `acceso_vehiculo`, lo que dejó seis columnas muertas en el panel hasta el 2026-09-08 |
| `FASE6-RBAC.md` | Permisos granulares en vez de perfiles fijos | Implementado |

## Propuestas (aprobadas e implementadas)

| Documento | |
|---|---|
| `PROPUESTA-JERARQUIA-Y-ROLES.md` | El modelo de cinco roles y el alcance territorial. Hoy vive en `App\Support\PerfilPanel` |
| `PROPUESTA-PLANTILLA-TURNOS.md` | El cuadrante semanal y la cobertura de vacantes |

## Registros

| Documento | |
|---|---|
| `RESUMEN-AVANCE.md` | Avance hasta el 2026-08-21. **No conoce** la migración de datos de v1, el ETL, el APK ni el keystore |
| `planificacion_pasos.md` | Roadmap de agosto, ya ejecutado |
| `HISTORIAL_DE_CHAT.md` | Registro de las primeras sesiones. ⚠️ **Contiene una contraseña en texto plano** que ya viajó al historial de git: tratarla como comprometida donde se haya reutilizado |

## Dos documentos que se eliminaron, y por qué

No están archivados: se borraron, porque **engañaban** en vez de estar
simplemente viejos.

- **`ANALISIS-ESQUEMA-ACTUAL.md`** (681 líneas) — describía `sede`,
  `organizacion_sede` y `persona` **33 veces entre las tres**, tablas que las
  migraciones `2026_08_24_100001` y `2026_08_27_100001` eliminaron. Y no
  mencionaba ni una vez `plantilla`, `vacante`, `puesto` ni `aviso_envio`, que
  son la mitad del sistema de hoy. Quien lo leyera se formaba una idea falsa del
  esquema.
- **`planificacion`** (303 líneas, sin extensión) — decía «Stack y arquitectura
  **inferidos**»: era la ingeniería inversa hecha *antes* de tener el código.
  Superado por el código mismo.

Los dos siguen en el historial de git si hicieran falta.
