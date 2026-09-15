# Roadmap Total Secure App

**Fecha:** 2026-09-15 · **Base:** pendientes previos (auditoría 2026-09-15) +
observaciones de uso del cliente + carga de puestos y horarios.

Cada punto trae el **diagnóstico verificado en código**, no la descripción del
síntoma. Donde dice `NO VERIFICADO` es porque hacía falta consultar la base de
producción y no se hizo.

---

## Bloque 0 — Seguridad. Va primero porque está expuesto a internet

### 0.1 ✅ Toma de cualquier cuenta (SEC-00) — CERRADO (2026-09-15)

**Hecho y desplegado**, por las dos puertas: la API y el portal web tenían el
mismo agujero, y cerrar sólo una habría dejado la otra abierta.

- `App\Services\RecuperacionDeClave`: código de 8 dígitos con `random_int`,
  **guardado hasheado**, con vencimiento de 30 minutos, de un solo uso, e
  invalidado al quinto intento fallido. La comparación usa `hash_equals`.
- El código **ya no vuelve en la respuesta**, y la respuesta es idéntica exista o
  no la cédula, para que el endpoint no sirva para averiguar quién está
  registrado.
- `procesar_paswchg` ya no acepta `user_id`: identifica por cédula + código.
- En el portal, `cambiar_password/{codigo}` valida el enlace y deja al usuario
  autorizado **en la sesión**; `procesar_cambiopass` lo lee de ahí e ignora lo
  que venga en el formulario. Antes tomaba el `user_id` del propio formulario.
- Se entrega por WhatsApp o correo en cuanto alguno esté configurado. **Hoy no
  hay ninguno** (`mailhog` no existe y el gateway está vacío), así que el
  autoservicio queda sin efecto y el cambio lo hace el supervisor desde el panel,
  con la acción «Cambiar contraseña» de Usuarios, que ya existía. Decidido así: un
  autoservicio que entrega la cuenta a cualquiera es peor que no tenerlo.
- La app se actualizó al contrato nuevo (pide el código). **El APK 1.0.2 ya
  instalado no puede recuperar clave**: manda `user_id` y recibe error. Hay que
  recompilar.
- 15 tests nuevos (`RecuperacionDeClaveTest`), incluidos los dos que reproducen
  el agujero por cada puerta. Suite: **450 en verde**.

**De paso se corrigieron las dos validaciones muertas** que ya estaban
documentadas: `usu_password == Hash::make($password)` nunca era cierta (bcrypt
sala distinto cada vez), y la que comparaba el hash contra el texto plano
diciendo «no puede ser el usuario» quería comparar contra la cédula.

**Pendiente derivado:** `INFORME_AUDITORIA.md` y `docs/05_SEGURIDAD/` siguen
marcando SEC-00 como crítico abierto, con sus PDF y DOCX ya generados. Hay que
regenerarlos.

### 0.1-bis (diagnóstico original, para referencia)

`Modules/MobileApp/Routes/api.php:19` publica `procesar_paswchg` fuera de
`api.auth`. En `LoginController::procesar_cambiopass` (líneas 170-211) el método
lee `user_id`, `password`, `password2` y **nunca comprueba `remember_token`**.
El `user_id` es un entero secuencial. Cualquiera en internet cambia la
contraseña de cualquier usuario, incluido un Administrador.

Agrava: `solicitud_cambiopass` (líneas 153-160) devuelve `user_id` y `token` en
la respuesta JSON, y el token es `rand(1000, 10000000)` — 10 millones de
valores, sin expiración y sin un solo intento de aleatoriedad criptográfica.

**Arreglo:** exigir el token en `procesar_cambiopass` y compararlo con
`hash_equals`; generarlo con `random_bytes(32)`; no devolverlo en la respuesta;
darle caducidad (30 min) e invalidarlo al usarlo. Mitigación de hoy mismo, si el
arreglo no entra ya: cerrar las dos rutas en `docker/nginx/default.conf`.

### 0.2 🔴 Respaldo inexistente

Verificado en el crontab de `server-dt`: sólo hay respaldo de la ticketera, y
traccar tiene su propio timer. Total Secure App **no tiene ninguno**. Cuatro
cosas irrecuperables: la base (880 personas, 12.664 biometrías), los secretos,
la base V1 de `v1_analisis`, y `apk/totalsecureapp-release.jks` — sin ese
keystore no se puede volver a actualizar la app en ninguna tablet.

**Arreglo:** respaldo diario siguiendo el patrón de `respaldo-semanal.sh` de la
ticketera, y copia del keystore fuera del servidor.

### 0.3 🔴 Sin HTTPS

Biometría y credenciales de 880 personas viajan en claro. `docker-compose.prod.yml`
ya tiene nginx 1.27 + certbot escrito y sin usar. Requiere decidir dominio y
tocar el NAT del router. Luego hay que quitar `usesCleartextTraffic` del APK y
recompilar.

### 0.4 🟠 Fuga entre clientes en el panel

`RondaDetalleResource.php:131` e `InstitucionMarcadoresResource.php:159` filtran
sólo por el parámetro de la URL (`?ronda=`, `?codigo=`), sin consultar el perfil.
Un Supervisor cambia el número a mano y lee rondas de otro local — y en
Marcadores **edita las coordenadas del QR de otro cliente**. El patrón correcto
ya está resuelto al lado, en `InvMovimientoDetalleResource.php:155-173`.

### 0.5 Menores

- Cambiar la clave del admin `0912345678`, reasignada el 2026-09-09 para la
  validación del cliente.
- `Modules/Acceso/Models/users.php:66-69`: un `saving()` comentado que pisaba
  toda contraseña con `123456`. Borrarlo; está a un descomento de ser un
  incidente.
- `storage/logs/schedule-cron.log` sin rotación: 834 KB en seis días.

---

## Bloque 1 — Datos base: puestos y horarios

Sin esto, Turnos, Cuadrantes y Vacantes no tienen nada que mostrar, y varias
observaciones del Bloque 3 no se pueden ni probar.

### 1.1 Cargar los puestos

`puestos-propuesta.csv` (66 locales → 16 sitios) está validado por el cliente.
La tabla `puesto` existe (`pu_ins_code`, `pu_nombre`, `pu_lat`, `pu_lng`, único
por local+nombre). Carga directa con un seeder idempotente.

### 1.2 Cargar la malla de septiembre

`docs/HORARIO-SEPTIEMBRE2026.xlsx`, analizado. Contenido real:

| Hoja | Qué es |
|---|---|
| `ESTRUCTURA` (559 filas) | dotación contratada por cliente: puesto, horas de servicio, CECO, modalidad |
| `DOTACION` (45 filas) | resumen de puestos por tipo de jornada |
| 10 hojas de zona | **la malla**: NORTE, CENTRO, CEMENTERIO, C.C Y LOTERIA, REGIONAL, RANGER, SEMEDIC, CASA TOTAL, SUPERVISORES, DHL MECANO |
| `CONTROL FLOTANTE`, `HORARIO FLOTANTE` | personal de reemplazo |

Cada hoja de zona es una cuadrícula: puesto, guardia (nombres + apellidos) y una
letra por día. Vigencia **desde el 7 de septiembre**, 28 días (35 en RANGER y
DHL MECANO).

Volumen extraído: **~300 guardias** y **6.151 marcas de turno** reales.
Distribución de códigos:

`D` 2.726 · `N` 1.938 · `X` 1.487 · `A` 112 · `T` 56 · `D1` 54 · `C` 48 ·
`D2` 24 · `B` 24 · `Z` 24 · `V2` 5 · `P` 4

El destino es la tabla `turno`, una fila por guardia y día trabajado:
`tu_ins_code`, `tu_usu_id`, `tu_puesto_id`, `tu_fecha`, `tu_hora_inicio_prevista`,
`tu_hora_fin_prevista`, `tu_estado='programado'`.

**Tres cosas bloquean la carga y son del cliente, no técnicas:**

1. **El diccionario de marcas.** `D`, `N` y `X` se entienden (diurno, nocturno,
   libre) pero hacen falta las **horas exactas** de cada uno. Y `A`, `T`, `C`,
   `B`, `Z`, `D1`, `D2`, `V2`, `P` no están documentados en ninguna parte del
   archivo: son 351 turnos que no se pueden cargar a ciegas.
2. **El cruce de personas.** El Excel trae nombres y apellidos; la base
   identifica por cédula. El cruce por nombre tiene homónimos y hay que
   resolverlo caso por caso. `NO VERIFICADO`: cuántos de los ~300 guardias
   existen ya como usuario.
3. **El cruce de puestos.** Los nombres del Excel («Edificio ADM Sala»,
   «Parqueo Sala de Velación», «Sala Oratoria») hay que mapearlos contra los
   `ins_code` reales.

**Propuesta:** un comando `horarios:importar` con `--dry-run` que emita el
informe de lo que casa y lo que no, antes de escribir una sola fila. Nada entra
a la base de 880 personas sin que ese informe esté revisado.

---

## Bloque 2 — Bugs confirmados de las observaciones

Los cinco tienen causa raíz localizada.

### 2.1 ✅ El botón de pánico no avisa a nadie — RESUELTO (2026-09-15)

**Hecho y desplegado.** La migración `2026_09_15_100001_avisos_de_alerta` corrió
en producción (crea `notifications`, agrega `aviso_envio.ae_al_code`), se
publicaron los activos de Filament y se limpiaron las cachés de configuración y
vistas. Verificado después: `/acceso/login` 200, `/admin` 302 al login, el JS de
Livewire 200 por su ruta con hash, y las dos IP públicas en pie.

Lo que se hizo:

- `App\Services\NotificadorAlerta`, con el mismo diseño que `NotificadorVacante`:
  canales desde `config/avisos.php` y constancia de cada intento en `aviso_envio`.
- `App\Listeners\AvisarAlertaCreada`, registrado en `EventServiceProvider`. El
  evento se emitía desde el principio y **no lo escuchaba nadie**.
- `App\Services\Avisos\CanalPanel`, la campanita del panel, más
  `->databaseNotifications()` en `AdminPanelProvider` con sondeo de 10 s. Es el
  único canal que no depende de Firebase ni del gateway de WhatsApp.
- El aviso va a supervisores del local, Consola y Administradores; nunca al
  propio guardia que pidió auxilio. Lleva enlace a mapa sólo si el GPS respondió
  de verdad: la app manda `0/0` a propósito cuando no hay lectura.
- 11 tests nuevos (`AvisoDeAlertaTest`). Suite completa: **435 en verde**.

**Dos bugs que aparecieron al implementarlo:**

- `AlertaService::buscarSupervisorPorNivel()` usaba `whereHas('instituciones')`,
  una relación **que no existe en ninguno de los dos modelos `users`** — el mismo
  error que ya se había corregido en `asignarASupervisor` y que quedó sin
  corregir acá. **Escalar una alerta reventaba siempre.** Ya usa
  `user_has_institucion`.
- El evento se emitía **dentro** de la transacción: se avisaba de una alerta que
  aún podía no existir, y la transacción quedaba abierta durante los 8 s de
  timeout del gateway de WhatsApp. Ahora sale después del commit.

### 2.1-bis (diagnóstico original, para referencia)

**Es el más grave de este bloque: es un botón de emergencia que no emite la
emergencia.**

La app envía bien (`AlertasScreen.tsx:100`, a `ALERT_CREAR`) y el backend guarda
bien — por eso quedó el registro. Lo que falla es el aviso:
`AlertaService.php:45` emite `event(new AlertaCreada($alerta))`, la clase
`App\Events\AlertaCreada` es un `ShouldBroadcast`, y **el `.env` tiene
`BROADCAST_DRIVER=log`**. El evento se escribe en un archivo de log y ahí muere.
No hay ningún listener registrado, y `AlertasActivasWidget` es un contador del
tablero que sólo cambia si alguien abre el panel y refresca.

**Arreglo:** un listener que notifique de verdad — WhatsApp al supervisor
asignado (el gateway ya existe y funciona) y notificación de Filament en el
panel. Encender un driver de broadcasting real es opcional; el aviso al
supervisor no lo es. Ojo con `QUEUE_CONNECTION=sync`: mientras la cola sea
síncrona, el envío del WhatsApp retrasa la respuesta al guardia.

### 2.2 🔴 El QR no sale en el PDF

Sale la plantilla y el recuadro, sin el código. Causa: `generalTrait.php:209`
genera el QR como data URI (`data:image/png;base64,...`) y la plantilla lo
inserta en un `<img>` (`pointcontrol.blade.php:92`). Pero **dompdf es la v3.1.6,
y desde la 2.0 el esquema `data:` quedó bajo el control de `enable_remote`**,
que en `config/dompdf.php:287` está en `false` — apagado a propósito, y bien, por
SSRF. dompdf entonces descarta la imagen en silencio y dibuja el resto.

**Arreglo:** no encender `enable_remote`. Escribir el PNG del QR a un archivo
temporal y referenciarlo por ruta local, o habilitar únicamente el protocolo
`data://` en `allowed_protocols`.

### 2.3 🟠 Las novedades creadas desde la web no guardan foto

`NovedadResource.php:48`: `public static function form(Schema $schema): Schema
{ return $schema->schema([ ]); }` — **el formulario está vacío**, y sin embargo
existen las páginas `CreateNovedad` y `EditNovedad`. Se guarda el registro sin
foto y sin ningún otro campo porque no hay dónde ponerlos.

**Arreglo:** escribir el formulario, con `FileUpload` que respete la convención
de `public/images/novedad/<fecha>/` que ya usa el modelo (`Novedad.php:49`).

### 2.4 🟠 Preregistro: no se puede escribir la hora

`PreregistroFormScreen.tsx:103-116`: fecha y hora son `TextInput` con
`keyboardType="numeric"`. El teclado numérico de Android **no trae `:` ni `-`**,
así que efectivamente «sólo salen números» y es imposible separar horas de
minutos. El campo ya está separado en la base (`apr_fecha_estimada` y
`apr_hora_estimada` son dos columnas).

**Arreglo:** selectores nativos de fecha y hora en lugar de texto libre.

### 2.5 🟡 «Quiero cubrir turnos extra» no se refleja entre web y app

El campo `usu_acepta_extras` existe, está en el `fillable` y la API lo lee y
escribe (`VacanteController.php:320`). Pero **no hay ningún control en el panel
web**: `UsersResource` sólo tiene el toggle de WhatsApp. Y la app recibe el valor
únicamente en la respuesta del login (`LoginController.php:118`), así que un
cambio hecho fuera no llega hasta que el guardia vuelve a entrar.

**Arreglo:** el toggle en el panel — y el cliente pide que viva en el **módulo de
Turnos**, no en Perfil — más refresco del valor al abrir la pantalla de Vacantes,
para que no dependa de volver a iniciar sesión.

---

## Bloque 3 — Mejoras funcionales pedidas

### App

- **3.1 Flujo posterior al login.** Hoy: login → selección de perfil →
  selección de local. Se pide llegar antes al local, y que de ahí se entre a
  inventario. `A CONFIRMAR`: si la selección de perfil debe saltarse cuando el
  guardia tiene un solo perfil, y si inventario reemplaza al Home o es sólo el
  atajo tras elegir local.
- **3.2 Botón de cargar coordenadas GPS en biometría.** Hoy
  (`BiometriaScreen.tsx:85-96`) si el GPS falla se aborta el marcaje y **se
  pierde la foto ya tomada**. Un botón de reintento explícito, con la lectura y
  su precisión a la vista antes de enviar.
- **3.3 Menú lateral desplegable** con todos los módulos y un icono por módulo.
- **3.4 Novedades con filtro de días.** El vigilante ya las ve, pero
  `NovedadListScreen.tsx:44` pide siempre `date:` de un solo día — el de hoy.
  Falta el rango.
- **3.5 Ocultar el código de acceso en Perfil** (`PerfilScreen.tsx:72`).

### Panel web

- **3.6 Apartado de «personas dentro»** en Accesos: los que tienen entrada
  registrada y todavía no marcaron salida.
- **3.7 Búsqueda por nombre, cédula o placa en el panel de salida**, para cerrar
  el acceso sin buscar a mano en el listado.
- **3.8 Mover «cubrir turnos extra» al módulo de Turnos** (ver 2.5).

---

## Bloque 4 — Deuda anterior, sigue abierta

- **4.1 Miniaturas de fotos.** La primera página de Accesos baja **21,5 MB**
  para dibujar cinco círculos de 35 px: `ImageColumn->width()` es sólo CSS y el
  navegador se trae el original. 32 fotos pasan de 3 MB (165 MB, el 20% del
  almacenamiento).
- **4.2 Instalar y probar el APK 1.0.2 en tablet** (`adb install -r`, sin
  desinstalar, misma firma). El archivo está verificado; nadie lo ha ejecutado.
  Hasta 1.0.2 **el botón de EMERGENCIA no existía**: hay que censar qué versión
  tiene cada tablet.
- **4.3 Worker de cola.** `QUEUE_CONNECTION=sync`; bloquea 2.1.
- **4.4 Healthchecks** de backend y nginx (hoy sólo los tiene la base).
- **4.5 Sembrar una fila por cada uno de los 27 recursos** en los tests del
  panel: con las tablas vacías, una acción de fila rota se dibuja perfecta.
- **4.6 `openapi.yaml`** sin contrastar contra `route:list`.
- **4.7 `v1_analisis`**, MariaDB fuera de todo compose y de todo respaldo.
- **4.8 Las 57 tablas** sin documentar una por una.
- **4.9 Puerto 3031** abierto a toda la LAN.
- **4.10 Decisión de negocio:** si bloquear el marcaje en locales sin
  marcadores. Hoy se acepta y se marca como no verificado.
- **4.11 18 nombres** que la migración omitió por tener el desglose corrupto.

---

## Orden sugerido

1. **0.1** (toma de cuentas) y **2.1** (el botón de pánico que no avisa). Uno
   es la puerta abierta; el otro es seguridad física de los guardias.
2. **0.2** respaldos — es lo único irrecuperable si algo se cae hoy.
3. **1.1** y **1.2**, en cuanto lleguen el diccionario de marcas y el criterio
   de cruce de personas. Desbloquea Turnos, Cuadrantes y Vacantes.
4. **0.4**, **2.2**, **2.3**, **2.4**, **2.5** — bugs acotados, cada uno con su
   causa ya localizada.
5. **0.3** HTTPS, coordinando la ventana con el cliente por el NAT.
6. Bloque 3, agrupado en una sola versión del APK para no repetir la
   instalación en tablet.
7. Bloque 4.

## Lo que hace falta del cliente

1. **Horas exactas** de `D` y `N`, y qué significan `A`, `T`, `C`, `B`, `Z`,
   `D1`, `D2`, `V2`, `P` (351 turnos dependen de esto).
2. **Cómo cruzar los guardias del Excel con la base**: ¿hay un listado con
   cédulas, o se resuelve por nombre con revisión manual?
3. **3.1**: si la selección de perfil se salta con un solo perfil, y qué papel
   cumple inventario en el arranque.
4. Dominio para el certificado (0.3).
