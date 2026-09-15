export const formatDateTime = (value?: string | null): string => {
  if (!value) return '';
  const d = new Date(value.replace(' ', 'T'));
  if (isNaN(d.getTime())) return value;
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yyyy = d.getFullYear();
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  return `${dd}/${mm}/${yyyy} ${hh}:${mi}`;
};

export const today = (): string => {
  const d = new Date();
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yyyy = d.getFullYear();
  return `${yyyy}-${mm}-${dd}`;
};

/*
 * Máscaras para escribir fecha y hora con el teclado numérico.
 *
 * ⚠️ El teclado numérico de Android **no trae `-` ni `:`**. Los campos de fecha
 * y hora del pre-registro eran texto libre con `keyboardType="numeric"`, así que
 * el usuario podía teclear los dígitos y no tenía forma de escribir los
 * separadores: «solo salen números y no se pudo separar horas y minutos».
 *
 * La otra salida era un selector nativo de fecha, pero eso significa agregar un
 * módulo nativo, y en este proyecto `android/` está versionado: `expo prebuild`
 * rehace la carpeta entera. Una máscara resuelve lo mismo sin tocar la
 * compilación.
 */

/** Deja AAAA-MM-DD mientras se escribe. */
export const enmascararFecha = (texto: string): string => {
  const d = texto.replace(/\D/g, '').slice(0, 8);

  if (d.length <= 4) return d;
  if (d.length <= 6) return `${d.slice(0, 4)}-${d.slice(4)}`;
  return `${d.slice(0, 4)}-${d.slice(4, 6)}-${d.slice(6)}`;
};

/** Deja HH:MM mientras se escribe. */
export const enmascararHora = (texto: string): string => {
  const d = texto.replace(/\D/g, '').slice(0, 4);

  if (d.length <= 2) return d;
  return `${d.slice(0, 2)}:${d.slice(2)}`;
};

/** ¿Es una hora válida del reloj de 24 horas? Vacío también vale: es opcional. */
export const horaEsValida = (texto: string): boolean => {
  const t = texto.trim();
  if (t === '') return true;

  const m = /^(\d{2}):(\d{2})$/.exec(t);
  if (!m) return false;

  return Number(m[1]) <= 23 && Number(m[2]) <= 59;
};
