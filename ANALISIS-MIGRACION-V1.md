# Análisis de la base real de v1 → v2

> Sobre `coredt360_bk.sql` (32 MB), volcado de **Navicat** el 2026-09-07 16:46 desde
> un servidor rotulado **`Produccion`**. Motor **MariaDB 10.4.32**, esquema
> `coredt360`. 41 tablas, 423 columnas, sin vistas, triggers ni procedimientos.
>
> Analizado cargándolo en un MariaDB 10.4 desechable, **no** contra el dump a ojo.
> Todos los números de abajo son consultas reales.

---

## 0. Primero: el dump está dentro del repo y no estaba ignorado

`coredt360_bk.sql` quedó en la raíz del repo, que está en GitHub. Trae datos de
878 personas reales —cédulas, correos, hashes de contraseña, GPS— y **un
`git add -A` lo habría publicado**. Comprobado que nunca entró a ningún commit, y
agregado a `.gitignore` (`*.sql`, `*.dump`, `*.sql.gz`, con excepción para el
script de init del contenedor).

**Sacarlo del historial después habría sido reescribirlo**, con el repo ya
clonado en otras máquinas. Conviene además no dejarlo ahí: su lugar es fuera del
árbol de trabajo.

---

## 1. La hipótesis fácil no funciona

La idea era: v1 y v2 son el mismo linaje (`coredt360`), así que **restaurar una
copia de v1 y correrle `php artisan migrate`** dejaría que las 48 migraciones
hagan el trabajo. Se descartó, y conviene saber por qué antes de intentarlo.

**v1 tiene 8 migraciones registradas.** La última es
`2024_11_29_190201_create_pacientes_table` — la herencia del sistema
hospitalario. 7 de esas 8 existen como archivo en v2; la de `pacientes` la
eliminó la limpieza del 2026-08-24 (inofensivo: `migrate` no corre lo que no
tiene archivo).

El problema es el otro lado: **8 migraciones no crearon 41 tablas.** La mayor
parte del esquema de v1 se hizo a mano, fuera de Laravel. Y en v2 alguien
escribió migraciones *de recuperación* que crean esas mismas tablas como código:

| Migración de v2 | Crea | ¿v1 la registró? |
|---|---|---|
| `2024_12_01_000000_create_mobile_app_business_tables` | 17 tablas | **No** |
| `2026_08_12_130000_create_organizacion_structure_tables` | 3 | **No** |
| `2026_08_12_100000_create_web_catalog_tables` | 5 | **No** |
| `2026_08_12_110000_create_web_log_tables` | 2 | **No** |
| `2024_11_27_150000_create_parametros_table` | 1 | **No** |

Ninguna usa `Schema::hasTable` como guarda (verificado: 0 ocurrencias en las
cinco). Sobre la base de v1 intentarían **crear tablas que ya existen** y
`migrate` se cae en la primera.

Y hay un segundo motivo, más de fondo: **v1 es MariaDB y v2 es PostgreSQL.** El
volcado es sintaxis MySQL y no entra en Postgres.

### El camino que sí sirve

**Esquema nuevo de v2 desde las migraciones (que ya funciona: 48 migraciones,
292 pruebas verdes) y ETL de solo los datos.**

Con una consecuencia que hay que tener presente: las migraciones que
**transforman** datos ya estarán aplicadas cuando lleguen las filas, así que
**no van a transformar nada**. Lo que hacían hay que hacerlo a mano en el ETL:

- `2026_08_27_100001_eliminar_sede` — rescatar el cliente que colgaba de la sede.
- `2026_08_24_300001_backfill_ciudad_en_locales` — resolver la ciudad de cada local.

En desarrollo esas migraciones no tenían nada que transformar. **Aquí sí.**

---

## 2. Cuánto dato hay

Producción de verdad, no una prueba. Del 2025-04-07 al 2026-09-07 (17 meses).

| Tabla | Filas | Nota |
|---|---:|---|
| `user_has_institucion` | 38.246 | Legítimo: 877 usuarios × hasta 125 locales. **Sin pares duplicados** |
| `ronda_detalle` | 37.616 | 29.243 con foto |
| `inv_movimiento_detalles` | 23.790 | |
| `user_has_biometria` | 12.664 | **todas** con foto |
| `personal_access_tokens` | 11.531 | Tokens de sesión: no migrar, se regeneran |
| `acceso` | 9.769 | |
| `ronda_cabecera` | 8.666 | |
| `inv_movimientos` | 5.963 | |
| `log` / `log_trafico` | 1.750 / 1.152 | Auditoría; decidir si vale la pena |
| `acceso_persona` | 1.636 | Visitantes registrados |
| `users` | **878** | |
| `user_has_roles` / `user_has_gestions` | 932 / 886 | |
| `organizacion_institucion` | **137** | Los locales |
| `institucion_marcadores` | **118** | Puntos QR |
| `organizacion` | **21** | Los clientes |
| `alertas` | 278 | |
| `novedad` | 7 | |
| `sede` / `organizacion_sede` | **2 / 23** | Ver §3.1 |
| `bitacora`, `failed_jobs`, `password_resets`, `productos`, `user_has_permissions` | 0 | Vacías: no son problema |

---

## 3. Los cuatro choques reales

### 3.1 `sede` no es lo que v2 creyó: son **países**

`AGENTS.md` dice que `sede` era «un nivel intermedio (organización → sede →
institución)» que «**nunca se usó** — las tres tablas estaban vacías y ningún
local tenía sede». Eso era cierto **en la base de desarrollo**. En producción:

```
sede:  ps_code 1 = "Ecuador" (EC, activo)
       ps_code 2 = "Chile"   (CL, inactivo)
organizacion_sede: 23 filas, cliente ↔ país
```

`sede` se estaba usando como **catálogo de países**, y `organizacion_sede` dice
en qué países opera cada cliente. Los **137 locales tienen `ins_so_code`**, así
que el rescate del cliente hacia `ins_cliente_id` funciona para todos.

Lo que se perdería si nadie lo mira es **el país**: en v2 el país de un local no
se guarda, se deduce por `ciudad → provincia → país`. Y eso importa porque
`PerfilPanel::localesDelUsuario()` acota al Líder Operativo por país, y **un
local sin ciudad no pertenece a ningún país, así que ningún líder lo ve**.

> ⚠️ **v2 sembró Ecuador y Colombia. Los datos reales dicen Ecuador y Chile.**
> Los 137 locales están en Ecuador; Chile está inactivo y sin locales. Habría que
> confirmar si Colombia es real (el README dice que se opera ahí) o si quedó del
> seed, y si Chile hay que conservarlo.

### 3.2 La ciudad es texto libre y hay que construir el catálogo

v1: `ins_ciudad varchar(250)`, escrito a mano. v2: `ins_ciudad_id`, FK al
catálogo `ciudad`. **v2 tiene 24 provincias sembradas y una sola ciudad**
(Guayaquil).

Los 16 valores reales, con su conteo:

| v1 (texto) | Locales | Qué hacer |
|---|---:|---|
| `Guayaquil` | 95 | ya existe en v2 |
| `QUITO` | 16 | crear (Pichincha) |
| `MANTA` | 11 | crear (Manabí) |
| `CUENCA` | 2 | crear (Azuay) |
| `AMBATO` | 2 | crear (Tungurahua) |
| `PORTOVIEJO`, `IBARRA`, `STO. DOMINGO`, `DURÁN`, `NARANJAL`, `EL TRIUNFO`, `Nobol`, `VILLAMIL PLAYAS` | 1 c/u | crear |
| `SAN CRISTOBAL`, `BALTRA` | 1 c/u | crear (Galápagos) |
| **`MANTENIMIENTO`** | 1 | **no es una ciudad** — decisión humana |

Son **15 ciudades por crear**, cada una atada a su provincia. Y hay que
normalizar mayúsculas y abreviaturas (`STO. DOMINGO` → Santo Domingo,
`VILLAMIL PLAYAS` → General Villamil). El caso `MANTENIMIENTO` es dato sucio:
alguien escribió un área en el campo de ciudad, y ese local **quedaría fuera del
alcance de todo líder** si se deja así.

### 3.3 `acceso`: 7 columnas cambiaron de tabla, y **tienen datos**

Ocho columnas de `acceso` no existen en el `acceso` de v2. No están perdidas
—v2 las normalizó en `acceso_vehiculo`— pero un ETL columna a columna **las
tiraría en silencio**:

| v1 `acceso` | Filas con dato | v2 |
|---|---:|---|
| `ac_empresa` | **8.975** | `acceso_vehiculo.av_empresa` |
| `ac_patente` | 1.326 | `av_patente` |
| `ac_is_neumatico` | 694 | `av_is_neumatico` |
| `ac_pta_llave` | 652 | `av_pta_llave` |
| `ac_is_sello` | 634 | `av_is_sello` |
| `ac_is_carro` | 602 | `av_is_carro` |
| `ac_kms` | 362 | `av_kms` |
| `ac_nombre_contrato` | 36 | **ningún destino** |

O sea: el ETL de accesos **se parte en dos** — las columnas base a `acceso`, y una
fila en `acceso_vehiculo` por cada acceso que traiga alguno de esos siete campos.
`ac_empresa` con 8.975 filas es el que más pesa: es casi cada acceso.

`ac_nombre_contrato` (36 filas) es el que la migración `2026_08_21_100002` borra
sin destino. Son pocas filas, pero **hay que decidir a mano** si se rescatan a
`ac_observaciones` o se aceptan como pérdida.

### 3.4 Inventario: v2 tiene los dos juegos de tablas

v1 trae `inv_productos`, `inv_listas_productos`, `inv_lista_producto_items`,
`inv_movimientos`, `inv_movimiento_detalles`. **v2 tiene esas mismas y además**
`inv_producto_catalogo`, `inv_lista`, `inv_lista_item`,
`inv_movimiento_cabecera`, `inv_movimiento_detalle` (singular).

Es el trabajo de `FASE1-INVENTARIO-UNIFICADO.md`. Antes de cargar 23.790
movimientos hay que resolver **a cuál de los dos juegos van**, o se cargan en las
tablas que el sistema nuevo ya no lee.

---

## 4. Las imágenes: **no están en el dump**

Respuesta directa a la pregunta: **no hay una sola columna BLOB en las 41 tablas.**
La base guarda solo el **nombre del archivo**; los archivos viven en el disco del
servidor de v1, en otra carpeta.

**Cuántos hay que buscar: ~41.913 archivos.**

| Tabla | Columna | Archivos |
|---|---|---:|
| `user_has_biometria` | `bio_image_name` | **12.664** (todas las filas) |
| `ronda_detalle` | `rd_foto` | **29.243** (de 37.616) |
| `novedad` | `nv_foto` | 6 |
| `bitacora` | `bt_foto` | 0 (tabla vacía) |

**Dónde buscarlas en el servidor de v1** — la convención (`generalTrait::storeFiles`)
es la misma en las dos versiones:

```
<raiz-del-proyecto>/public/images/<modulo>/<AAAA>/<MM>/<DD>/<archivo>
```

con `<modulo>` ∈ `biometria`, `rondas`, `accesos`, `novedad`, `bitacora`. El
nombre del archivo es `<user_id>_<ug_code>_<timestamp>.jpg`; ejemplo real:
`426_427_1788802977.jpg`.

> ### ⚠️ La ruta se **calcula desde la fecha del registro**, no se guarda
>
> ```php
> $fecha = Carbon::parse($this->nv_fecha_hora)->format('Y/m/d');
> $imagePath = public_path('images/novedad/' . $fecha . '/' . $this->nv_foto);
> if (file_exists($imagePath) && !empty($this->nv_foto)) { ... }
> return null;
> ```
>
> Hay que **copiar el árbol de carpetas tal cual**. Si se vuelcan los 42.000
> archivos en una sola carpeta, o se pierde un nivel, el accessor devuelve `null`
> y **las fotos desaparecen sin un solo error en el log**: el panel muestra la
> fila sin imagen, como si nunca hubiera habido foto.
>
> Y como la carpeta viene de la fecha del registro, **el ETL tiene que preservar
> las fechas originales** (`bio_created_at`, `rd_fecha`, `nv_fecha_hora`). Si se
> cargan con `now()`, las rutas apuntan al día de la migración y ninguna foto
> aparece.

Rango a copiar: **2025-04-07 a 2026-09-07**, unos 17 meses de carpetas diarias.

**Lo que hace falta de tu lado:** acceso al disco del servidor de v1 para copiar
`public/images/`. Con `du -sh public/images/` sabemos el peso antes de moverlo, y
conviene `rsync -a` (preserva estructura y fechas) y contar archivos en los dos
extremos.

---

## 5. Lo que no tiene origen, y arranca vacío

Nace sin datos, y está bien que así sea: son funciones que v1 no tenía.

`puesto`, `turno`, `plantilla`, `plantilla_franja`, `plantilla_asignacion`,
`turno_vacante`, `turno_postulacion`, `aviso_envio`, `user_has_pais`, `pais`,
`provincia`, `ciudad`, `acceso_historial`, `acceso_preregistro`,
`acceso_vehiculo`, `acceso_visitante`, `alertas_detalle`, `alertas_historial`.

Dos consecuencias operativas:

- **Cuadrantes y turnos hay que armarlos desde cero** para los 137 locales. Es
  trabajo de negocio, no de migración: no existe el dato en v1.
- **`user_has_pais` vacío significa que ningún Líder Operativo ve nada.**
  `PerfilPanel::localesDelUsuario()` devuelve `[]` (no `null`) para un líder sin
  países, y eso es deliberado: una configuración incompleta no debe convertirse
  en acceso global. Hay que poblarlo como parte de la carga.

---

## 6. Lo que sí calza sin problema

- **Ninguna colisión de cédulas** con los usuarios que creé en v2
  (`0912345678` admin, `1234567890` demo).
- **Ninguna cédula duplicada ni vacía** en las 878 filas de `users`.
- **44 correos repetidos**, pero v2 **no** tiene UNIQUE en `usu_email` ni en
  `usu_cedula`, así que no rompen la carga.
- `user_has_institucion` **sin pares duplicados** en 38.246 filas.
- Los 137 locales **todos** con `ins_so_code`, así que el rescate del cliente
  cubre el 100%.

---

## 7. Consecuencia del fail-open de la geocerca

Con lo corregido el 2026-09-07: si un local no tiene marcador activo, sus
marcajes entran como **ubicación no verificada** en vez de aceptarse en silencio.

Hay **118 marcadores para 137 locales**, así que hay locales sin marcador. Antes
de dar la asistencia por buena conviene mirar cuántos locales quedan sin punto
QR y cargarlos: si no, su asistencia entra marcada como no verificable — visible,
pero no auditable.

---

## 8. Qué falta para poder ejecutar

1. **`public/images/` del servidor de v1** (~42.000 archivos). Es lo único que no
   está en el dump y sin eso las fotos se pierden.
2. **Decidir Colombia y Chile**: ¿el catálogo de países queda Ecuador+Colombia
   (como sembró v2), Ecuador+Chile (como dice v1), o los tres?
3. **El local con ciudad `MANTENIMIENTO`**: a qué ciudad pertenece.
4. **`ac_nombre_contrato`** (36 filas): rescatar a observaciones, o aceptar la
   pérdida.
5. **Inventario**: a cuál de los dos juegos de tablas van los 23.790 movimientos.
6. **`log` y `log_trafico`** (2.902 filas): ¿migrar la auditoría vieja o empezar
   limpio?

Con eso definido, el ETL es escribible y verificable tabla por tabla.

---

## Anexo: el MariaDB de análisis

Quedó un contenedor `v1_analisis` (MariaDB 10.4) con el dump cargado, para poder
seguir consultando sin recargar 32 MB cada vez. **Tiene datos de producción.**
Cuando no se necesite:

```bash
docker rm -f v1_analisis
```
