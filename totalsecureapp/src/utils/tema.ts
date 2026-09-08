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
