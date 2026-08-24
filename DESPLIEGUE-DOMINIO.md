# Despliegue con dominio — totalsecureapp.com

Complementa `CHECKLIST-DESPLIEGUE-V2.md` (que cubre backup y migraciones). Este
documento cubre pasar de la red local al dominio.

**Nada de lo que hay aquí está activo todavía.** El entorno local sigue
funcionando igual: `docker compose up -d` en el `:3031` con HTTP. Los archivos de
producción son adicionales y hay que invocarlos explícitamente.

---

## 1. ¿Hay que redirigir el dominio al servidor? Sí, pero primero hay un problema

Sí: el dominio necesita registros DNS apuntando a la **IP pública** del servidor.
Pero hoy este servidor **no tiene IP pública**:

```
192.168.100.212/24   -> PRIVADA (detrás de NAT)
```

Es una dirección de red local. Un registro DNS no puede apuntar ahí: nadie desde
internet puede alcanzarla. Antes del DNS hay que resolver **cómo se expone el
servidor**, y hay tres caminos:

| Camino | Qué implica | Cuándo conviene |
|---|---|---|
| **A. IP pública fija del ISP** + port forwarding 80/443 en el router | Pedir IP fija al proveedor de internet y abrir dos puertos hacia `192.168.100.212` | El servidor se queda en las instalaciones |
| **B. Mover el backend a un VPS** | Contratar un VPS, desplegar ahí con el compose de producción | Es lo habitual para producción: IP pública, sin depender del router ni del corte de luz de la oficina |
| **C. Túnel** (Cloudflare Tunnel, Tailscale Funnel) | Sin abrir puertos ni IP fija; el túnel publica el servicio | Solución rápida o piloto; agrega una dependencia externa |

**Recomendación:** el camino B. El sistema va a recibir marcajes de guardias en
turnos nocturnos; un servidor detrás del router de la oficina depende de que no
se corte la luz ni cambie la IP del ISP. Si es un piloto corto, C es aceptable.

### Registros DNS (una vez que haya IP pública)

| Tipo | Nombre | Valor | TTL |
|---|---|---|---|
| A | `@` | IP pública del servidor | 300 |
| A | `api` | IP pública del servidor | 300 |
| CNAME | `www` | `totalsecureapp.com` | 300 |

Verificar antes de seguir, **desde fuera de la red local**:

```bash
dig +short totalsecureapp.com
dig +short api.totalsecureapp.com
```

Los dos deben devolver la IP pública. Si no resuelven, certbot va a fallar: su
validación consiste en que Let's Encrypt alcance el servidor por el dominio.

---

## 2. Orden de ejecución

El orden importa y **no** es intercambiable:

```
1. Exponer el servidor (camino A, B o C)
2. DNS apuntando y verificado con dig
3. Certbot: emitir certificados          <- necesita 1 y 2 listos
4. Backend: .env de producción
5. Levantar con el compose de producción
6. App: apiHost -> dominio, y recompilar el APK   <- último
```

El paso 6 va al final a propósito: en cuanto el APK apunte al dominio, deja de
funcionar contra la IP local. Mientras 1-5 no estén arriba, el APK actual
(`192.168.100.212:3031`) sigue siendo el que funciona.

---

## 3. Certbot — emisión inicial

La renovación queda automática en `docker-compose.prod.yml`, pero la **primera
emisión** es manual porque el nginx de producción no arranca sin certificados
(referencia archivos que aún no existen). Se emite con un nginx temporal:

```bash
cd totalsecureapp/backend

# nginx temporal solo para el desafío HTTP-01
docker run --rm -p 80:80 \
  -v certbot-web:/var/www/certbot \
  -v certbot-etc:/etc/letsencrypt \
  certbot/certbot certonly --standalone \
  -d totalsecureapp.com -d www.totalsecureapp.com -d api.totalsecureapp.com \
  --email TU-CORREO --agree-tos --no-eff-email
```

- [ ] `/etc/letsencrypt/live/totalsecureapp.com/fullchain.pem` existe
- [ ] Los tres dominios están en el mismo certificado (`certbot certificates`)

Los tres van juntos porque `prod.conf` usa un solo certificado para ambos vhosts.

---

## 4. Backend en producción

```bash
cd totalsecureapp/backend
cp .env.production.example .env        # ¡en el servidor real, no en el local!
# completar: APP_KEY, DB_PASSWORD, MAIL_*, ACCESS_PARAM_VALUE
docker compose run --rm backend php artisan key:generate

docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Lo que cambia respecto al local, y por qué importa:

- **`APP_DEBUG=false`** — el cambio más importante. Con `true`, cualquier error
  muestra el stack trace con rutas y credenciales a quien visite el sitio.
- **`SESSION_SECURE_COOKIE=true`** — cookies solo por HTTPS. Ojo: **con esto
  activado y el SSL no funcionando, no se puede entrar al panel.** Es la causa
  más común de "el login no hace nada" en un primer despliegue.
- **`TRUSTED_PROXIES=*`** — sin esto Laravel genera URLs `http://` detrás del
  proxy, y Filament carga sus assets por HTTP → el navegador los bloquea y el
  panel aparece sin estilos.
- **`APP_URL=https://totalsecureapp.com`** — de aquí salen los enlaces absolutos
  de los correos de cambio de contraseña.

Después de levantar:

```bash
docker compose run --rm backend php artisan config:cache
docker compose run --rm backend php artisan route:cache
```

- [ ] `https://totalsecureapp.com/acceso/login` carga con candado
- [ ] `https://api.totalsecureapp.com/api/login` responde JSON
- [ ] `http://totalsecureapp.com` redirige a HTTPS
- [ ] `https://api.totalsecureapp.com/admin` devuelve **404** (el panel no se
      sirve por el subdominio de la API)
- [ ] Provocar un error a propósito (una ruta inexistente) y confirmar que **no**
      aparece el stack trace de Laravel

---

## 5. App Expo — apuntar al dominio

No hace falta tocar código: `src/utils/constants.ts` ya construye la URL desde
`expo.extra`. Basta editar `app.json`:

```json
"extra": {
  "apiUrl": "https://api.totalsecureapp.com"
}
```

Eso produce `https://api.totalsecureapp.com/api`, **sin puerto** (443 implícito).
Alternativa equivalente: `{ "apiHost": "api.totalsecureapp.com", "apiScheme": "https" }`.

Sin ninguna de las dos, el comportamiento es el de hoy:
`http://192.168.100.212:3031/api`. La configuración local no se rompe.

### Apagar el tráfico en claro

Con HTTPS funcionando, quitar de `app.json`:

```json
["expo-build-properties", { "android": { "usesCleartextTraffic": true } }]
```

Mientras esté activo, la app acepta HTTP. Es lo que hoy permite usar la IP local,
y hay que dejarlo hasta que el dominio esté arriba; después es una puerta abierta
sin motivo.

### Recompilar

```bash
cd totalsecureapp
npx expo prebuild --platform android --clean
cd android && ./gradlew :app:assembleRelease
```

- [ ] El APK nuevo hace login contra el dominio **desde datos móviles** (no solo
      en la red local: eso es lo que prueba que sale a internet)

---

## 6. Notificaciones push — `google-services.json`

**Este archivo no se puede generar desde el repositorio.** Lo emite Firebase
Console para un proyecto concreto y contiene `project_id`, `api_key` y
`mobilesdk_app_id` reales. Un archivo inventado hace que el registro del token
falle en el dispositivo.

Lo que sí está preparado:

- `google-services.json` está en **`.gitignore`** (antes no lo estaba: si se
  hubiera dejado el archivo real en su lugar, se habría subido a GitHub con las
  claves del proyecto Firebase).
- `totalsecureapp/google-services.json.example` documenta la estructura esperada.
- El build **no** lo exige hoy: el APK de 99 MB se compiló sin él y el plugin de
  gradle no está aplicado.

### Cómo obtenerlo

1. Entrar a https://console.firebase.google.com/ y crear un proyecto (o usar uno
   existente).
2. Agregar una app **Android**. El *package name* debe ser exactamente:

   ```
   com.dt360.coreapp
   ```

   Es el `expo.android.package` de `app.json`. Si no coincide, FCM rechaza el
   registro.
3. Descargar `google-services.json` y dejarlo en `totalsecureapp/`.
4. Declararlo en `app.json`, **solo cuando el archivo ya exista**:

   ```json
   "android": {
     "package": "com.dt360.coreapp",
     "googleServicesFile": "./google-services.json"
   }
   ```

   ⚠️ Si se declara sin el archivo presente, `expo prebuild` **falla** con
   «Cannot copy google-services.json». Por eso esa línea no está puesta todavía.
5. `npx expo prebuild --platform android --clean` — el config plugin de Expo copia
   el archivo a `android/app/` y aplica el plugin de gradle automáticamente.
6. Recompilar el APK.

### El paso que falta y suele olvidarse

El backend **no** habla con FCM directamente: envía a la API de Expo
(`https://exp.host/--/api/v2/push/send`, en `ExpoNotificationService.php`). Para
que Expo pueda entregar a Android, hay que subirle las credenciales FCM del
proyecto Firebase:

- En Firebase Console: *Configuración del proyecto → Cuentas de servicio* →
  generar una clave privada (JSON).
- Cargarla en el proyecto de Expo (`eas credentials`, o el panel de
  expo.dev → Credentials → FCM V1).

**Con `google-services.json` pero sin este paso, la app obtiene su token push y
el backend cree que envió, pero la notificación no llega al teléfono.** Es
exactamente el síntoma que hay hoy.

Como la app no va a la Play Store por ahora, esto es lo único necesario: no hace
falta keystore propio ni ficha de tienda. El APK sigue firmado con el debug
keystore, que sirve para instalar por fuera pero **no** para publicar.

---

## 7. Resumen: qué puedo dejar hecho y qué necesita tus accesos

| Tarea | Estado |
|---|---|
| App preparada para HTTPS + dominio sin tocar código | ✅ hecho |
| `.env.production.example` con los valores y su explicación | ✅ hecho |
| nginx de producción con los dos vhosts y redirección | ✅ hecho |
| Compose de producción con 443 y renovación de certbot | ✅ hecho |
| `google-services.json` fuera del versionado + plantilla | ✅ hecho |
| Exponer el servidor (IP pública, VPS o túnel) | ⏳ decisión + tu proveedor |
| Registros DNS | ⏳ tu panel de dominio |
| Emisión inicial de certificados | ⏳ requiere DNS resolviendo + sudo |
| Proyecto Firebase y credenciales FCM en Expo | ⏳ tu cuenta |
| Rotar la credencial demo expuesta en el repo | ⏳ ver AGENTS.md |
