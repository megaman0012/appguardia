import { useRef } from 'react';

/**
 * Idempotencia de los registros que se crean en campo.
 *
 * **El problema.** Un guardia sin señal toca «Guardar», la petición falla y
 * vuelve a tocar. Sin nada que identifique al evento, el servidor recibe dos
 * peticiones indistinguibles y crea dos registros. El servidor resuelve esto
 * con un `client_uuid` por evento: el primero crea, los siguientes devuelven el
 * mismo id con `duplicado: true`.
 *
 * ⚠️ **El uuid tiene que sobrevivir al reintento.** Es el punto entero. Antes
 * `VacantesScreen` lo armaba como `${tv_id}-${Date.now()}`: cambiaba en cada
 * envío, así que el servidor veía un evento nuevo cada vez y la deduplicación
 * no hacía nada. `useIdempotencia` guarda el uuid por acción y solo lo suelta
 * cuando el servidor confirma.
 */

/**
 * UUID v4. No hay `crypto.randomUUID` en Hermes ni paquete de uuid en el
 * proyecto, así que se arma a mano con el formato RFC 4122 -- el backend valida
 * `uuid` y rechaza cualquier otra cosa.
 */
export function nuevoUuid(): string {
  const hex = '0123456789abcdef';
  let s = '';

  for (let i = 0; i < 36; i++) {
    if (i === 8 || i === 13 || i === 18 || i === 23) {
      s += '-';
    } else if (i === 14) {
      // Versión 4.
      s += '4';
    } else if (i === 19) {
      // Variante: 8, 9, a o b.
      s += hex[(Math.floor(Math.random() * 16) & 0x3) | 0x8];
    } else {
      s += hex[Math.floor(Math.random() * 16)];
    }
  }

  return s;
}

/**
 * Momento actual del dispositivo, **con el offset de su zona horaria**.
 *
 * ⚠️ No usar `toISOString().slice(0, 19).replace('T', ' ')`. Eso produce hora
 * UTC sin decir que es UTC; el servidor la lee como hora local de Guayaquil, la
 * ve 5 horas en el futuro y la recorta a la hora de llegada -- justo el dato que
 * se quería preservar. Con el offset incluido el servidor lo convierte bien
 * desde cualquier zona.
 */
export function ahoraDelDispositivo(): string {
  const d = new Date();
  const dosDigitos = (n: number) => String(n).padStart(2, '0');

  // getTimezoneOffset devuelve minutos a restar de la hora local para llegar a
  // UTC: para -05:00 devuelve 300, con el signo invertido.
  const offset = -d.getTimezoneOffset();
  const signo = offset >= 0 ? '+' : '-';
  const abs = Math.abs(offset);

  return (
    `${d.getFullYear()}-${dosDigitos(d.getMonth() + 1)}-${dosDigitos(d.getDate())}` +
    `T${dosDigitos(d.getHours())}:${dosDigitos(d.getMinutes())}:${dosDigitos(d.getSeconds())}` +
    `${signo}${dosDigitos(Math.floor(abs / 60))}:${dosDigitos(abs % 60)}`
  );
}

/**
 * Un uuid estable por acción, hasta que el servidor la confirma.
 *
 * ```ts
 * const { uuidPara, confirmar } = useIdempotencia();
 * // ...
 * await api.post(url, { ...payload, client_uuid: uuidPara('recepcion') });
 * confirmar('recepcion'); // recién acá se suelta
 * ```
 *
 * Si la petición falla, el uuid NO se suelta: el siguiente intento manda el
 * mismo y el servidor lo reconoce como reintento en vez de crear otro registro.
 */
export function useIdempotencia() {
  const uuids = useRef<Record<string, string>>({});

  const uuidPara = (clave: string): string => {
    if (!uuids.current[clave]) {
      uuids.current[clave] = nuevoUuid();
    }

    return uuids.current[clave];
  };

  const confirmar = (clave: string): void => {
    delete uuids.current[clave];
  };

  return { uuidPara, confirmar };
}
