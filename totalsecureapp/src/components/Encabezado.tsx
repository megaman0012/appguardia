import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet, StatusBar, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { ALTO_ENCABEZADO, COLORES } from '../utils/tema';

interface Props {
  titulo: string;
  /** Si se pasa, se dibuja la flecha de volver. */
  onVolver?: () => void;
  /** Contenido opcional a la derecha: un badge, un botón. */
  derecha?: React.ReactNode;
}

/**
 * Encabezado único de la app.
 *
 * **Dos problemas que resuelve.**
 *
 * 1. **La barra de estado.** `styles.xml` pone `statusBarColor` transparente,
 *    así que el contenido se dibuja por debajo del reloj y la hora del
 *    dispositivo quedaba encima del título. Cada pantalla lo tapaba con un
 *    `paddingTop: 50` a ojo — 17 pantallas con el mismo número mágico, que en
 *    una tablet sin notch deja un hueco y en un teléfono con notch no alcanza.
 *    Acá el alto lo da `useSafeAreaInsets()`, que es el valor real del
 *    dispositivo.
 * 2. **No parecía la app de nadie.** Fondo blanco, borde gris y texto `#333`.
 *    Ahora lleva el rojo de la marca y el título en blanco.
 */
export const Encabezado: React.FC<Props> = ({ titulo, onVolver, derecha }) => {
  const insets = useSafeAreaInsets();

  return (
    <>
      <StatusBar
        barStyle="light-content"
        backgroundColor={COLORES.marca}
        translucent={Platform.OS === 'android'}
      />
      <View style={[styles.contenedor, { paddingTop: insets.top, height: ALTO_ENCABEZADO + insets.top }]}>
        {onVolver ? (
          <TouchableOpacity
            onPress={onVolver}
            style={styles.volver}
            // El área táctil que recomienda Android es 48dp: la flecha sola
            // mide 24 y en una tablet con guantes no se acierta.
            hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
            accessibilityLabel="Volver"
          >
            <Text style={styles.flecha}>‹</Text>
          </TouchableOpacity>
        ) : (
          <View style={styles.volver} />
        )}

        <Text style={styles.titulo} numberOfLines={1}>
          {titulo}
        </Text>

        <View style={styles.derecha}>{derecha}</View>
      </View>
    </>
  );
};

const styles = StyleSheet.create({
  contenedor: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORES.marca,
    paddingHorizontal: 8,
  },
  volver: {
    width: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  flecha: {
    color: COLORES.textoSobreMarca,
    fontSize: 34,
    lineHeight: 36,
    marginTop: -4,
  },
  titulo: {
    flex: 1,
    color: COLORES.textoSobreMarca,
    fontSize: 18,
    fontWeight: '700',
  },
  derecha: {
    minWidth: 40,
    alignItems: 'flex-end',
    justifyContent: 'center',
    paddingRight: 4,
  },
});
