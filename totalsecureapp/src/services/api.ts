import axios, { AxiosError, AxiosRequestConfig } from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_URLS, STORAGE_KEYS } from '../utils/constants';

const api = axios.create({
  baseURL: API_URLS[0],
  timeout: 15000,
});

/**
 * Cual de las URLs de API_URLS se esta usando.
 *
 * El servidor sale por dos enlaces de internet distintos (dos IP publicas). Si
 * el primero no responde, la app pasa al segundo sola y se queda ahi.
 *
 * Vive en memoria y NO en AsyncStorage a proposito: al reiniciar la app se
 * vuelve a intentar por la principal. Si se recordara el respaldo entre
 * arranques, una caida de un rato dejaria a la tablet pegada al enlace
 * secundario durante semanas sin que nadie se entere.
 */
let indiceActual = 0;

/**
 * ¿Es un fallo de RED y no una respuesta del servidor?
 *
 * Esta distincion es la que hace que el respaldo sea seguro: si el backend
 * contesta -- aunque sea 401, 422 o 500 -- **esta vivo**, y cambiar de host no
 * arregla nada; solo repetiria la operacion contra otra direccion. Se cambia
 * unicamente cuando no hubo respuesta: sin ruta, conexion rechazada o timeout.
 */
function esFalloDeRed(error: AxiosError): boolean {
  return !error.response;
}

api.interceptors.request.use(
  async (config) => {
    const token = await AsyncStorage.getItem(STORAGE_KEYS.TOKEN);
    if (token) {
      config.headers = config.headers ?? {};
      config.headers.Authorization = `Bearer ${token}`;
    }

    // El host vigente se aplica en cada peticion. El reintento pisa `baseURL`
    // en su propia config, y solo se respeta ese valor para no pelearse con el.
    if (config.baseURL === undefined || config.baseURL === API_URLS[0]) {
      config.baseURL = API_URLS[indiceActual];
    }

    return config;
  },
  (error) => Promise.reject(error)
);

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    if (error.response && error.response.status === 401) {
      await AsyncStorage.multiRemove([STORAGE_KEYS.TOKEN, STORAGE_KEYS.USER]);
      return Promise.reject(error);
    }

    const config = error.config as
      | (AxiosRequestConfig & { _hostsIntentados?: number })
      | undefined;

    if (config && API_URLS.length > 1 && esFalloDeRed(error)) {
      const intentados = config._hostsIntentados ?? 1;

      // Cada host se prueba UNA vez. Sin este tope, con los dos enlaces caidos
      // la promesa no se resolveria nunca y la pantalla quedaria cargando para
      // siempre en vez de mostrar el error al guardia.
      if (intentados < API_URLS.length) {
        indiceActual = (indiceActual + 1) % API_URLS.length;

        return api({
          ...config,
          _hostsIntentados: intentados + 1,
          baseURL: API_URLS[indiceActual],
        } as AxiosRequestConfig);
      }
    }

    return Promise.reject(error);
  }
);

export default api;
