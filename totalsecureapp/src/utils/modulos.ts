/**
 * Los módulos de la app, en un solo lugar.
 *
 * El Home y el menú lateral muestran lo mismo, así que la lista vive acá en vez
 * de estar copiada en los dos: agregar un módulo en un sitio y olvidarlo en el
 * otro es el error que esto evita.
 *
 * El icono es un emoji y no una fuente de iconos a propósito: `@expo/vector-icons`
 * no está instalado, y en este proyecto `android/` está versionado, así que
 * sumar un módulo nativo obliga a `expo prebuild`, que rehace la carpeta entera.
 * Un emoji se ve igual en toda tablet Android y no cuesta nada.
 */
export interface Modulo {
  /** Ruta del stack de navegación. */
  pantalla: string;
  titulo: string;
  icono: string;
  /** Permiso de lectura que lo habilita. El backend lo revalida en cada endpoint. */
  permiso: string;
}

export const MODULOS: Modulo[] = [
  // Inventario va primero: es lo que se revisa al recibir el puesto.
  { pantalla: 'Inventario', titulo: 'Inventario', icono: '📦', permiso: 'inventario.ver' },
  // 🕐 y no el emoji de huella: ese es de Unicode 16 y las tablets viejas lo
  // dibujarían como un cuadrito. Acá lo que se hace es marcar entrada y salida.
  { pantalla: 'Biometria', titulo: 'Biometría', icono: '🕐', permiso: 'biometria.marcar' },
  { pantalla: 'RondaList', titulo: 'Rondas', icono: '🚶', permiso: 'rondas.ver' },
  { pantalla: 'AccesoList', titulo: 'Accesos', icono: '🚪', permiso: 'acceso.ver' },
  // Se llama «Bitácora», que es como lo nombra la operación. La ruta y el
  // permiso siguen diciendo «novedad» porque son los de la API y la base: un
  // cambio de etiqueta no justifica tocar el contrato con el servidor.
  { pantalla: 'NovedadList', titulo: 'Bitácora', icono: '📝', permiso: 'novedades.ver' },
  { pantalla: 'Alertas', titulo: 'Alertas', icono: '🚨', permiso: 'alertas.ver' },
  { pantalla: 'Vacantes', titulo: 'Turnos disponibles', icono: '📅', permiso: 'vacantes.ver' },
  { pantalla: 'Perfil', titulo: 'Perfil', icono: '👤', permiso: 'perfil.ver' },
];
