# Aplicacion movil — Total Secure App

## Identificacion

| Dato | Valor | Fuente |
|---|---|---|
| Nombre | **Total Secure App** | `app.json` |
| Slug | `Dt360Core` | `app.json` |
| **Package name** | **`com.dt360.coreapp`** | `app.json` |
| Version actual | **1.0.2** | `app.json` |
| versionCode | **3** | `apk/LEEME-apk.txt` |
| Framework | **React Native + Expo** | `package.json`, `App.tsx` |
| Lenguaje | TypeScript | `tsconfig.json` |
| Plataforma | Android | directorio `android/` |
| iOS | **no** | no hay proyecto iOS |

## Permisos que solicita

Declarados en `app.json`:

| Permiso | Para que (por los modulos que usa) |
|---|---|
| `CAMERA` | biometria y evidencia fotografica |
| `RECORD_AUDIO` | grabacion de audio |
| `ACCESS_FINE_LOCATION` | ubicacion precisa en rondas |
| `ACCESS_COARSE_LOCATION` | ubicacion aproximada |
| `POST_NOTIFICATIONS` | avisos y alertas |

**Son permisos sensibles**: camara, microfono y ubicacion precisa. Coherentes
con el uso (control de rondas y biometria de guardias), pero conviene que el
personal sepa que la aplicacion los usa.

## Complementos de Expo

`expo-camera`, `expo-location`, `expo-notifications`, `expo-splash-screen`,
`expo-build-properties`.

## ⚠️ Trafico sin cifrar habilitado a proposito

`app.json` declara:

```json
"expo-build-properties", { "android": { "usesCleartextTraffic": true } }
```

Android **bloquea HTTP desde la version 9**. Esta opcion desactiva esa
proteccion, y es lo que permite que la app hable con
`http://181.198.245.50:3031`.

**Consecuencia:** credenciales, tokens, ubicaciones y **biometria** viajan
legibles. Ver SEC-01. Al pasar a HTTPS, esta linea debe retirarse y el APK
recompilarse.

## APK disponibles

En `apk/`:

| Archivo | Tamano | Fecha | Estado |
|---|---|---|---|
| `TotalSecureApp-v1.0.0-prod-20260908.apk` | 100 MB | 2026-09-08 07:28 | superado |
| `TotalSecureApp-v1.0.1-prod-20260908.apk` | 99 MB | 2026-09-08 11:52 | ⚠️ **NO USAR** |
| **`TotalSecureApp-v1.0.2-prod-20260909.apk`** | **53 MB** | 2026-09-09 02:05 | ✅ **vigente** |

### Por que la 1.0.1 no sirve

`LEEME-apk.txt` lo documenta con precision:

> *"El archivo se compilo el 2026-09-08 a las 11:52 y los arreglos entraron a
> las 13:45: estaban en el codigo y NO en el APK."*

Es decir: se compilo **antes** de que entraran las correcciones. Quien la
instale vera otra vez los seis problemas que la 1.0.2 corrige.

**Que reparte:** solo la **1.0.2**.

## Firma y verificacion

Todas las versiones estan firmadas con el **mismo certificado**, cuya huella
SHA-256 esta publicada en `LEEME-apk.txt`:

    e0:84:fd:69:65:aa:55:a2:45:81:8f:c6:4e:77:cf:1c:
    f7:de:16:28:e3:2c:34:9b:ca:87:44:6c:af:1c:96:a7

Verificar antes de repartir:

    apksigner verify --print-certs TotalSecureApp-v1.0.2-prod-20260909.apk

**Si la huella no coincide, ese APK no salio de este servidor.** Publicar la
huella es una buena practica que no aparece en ningun otro proyecto auditado.

Como comparten certificado, **la 1.0.2 se instala encima de las anteriores** sin
desinstalar y **sin borrar los datos locales de la tablet**.

## Keystore

`apk/totalsecureapp-release.jks`, permisos `600`. Manejo documentado en
`apk/LEEME-keystore.txt`.

⚠️ **Sin este archivo no se pueden publicar actualizaciones nunca mas.**
Perderlo obliga a republicar la app con otra identidad y reinstalarla en todos
los dispositivos. Debe tener copia fuera del servidor. Ver SEC-03.

## Instalacion en las tablets

1. Verificar la huella del APK (comando de arriba).
2. Copiar `TotalSecureApp-v1.0.2-prod-20260909.apk` a la tablet.
3. Permitir la instalacion desde origen desconocido.
4. Instalar. **No hace falta desinstalar la version previa**: el certificado es
   el mismo y los datos locales se conservan.

## Que trae la 1.0.2

Segun `LEEME-apk.txt`, seis correcciones:

1. Encabezado rehecho (`components/Encabezado.tsx`).
2. La pantalla de inicio hace scroll: antes era un `View` con `flex:1` y **los
   ultimos botones quedaban fuera de la pantalla**, inalcanzables.
3. El biometrico muestra la foto antes de guardar, con boton para repetirla.
4. **Boton EMERGENCIA** en Alertas, llamando a `/alert/crear`. Antes la
   pantalla era de solo lectura y **el boton de panico no existia**.
5. Inventario con colores explicitos: las letras no se veian.
6. `AppTheme` paso de `Theme.AppCompat.DayNight` a `.Light`, causa raiz del
   "no se ve el texto": el tema seguia al sistema mientras la app se declara
   clara.

Ademas, las **22 pantallas y componentes** usan la paleta de `utils/tema.ts`.

> El punto 4 merece atencion: **hasta la 1.0.2 el boton de panico no existia**
> en una aplicacion para guardias de seguridad. Conviene confirmar que todas
> las tablets fueron actualizadas.

## Funcionamiento sin conexion

El proyecto incluye `API-OFFLINE-SYNC.md`, que documenta la sincronizacion.
**Su comportamiento no se verifico**: `NO DETERMINADO`.

## Lo que NO se verifico

- Version minima de Android soportada: `NO DETERMINADO`.
- Comportamiento real de las 22 pantallas (requiere una tablet).
- Sincronizacion sin conexion.
- Si todas las tablets estan en 1.0.2.
- Notificaciones push (existe `google-services.json.example`, no el archivo
  real).
