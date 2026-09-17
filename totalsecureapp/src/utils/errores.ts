/**
 * El mensaje que el servidor realmente mandó.
 *
 * ⚠️ **Sin esto la app tapaba el motivo de cada rechazo.** El backend responde
 * los errores con `generalTrait::message_json()`, que arma:
 *
 *     {"success": false, "errors": {"": ["Está a 3,2 km del punto de marcación…"]}}
 *
 * y las pantallas leían `data.message`, que en esa respuesta **no existe**. El
 * guardia veía «No se pudo guardar la marcación» mientras el servidor le estaba
 * explicando que se había alejado del punto. Pasaba en 15 pantallas.
 *
 * Los validadores de Laravel usan la misma forma pero con el nombre del campo
 * como clave (`{"errors": {"usu_cedula": ["…"]}}`), así que sirve para las dos.
 */
export const mensajeDeError = (data: any, porDefecto: string): string => {
  if (!data) return porDefecto;

  const errores = data.errors;

  if (typeof errores === 'string' && errores.trim() !== '') {
    return errores;
  }

  if (errores && typeof errores === 'object') {
    for (const clave of Object.keys(errores)) {
      const v = errores[clave];
      if (Array.isArray(v) && v.length > 0 && typeof v[0] === 'string' && v[0].trim() !== '') {
        return v[0];
      }
      if (typeof v === 'string' && v.trim() !== '') {
        return v;
      }
    }
  }

  if (typeof data.message === 'string' && data.message.trim() !== '') {
    return data.message;
  }

  return porDefecto;
};

/** Lo mismo, para el `catch` de axios: el cuerpo viaja en `error.response.data`. */
export const mensajeDeExcepcion = (error: any, porDefecto: string): string => {
  if (error?.response?.data) {
    return mensajeDeError(error.response.data, porDefecto);
  }

  // Sin respuesta del servidor: se cortó la red o no hay conexión.
  return porDefecto;
};
