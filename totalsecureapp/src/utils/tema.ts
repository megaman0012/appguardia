/**
 * Colores y medidas de la app, en un solo lugar.
 *
 * **Por que existe.** Cada pantalla traia sus propios colores sueltos: el azul
 * `#007AFF` de iOS en los indicadores, `#333` en los títulos, `#dc3545` de
 * Bootstrap en «Cerrar Sesión», y los estados de una alerta salían todos del
 * mismo gris. Nada de eso es la marca de Total Secure, y sin un lugar común
 * cada pantalla nueva volvía a inventar el suyo.
 *
 * Los dos primeros valores están **medidos del logo**
 * (`LOGO_APP_TOTAL_SECURE.png`), no elegidos a ojo.
 */
export const COLORES = {
  /** Rojo del escudo. */
  marca: '#BD1212',
  /** Gris del candado. */
  marcaGris: '#666666',

  /** Un rojo más oscuro, para el estado «presionado» sobre `marca`. */
  marcaOscura: '#8E0D0D',

  fondo: '#FFFFFF',
  fondoSuave: '#F5F6F8',
  borde: '#E4E6EA',

  texto: '#1F2328',
  textoSuave: '#5B6470',
  textoSobreMarca: '#FFFFFF',

  /**
   * Estados. Se usan en las alertas y en los badges de inventario: antes todos
   * los estados se veían igual y había que leer la palabra para distinguir una
   * alerta crítica de una ya atendida.
   */
  critico: '#B3261E',
  advertencia: '#B26B00',
  exito: '#1B7F4B',
  informacion: '#1F5EA8',

  /**
   * Fondos suaves de los mismos estados, para los badges de las listas.
   *
   * Se agregaron al migrar las 14 pantallas que quedaban: los badges de
   * Accesos, Rondas, Vacantes y Perfil usaban los colores de Bootstrap
   * (`#d4edda`, `#f8d7da`, `#cce5ff`, `#fff3cd`) y no habia token para eso.
   * Forzarlos al color pleno los volvia ilegibles -- son fondos con texto
   * oscuro encima, no botones.
   */
  criticoSuave: '#FBE9E7',
  advertenciaSuave: '#FDF3E0',
  exitoSuave: '#E8F5EC',
  informacionSuave: '#E7F0FB',
  /** Estado neutro o ya cerrado: ni bueno ni malo, solo terminado. */
  neutroSuave: '#EDEEF0',

  /**
   * Texto de marcador de posicion y de campos deshabilitados.
   *
   * ⚠️ Es a proposito MAS CLARO que `textoSuave` y mas oscuro que los `#999`,
   * `#aaa` y `#ccc` que reemplaza: esos tres no llegaban al contraste minimo
   * sobre blanco, y mandarlos a `textoSuave` habria borrado la diferencia entre
   * un dato real y un marcador de posicion.
   */
  textoTenue: '#8A929E',

  /**
   * Negro de las pantallas de camara y del escaner QR.
   *
   * No es `fondo` ni un descuido: la vista previa de la camara sobre blanco se
   * ve como un error de carga. Tiene su propio nombre para que quede claro que
   * el negro ahi es deliberado.
   */
  fondoCamara: '#000000',
} as const;

/** Alto del encabezado, sin contar la barra de estado. */
export const ALTO_ENCABEZADO = 56;

/**
 * Color para un estado de alerta.
 *
 * ⚠️ Compara en minúsculas a propósito: la columna `al_estado_alerta` guarda
 * «Finalizada» con mayúscula inicial pero el CHECK de la tabla usa minúsculas,
 * así que en la práctica llegan las dos formas.
 */
export function colorDeEstadoAlerta(estado?: string | null): string {
  switch ((estado || '').trim().toLowerCase()) {
    case 'atendida':
    case 'finalizada':
    case 'cerrada':
      return COLORES.exito;
    case 'en_proceso':
    case 'en proceso':
    case 'atendiendo':
      return COLORES.informacion;
    case 'cancelada':
      return COLORES.textoSuave;
    default:
      // Pendiente, o cualquier estado que no conozcamos: se muestra como algo
      // que requiere atención, que es el lado seguro del error.
      return COLORES.critico;
  }
}

/** Color para una prioridad de alerta. */
export function colorDePrioridad(prioridad?: string | null): string {
  switch ((prioridad || '').trim().toLowerCase()) {
    case 'critica':
      return COLORES.critico;
    case 'alta':
      return COLORES.marca;
    case 'media':
      return COLORES.advertencia;
    default:
      return COLORES.textoSuave;
  }
}
