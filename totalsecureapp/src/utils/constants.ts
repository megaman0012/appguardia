// Constantes de la aplicacion
import Constants from 'expo-constants';

// Puerto del backend en desarrollo (nginx del docker-compose local).
// En produccion con HTTPS no se usa: el 443 es implicito.
const API_PORT_DEFECTO = 3031;

interface ExtraConfig {
  /** Override completo, p. ej. "https://api.totalsecureapp.com". Gana sobre todo lo demas. */
  apiUrl?: string;
  /**
   * Varios hosts, el primero es el principal.
   *
   * Sirve cuando el servidor sale por dos enlaces de internet distintos: si el
   * primero no responde POR RED, `api.ts` reintenta con el siguiente y se queda
   * con el que funciono. Gana sobre `apiHost`.
   */
  apiHosts?: string[];
  /** Host o dominio del backend. Se usa si no hay `apiHosts`. */
  apiHost?: string;
  /** 'http' (defecto) o 'https'. */
  apiScheme?: string;
  /** Puerto explicito. Con https se omite salvo que se indique. */
  apiPort?: number | string | null;
}

function getExtra(): ExtraConfig {
  try {
    return (Constants.expoConfig?.extra ?? {}) as ExtraConfig;
  } catch (e) {
    return {};
  }
}

function getHost(): string {
  try {
    const extraHost = getExtra().apiHost;
    if (extraHost) {
      return extraHost;
    }
    const hostUri = Constants.expoConfig?.hostUri || Constants.expoGoConfig?.debuggerHost;
    if (hostUri) {
      return hostUri.split(':')[0];
    }
  } catch (e) {
    // ignorar y usar localhost
  }
  return 'localhost';
}

/**
 * URL base de la API.
 *
 * Sin configuracion extra se comporta igual que siempre:
 * http://<apiHost o hostUri>:3031/api
 *
 * Para produccion con dominio basta editar `expo.extra` en app.json, sin tocar
 * este archivo:
 *   { "apiUrl": "https://api.totalsecureapp.com" }
 * o bien:
 *   { "apiHost": "api.totalsecureapp.com", "apiScheme": "https" }
 *
 * Con apiScheme 'https' el puerto se omite (443 implicito) salvo que se pase
 * apiPort explicitamente.
 */
function construirApiUrl(): string {
  const extra = getExtra();

  if (extra.apiUrl) {
    // Se normaliza para tolerar que venga con o sin '/api' y con o sin barra final.
    const base = String(extra.apiUrl).replace(/\/+$/, '');
    return base.endsWith('/api') ? base : `${base}/api`;
  }

  const scheme = extra.apiScheme === 'https' ? 'https' : 'http';

  let puerto = '';
  if (extra.apiPort !== undefined && extra.apiPort !== null && extra.apiPort !== '') {
    puerto = `:${extra.apiPort}`;
  } else if (scheme === 'http') {
    puerto = `:${API_PORT_DEFECTO}`;
  }

  return `${scheme}://${getHost()}${puerto}/api`;
}

/**
 * Todas las URLs base, en orden de preferencia.
 *
 * Con `apiHosts` en app.json hay una por host; sin eso, una sola. `api.ts`
 * arranca por la primera y solo pasa a la siguiente si hay error DE RED.
 */
function construirApiUrls(): string[] {
  const extra = getExtra();

  // Un override completo no admite lista: es una URL y punto.
  if (extra.apiUrl) {
    return [construirApiUrl()];
  }

  const hosts = Array.isArray(extra.apiHosts)
    ? extra.apiHosts.filter((h) => typeof h === 'string' && h.length > 0)
    : [];

  if (hosts.length === 0) {
    return [construirApiUrl()];
  }

  const scheme = extra.apiScheme === 'https' ? 'https' : 'http';

  let puerto = '';
  if (extra.apiPort !== undefined && extra.apiPort !== null && extra.apiPort !== '') {
    puerto = `:${extra.apiPort}`;
  } else if (scheme === 'http') {
    puerto = `:${API_PORT_DEFECTO}`;
  }

  return hosts.map((h) => `${scheme}://${h}${puerto}/api`);
}

export const API_URLS = construirApiUrls();

/** La principal. Se mantiene para no romper lo que ya la importaba. */
export const API_URL = API_URLS[0];

export const API_ENDPOINTS = {
  AUTH: {
    LOGIN: '/login',
    SOLICITUD_PASS: '/solicitud_paswchg',
    PROCESAR_PASS: '/procesar_paswchg',
  },
  PERFIL: {
    SELECCIONAR: '/seleccionar_perfil',
    PROCESAR: '/procesar_perfil',
  },
  INSTITUCIONES: '/instituciones',
  BIOMETRIA: '/biometria',
  TURNOS: {
    DEL_DIA: '/turnos-del-dia',
    VINCULAR_MARCAJE: '/turnos-vincular-marcaje',
    CUMPLIMIENTO: '/turnos-cumplimiento',
  },
  VACANTES: {
    DISPONIBLES: '/vacantes-disponibles',
    POSTULAR: '/vacantes-postular',
    RETIRAR: '/vacantes-retirar',
    MIS_POSTULACIONES: '/vacantes-mis-postulaciones',
    MIS_PROXIMOS_TURNOS: '/turnos-proximos',
    AVISAR_AUSENCIA: '/turnos-avisar-ausencia',
    ACEPTAR_EXTRAS: '/perfil-extras',
  },
  RONDAS: {
    LIST: '/rondas',
    GESTION: '/rondas_gestion',
    DETALLE: '/rondas_detalle',
    DETALLE_GESTION: '/rondas_detalle_gestion',
    DETALLE_QRCODE: '/rondas_detalle_qrcode',
  },
  ACCESO: {
    REGISTRAR: '/acceso',
    LIST_BY_INST: '/accesosbyinst',
    SALIDA: '/accesout',
    PREREGISTRO_CREATE: '/acceso/preregistro',
    PREREGISTRO_LIST: '/acceso/preregistros',
    PREREGISTRO_CANCEL: '/acceso/cancelar-preregistro',
  },
  NOVEDAD: {
    CREATE: '/novedad_create',
    LIST_BY_DATE: '/novedad_listbydate',
  },
  INVENTARIO: {
    LIST_BY_INST: '/inventario/listbyinst',
    LIST_SAVE: '/inventario/listsave',
    FINISH_SAVE: '/inventario/finishsave',
  },
  NOTIFICACION: {
    TOKEN_SAVE: '/token/save',
    TOKEN_REMOVE: '/token/remove',
    ALERT_TODAY: '/alert/today',
    // El endpoint existia en el backend desde el principio y la app no lo
    // llamaba nunca: la pantalla de Alertas era solo lectura, sin forma de
    // generar una. En una app para guardias de seguridad, avisar de una
    // emergencia es la funcion mas importante que hay.
    ALERT_CREAR: '/alert/crear',
  },
};

// Ambiente de notificaciones push. Debe coincidir con PUSH_ENV del backend (por defecto 'prod').
export const PUSH_ENV = 'prod';

export const APP_NAME = 'Total Secure App';
export const STORAGE_KEYS = {
  TOKEN: 'token',
  USER: 'user',
};
