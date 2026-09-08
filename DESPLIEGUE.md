# Despliegue: cómo sale el sistema a internet

> **Cuál de los tres documentos necesitas**
>
> - **Este** — cómo está publicado hoy: reparto del puerto 80 entre proyectos, el
>   3031 del APK, qué reenvía el router, y los pendientes de seguridad.
> - **`DESPLIEGUE-DOMINIO.md`** — cuando haya dominio: DNS, certbot y HTTPS. Es el
>   siguiente paso pendiente.
> - **`CHECKLIST-DESPLIEGUE-V2.md`** — procedimiento para aplicar las migraciones
>   de fase sobre una base que ya tiene datos, con respaldo obligatorio y rollback.
>   La migración de v1 **no** se hizo por ahí (ver `ANALISIS-MIGRACION-V1.md`),
>   pero su procedimiento de respaldo sigue siendo el bueno.

## Dos puertas, y para qué es cada una

| Entra por | Sirve | Quién lo usa |
|---|---|---|
| **80** (nginx del host, por nombre) | el panel web | personas, desde el navegador |
| **3031** (publicado por Docker) | la API y el panel | **el APK de las tablets** |

El 80 lo reparte el nginx del host entre varios proyectos; el 3031 va directo al
contenedor. El APK sale por el **3031** por decisión del 2026-09-08, asi que se
saltea el nginx del host: es mas simple y no depende del reparto del 80 con la
v1, pero deja fuera el unico lugar donde despues van TLS, limites y logs.

Situación de partida (2026-09-07): la **v1** del proyecto ocupa el puerto 80 en
**otro servidor**, y esta v2 tiene que salir igual. Hay **dos IP públicas**, y
todavía no hay dominio ni certificado.

Este documento es el estado real, qué quedó configurado, y **qué falta hacer
fuera de este servidor**.

---

## 1. Cómo está repartido el puerto 80 hoy

El puerto 80 de este servidor **ya lo administra el nginx del host**, que reparte
por nombre entre varios proyectos. No había que inventar nada: Total Secure App
es el tercer inquilino.

| Entra por | Va a | Archivo |
|---|---|---|
| `servidesk.totalpacificgroup.com` | `127.0.0.1:3011` (ticketera) | `/etc/nginx/conf.d/servidesk.conf` |
| `tecdesk.totalpacificgroup.com` | `127.0.0.1:3001` (coordinador) | `/etc/nginx/conf.d/tecdesk.conf` |
| `181.188.232.50`, `181.198.245.50`, `192.168.3.124` | `127.0.0.1:3031` (**este proyecto**) | `/etc/nginx/conf.d/totalsecureapp.conf` |
| cualquier otro nombre | página de bienvenida de nginx | bloque `_` de `nginx.conf` |

La copia versionada del vhost está en
`totalsecureapp/backend/deploy/nginx-host-totalsecureapp.conf`. **Copiarla, no
enlazarla**: SELinux no deja a nginx seguir enlaces al home del usuario.

```bash
sudo cp totalsecureapp/backend/deploy/nginx-host-totalsecureapp.conf \
        /etc/nginx/conf.d/totalsecureapp.conf
sudo nginx -t && sudo systemctl reload nginx
```

> **`nginx -t` antes de recargar, siempre.** Este nginx sirve tres proyectos: un
> error de sintaxis no rompe solo este, deja los tres sin puerto 80.

**Responde por IP a propósito.** Sin dominio, el APK apunta a la IP y manda
`Host: 181.188.232.50`; nginx casa por `server_name` igual que con un nombre
(ignora el puerto del `Host` al comparar). Están las dos IP públicas porque cuál
reenvía el router se decide afuera, y así funciona con cualquiera.

**El vhost NO lleva `default_server`.** Ese lo tiene el bloque `_` de
`nginx.conf`, y declarar un segundo en el mismo `listen` **impide arrancar nginx
entero**, tumbando también los otros dos proyectos.

---

## 2. Lo que falta, y no se puede hacer desde este servidor

Este servidor está detrás de NAT: su única dirección es **192.168.3.124/24**, con
gateway 192.168.3.1. **Las dos IP públicas viven en el router, no aquí.** Nada de
lo que se configure en este servidor las alcanza.

**Hay que reenviar, en el router:**

```
181.188.232.50 : 80   ->   192.168.3.124 : 80
```

Se usa la **secundaria** porque el `:80` de la primaria lo tiene la v1. Si se
prefiere al revés, o si esa IP no se puede reenviar, hay dos salidas:

- **Otro puerto público hacia el 80 de aquí:** p. ej. `181.198.245.50:8080 ->
  192.168.3.124:80`. Entonces en `app.json` va `"apiPort": 8080`. Sigue pasando
  por el nginx del host, que es lo que se quería.
- **Al 3031 directo:** `181.188.232.50:80 -> 192.168.3.124:3031`. Funciona, pero
  se salta el nginx del host — o sea, se pierde el único lugar donde después van
  TLS, límites y logs. No recomendado.

Para comprobar que el reenvío quedó bien, desde **fuera** de la red:

```bash
curl -I http://181.188.232.50/acceso/login    # debe dar 200
```

Desde dentro de la red eso no prueba nada: casi ningún router hace *hairpin* NAT.

---

## 3. El APK con IP y puerto: sí funciona

**Sí.** No hace falta dominio ni HTTPS para que el APK hable con el backend.

- `src/utils/constants.ts` acepta `apiUrl` (override completo), o `apiHost` +
  `apiScheme` + `apiPort`. **No hay puerto quemado en el código.**
- `usesCleartextTraffic: true` (plugin `expo-build-properties`) es lo que lo hace
  posible: **Android 9+ bloquea HTTP sin cifrar por defecto** y sin esa bandera
  la app fallaría con un error de red que no menciona el certificado.

Configuración actual de `app.json` → la app arma `http://181.188.232.50:80/api`:

```json
"extra": {
  "apiHost": "181.188.232.50",
  "apiScheme": "http",
  "apiPort": 80
}
```

> **`apiPort` hay que ponerlo explícito.** Con `apiScheme: "http"` y sin
> `apiPort`, `constants.ts` cae al **3031** por defecto y la app apuntaría al
> puerto equivocado.

Después de cambiar `app.json` hay que **recompilar**: en el APK release el JS va
embebido y no se lee de la red.

```bash
cd totalsecureapp/android && ./gradlew :app:assembleRelease
# -> android/app/build/outputs/apk/release/app-release.apk
```

### Los dos riesgos de ir por IP

1. **Todo viaja en claro.** Cédula, contraseña, fotos de los marcajes y
   coordenadas GPS, sin cifrar, por internet. Cualquiera en el camino —el wifi
   del local, la red móvil, el ISP— lo lee y puede reusar la contraseña. Mientras
   sea una prueba controlada se puede convivir; **para operación real con
   guardias, no.**
2. **La IP queda quemada en el APK.** Si el ISP la cambia, **todas las tablets
   dejan de funcionar a la vez** y hay que recompilar y reinstalar una por una.
   Con un dominio eso se arregla con un registro DNS y nadie toca las tablets.

### La salida, cuando se pueda

Un subdominio de `totalpacificgroup.com` —el patrón que ya usan `servidesk` y
`tecdesk`— resuelve **las dos cosas de golpe**: quita la IP del APK y habilita
certificado de Let's Encrypt. El repo ya trae lo necesario
(`docker-compose.prod.yml` con certbot, `docker/nginx/prod.conf`, y
`DESPLIEGUE-DOMINIO.md`). **Let's Encrypt no emite certificados para una IP**, así
que sin dominio no hay HTTPS posible.

Con dominio, en `app.json` basta:

```json
"extra": { "apiUrl": "https://guardias.totalpacificgroup.com" }
```

---

## 4. Pendiente de seguridad: el 3031 está abierto

**El puerto 3031 acepta conexiones desde cualquier origen**, no solo desde el
nginx del host.

`firewall-cmd --query-port=3031/tcp` responde `no`, y eso **engaña**: Docker
publica los puertos con reglas DNAT en `iptables` que viajan por la cadena
`FORWARD`, no por `INPUT`, así que **se saltan la lista de puertos de firewalld**.
Comprobado:

```
$ sudo iptables -t nat -L DOCKER -n | grep 3031
DNAT  tcp -- 0.0.0.0/0  0.0.0.0/0  tcp dpt:3031 to:172.25.0.4:80
```

Compárese con Postgres, que **sí** está bien acotado (`to:172.25.0.2:5432` solo
para destino `127.0.0.1`), porque el compose lo publica como
`"127.0.0.1:5434:5432"`.

Hoy el alcance es la **red local**; desde internet solo si el router reenvía ese
puerto. Aun así conviene cerrarlo: con el puerto 80 como puerta, el 3031 no tiene
que estar accesible a nadie más.

**El arreglo es una línea** en `totalsecureapp/backend/docker-compose.yml`:

```yaml
  nginx:
    ports:
      - "127.0.0.1:3031:80"     # en vez de "3031:80"
```

```bash
docker compose up -d    # recrea solo ts_nginx
```

> **Ojo antes de aplicarlo:** cualquier tablet o navegador que hoy apunte a
> `192.168.3.124:3031` **deja de funcionar** en ese momento. Hacerlo *después* de
> repuntar todo al puerto 80, no antes.

Mientras el 3031 siga abierto hay un efecto secundario: quien lo alcance directo
puede **falsear su IP** en el log de tráfico mandando `X-Forwarded-For`, porque
`TrustProxies` confía en los rangos privados (ver abajo). Afecta al log, no a la
autenticación.

---

## 5. `TrustProxies`: por qué había que tocarlo

`app/Http/Middleware/TrustProxies.php` traía **una sola IP quemada,
`10.72.80.119`**, de una red que este servidor no tiene: resto de la instalación
original. Con ella **ningún proxy era de confianza** y las cabeceras
`X-Forwarded-*` se descartaban.

La cadena real es:

```
cliente -> nginx del host (:80) -> 127.0.0.1:3031 -> docker-proxy -> ts_nginx -> php-fpm
```

**PHP nunca ve al cliente**: ve a `ts_nginx`, con una IP de la red Docker que
**cambia al recrear el stack**. Por eso ahora van rangos privados y no
direcciones fijas.

Qué se rompía sin esto:

- `$request->ip()` devolvía la IP del contenedor **para todos**, y el log de
  tráfico dejaba de servir para nada.
- Y lo que habría costado más de encontrar: **en cuanto se ponga HTTPS**,
  `url()` generaría enlaces `http://` porque no se creería el
  `X-Forwarded-Proto` → contenido mixto en el panel, y el correo de cambio de
  contraseña apuntando a `http`.

---

## 6. Verificado el 2026-09-07

Todo contra el sistema corriendo, no contra el código:

| Prueba | Resultado |
|---|---|
| `nginx -t` tras agregar el vhost | ✅ |
| `GET /` con `Host: 181.188.232.50` | ✅ 302 → `http://181.188.232.50/acceso/login` (conserva la IP pública) |
| `GET /acceso/login` por el 80 | ✅ 200 |
| `POST /api/login` por el 80 | ✅ token emitido |
| `servidesk` y `tecdesk` tras recargar nginx | ✅ 200 los dos, sin tocarlos |

Lo que **no** se pudo probar desde aquí, porque depende del router: que
`181.188.232.50:80` llegue a este servidor. Eso se comprueba desde fuera de la
red con el `curl` de la sección 2.
