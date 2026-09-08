# Análisis: plantilla de turnos y cuadrante de cobertura

Idea planteada: que el Líder Operativo suba una plantilla preliminar de qué
guardia va a dónde, y que de ahí salga un horario completo.

---

## 1. Antes: ¿tenemos las funciones del sistema anterior?

**Sí, íntegras.** La auditoría de Fase 0 inventarió **24 endpoints** en el sistema
original. Los 24 siguen existiendo, verificado uno por uno contra el router.

Y se agregaron **17 más** en las fases posteriores:

| Agregado | Para qué |
|---|---|
| `acceso/preregistro`, `preregistros`, `cancelar-preregistro` | Visitantes esperados (Fase 5) |
| `alert/crear`, `{id}/atender`, `{id}/cancelar`, `estadisticas`, `{id}/historial` | Escalamiento de alertas (Fase 4) |
| `inventario/registrar-baja` | Bajas de inventario (Fase 1) |
| `seleccionar_perfil`, `procesar_perfil` | RBAC (Fase 6) |
| `turnos-del-dia`, `turnos-vincular-marcaje`, `turnos-cumplimiento` | Turnos (Fase 3) |
| 7 endpoints `portal/*` | Portal cliente (Fase 8) |

Ninguna función se perdió. Lo eliminado fue el módulo hospitalario heredado
(Epicrisis, Referencia, Personas), que no pertenecía al dominio de guardias y
además no funcionaba.

---

## 2. Qué problema resuelve la plantilla

Hoy los turnos se programan **de a uno** en el panel. Haciendo la cuenta:

```
50 guardias × 30 días = 1.500 turnos al mes
```

Nadie va a cargar eso a mano. Sin plantilla, la funcionalidad de turnos existe
pero no se usa, y con ella se quedan sin datos:

- el widget **Cumplimiento de turnos** del dashboard,
- el cierre automático `turnos:cerrar-dia` de las 23:55,
- el reporte de tardanzas y horas extra,
- y el endpoint `turnos-del-dia` de la app.

O sea: **la plantilla es lo que activa toda la Fase 3**, que hoy está construida
pero inerte.

---

## 3. Dependencia crítica que hay que resolver primero

> El marcaje del guardia **no se vincula** al turno.

`BiometriaController` guarda la biometría y ahí termina. Enlazarla al turno exige
una llamada aparte a `POST api/turnos-vincular-marcaje`, y **la app nunca la
hace**: los tres endpoints de turnos no los consume ninguna pantalla, ni están en
`constants.ts`.

Consecuencia: aunque la plantilla genere 1.500 turnos perfectos,
`tu_marcada_entrada` quedaría en null, el cumplimiento marcaría 0% y el cierre
automático declararía ausentes a guardias que sí trabajaron.

**Sin cerrar este eslabón, la plantilla produce datos que nunca se contrastan con
la realidad.** Es trabajo de la app (dos pantallas y una llamada), no del APK
compilado.

---

## 4. Diseño propuesto

### El modelo: patrón semanal, no lista de fechas

Un cuadrante de guardias es **repetitivo**: "Juan cubre Garita, lunes a viernes,
06:00–14:00". Modelar fecha por fecha obligaría a resubir todo cada mes.

```
plantilla                    (el cuadrante de un local)
  pl_id, pl_ins_code, pl_nombre,
  pl_estado        borrador | publicada | archivada
  pl_vigencia_desde, pl_vigencia_hasta

plantilla_franja             (QUÉ hay que cubrir)
  pf_id, pf_pl_id, pf_puesto_id,
  pf_dia_semana    1..7
  pf_hora_inicio, pf_hora_fin
  pf_cruza_medianoche

plantilla_asignacion         (QUIÉN lo cubre)
  pa_id, pa_pf_id, pa_usu_id,
  pa_desde, pa_hasta         (para rotaciones y reemplazos)
```

La generación toma `plantilla + rango de fechas` y produce filas en `turno`, que
ya existe y no se toca. Igual que con la geografía: **aditivo**.

### La carga del archivo

`pxlrbt/filament-excel` (instalado) es **solo de exportación**; no hay paquete de
importación y no se pueden agregar dependencias en este entorno. La vía práctica
es **CSV con `fgetcsv` nativo**, que no necesita nada nuevo y Excel exporta sin
problema.

Formato propuesto, una fila por asignación:

```csv
cedula,local,puesto,dia,hora_inicio,hora_fin
0912345678,Terminal de carga,Garita de ingreso,LUN,06:00,14:00
0912345678,Terminal de carga,Garita de ingreso,MAR,06:00,14:00
```

Se acompaña de un **botón para descargar la plantilla ya rellenada** con los
puestos del local, para que el líder no tenga que escribir los nombres a mano y
no haya errores de tipeo.

---

## 5. Lo que el sistema valida y Excel no

Aquí está el valor real. Al subir, **antes de generar nada**:

| Validación | Por qué importa |
|---|---|
| Guardia con **dos turnos solapados** | Hoy nada lo impide: `turno` no tiene restricción de solape |
| Guardia asignado a un local **que no tiene vinculado** | No podría ni marcar: la app valida `user_has_institucion` |
| Puesto que **pertenece a otro local** | Turno incoherente |
| **Franja sin cubrir** | Un puesto sin guardia asignado se ve *antes* del día, no cuando falta |
| **Descanso insuficiente** | Cierra 22–06 y abre 06–14 el mismo día |
| Guardia **fuera del país** del líder | Respeta el alcance de la Fase 6 |
| Cédula inexistente o guardia inactivo | Errores de tipeo del Excel |

Los errores bloquean; las advertencias (descanso corto, franja sin cubrir) dejan
publicar pero quedan registradas.

### Regenerar sin pisar lo ya ocurrido

El punto más delicado. Si el líder corrige la plantilla a mitad de mes y
republica, la generación **no puede borrar turnos donde el guardia ya marcó**.
Regla propuesta:

- turnos **futuros y sin marcaje** → se reemplazan
- turnos **con marcaje** o **pasados** → se conservan intactos y se informa cuáles
  quedaron fuera del cambio

---

## 6. El flujo

```
Líder Operativo
   │
   ├─ 1. Descarga la plantilla del local (puestos y franjas ya listados)
   ├─ 2. La llena en Excel: qué guardia va en cada franja
   ├─ 3. La sube  ──► el sistema valida TODO antes de tocar la base
   │                   ├── errores    → los muestra y no genera nada
   │                   └── sin errores→ queda en estado BORRADOR
   │
   ├─ 4. Revisa el cuadrante en pantalla: huecos de cobertura y avisos
   ├─ 5. Publica  ──► genera los turnos del período en `turno`
   │
   └─ 6. Cambios a mitad de mes → corrige y republica
                                  (respeta lo ya marcado)

Guardia (app)
   └─ Ve su turno del día con su puesto, marca entrada y salida
      └─► el marcaje se vincula al turno  ◄── ESTO ES LO QUE FALTA HOY

Supervisor / Dashboard
   └─ Cumplimiento real: quién marcó, quién llegó tarde, qué puesto quedó vacío
```

---

## 7. Beneficios

**Operativos**

- Programar un mes toma minutos en vez de ser inviable.
- Los **huecos de cobertura se ven antes**, no cuando el puesto amanece vacío.
- Los **solapes se detectan al cargar**, no cuando dos guardias reclaman el mismo
  turno.
- Reemplazos y rotaciones sin rehacer el cuadrante: se cambia una asignación.

**De control**

- Activa el widget de cumplimiento, el cierre automático y el reporte de
  tardanzas, que hoy están construidos y sin datos.
- Deja registro de **quién debía estar dónde**, que es lo que permite reclamar o
  responder un reclamo del cliente.
- El portal cliente podría mostrar la cobertura comprometida contra la real.

**De escala**

- Es lo que hace viable operar en varios países: cada líder arma el cuadrante de
  su país sin pisar al otro, con el alcance de la Fase 6 ya funcionando.

---

## 8. Tamaño y riesgos

Es la funcionalidad **más grande desde las fases**. Por partes:

| Parte | Tamaño | Riesgo |
|---|---|---|
| Modelo y migraciones | Bajo | Bajo: aditivo, `turno` no se toca |
| Importador CSV + validaciones | **Medio-alto** | Las validaciones son la mitad del trabajo |
| Generador de turnos | Medio | Alto en el caso de republicar: no pisar lo marcado |
| Cuadrante visual | **Alto** | Filament no trae una vista de calendario; hay que construirla |
| Vincular marcaje ↔ turno (app) | Medio | Toca la pantalla de biometría, que hoy funciona |

Riesgo principal: **hacer la plantilla sin cerrar el eslabón del marcaje**. Se
generarían miles de turnos que nadie contrasta, y el cierre automático empezaría
a marcar ausentes en masa. Sería peor que no tenerla.

### Orden recomendado

1. **Vincular marcaje ↔ turno** (app + backend). Sin esto lo demás no rinde.
2. **Modelo de plantilla** y generación desde el panel, sin importación: el líder
   define franjas y asigna guardias en pantalla. Ya sirve para locales chicos.
3. **Importación CSV** con las validaciones. Es lo que lo hace viable a escala.
4. **Cuadrante visual** con huecos y conflictos resaltados.
5. **Republicación** respetando lo ya marcado.

Los pasos 1 y 2 ya entregan valor por separado; no hace falta llegar al 5 para
que sirva.

---

## 9. Alternativa descartada

**Carga plana fecha por fecha** (CSV con una fila por turno concreto). Más simple
de implementar, pero sin reutilización: cada mes hay que rearmar el archivo
completo, y un cambio de rotación obliga a reeditar cientos de filas. El patrón
semanal cuesta un poco más y se amortiza el primer mes.
