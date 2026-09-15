import React, { useEffect, useRef } from 'react';
import {
  Animated,
  Dimensions,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORES } from '../utils/tema';
import { MODULOS } from '../utils/modulos';

interface Props {
  visible: boolean;
  onCerrar: () => void;
  /** Navega a una pantalla del stack. */
  onIr: (pantalla: string) => void;
  /** Permiso → si el perfil activo lo tiene. */
  puede: (permiso: string) => boolean;
  onSalir: () => void;
  nombre?: string;
  perfil?: string;
  local?: string;
}

const ANCHO = Math.min(300, Dimensions.get('window').width * 0.8);

/**
 * Menú lateral con todos los módulos.
 *
 * Está hecho con `Modal` y `Animated` del propio React Native, y no con
 * `@react-navigation/drawer`: ese paquete arrastra `react-native-reanimated` y
 * `react-native-gesture-handler`, que son módulos nativos, y en este proyecto
 * `android/` está versionado — sumar uno obliga a `expo prebuild`, que rehace la
 * carpeta entera con sus iconos y su splash. Para un panel que se desliza no
 * hace falta tanto.
 *
 * Los módulos salen de la misma lista que el Home (`utils/modulos`), así que no
 * pueden quedar desfasados.
 */
export const MenuLateral: React.FC<Props> = ({
  visible,
  onCerrar,
  onIr,
  puede,
  onSalir,
  nombre,
  perfil,
  local,
}) => {
  const insets = useSafeAreaInsets();
  const desplazamiento = useRef(new Animated.Value(-ANCHO)).current;

  useEffect(() => {
    Animated.timing(desplazamiento, {
      toValue: visible ? 0 : -ANCHO,
      duration: 180,
      useNativeDriver: true,
    }).start();
  }, [visible, desplazamiento]);

  const visibles = MODULOS.filter((m) => puede(m.permiso));

  const ir = (pantalla: string) => {
    onCerrar();
    onIr(pantalla);
  };

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onCerrar}>
      {/* Tocar fuera cierra: en una tablet es más rápido que buscar la X. */}
      <Pressable style={styles.fondo} onPress={onCerrar}>
        <Animated.View
          style={[
            styles.panel,
            { paddingTop: insets.top, transform: [{ translateX: desplazamiento }] },
          ]}
        >
          {/* El panel no propaga el toque, si no se cerraría al usarlo. */}
          <Pressable style={styles.panelInterior} onPress={() => {}}>
            <View style={styles.cabecera}>
              <Text style={styles.nombre} numberOfLines={1}>
                {nombre || 'Usuario'}
              </Text>
              {perfil ? (
                <Text style={styles.subtitulo} numberOfLines={1}>
                  {perfil}
                </Text>
              ) : null}
              {local ? (
                <Text style={styles.subtitulo} numberOfLines={1}>
                  📍 {local}
                </Text>
              ) : null}
            </View>

            <ScrollView>
              {visibles.map((m) => (
                <TouchableOpacity
                  key={m.permiso}
                  style={styles.fila}
                  onPress={() => ir(m.pantalla)}
                  activeOpacity={0.6}
                >
                  <Text style={styles.icono}>{m.icono}</Text>
                  <Text style={styles.filaTexto}>{m.titulo}</Text>
                </TouchableOpacity>
              ))}

              {visibles.length === 0 ? (
                <Text style={styles.vacio}>El perfil activo no tiene módulos habilitados.</Text>
              ) : null}
            </ScrollView>

            <TouchableOpacity
              style={[styles.fila, styles.salir, { marginBottom: insets.bottom }]}
              onPress={() => {
                onCerrar();
                onSalir();
              }}
            >
              <Text style={styles.icono}>🚪</Text>
              <Text style={[styles.filaTexto, styles.salirTexto]}>Cerrar sesión</Text>
            </TouchableOpacity>
          </Pressable>
        </Animated.View>
      </Pressable>
    </Modal>
  );
};

const styles = StyleSheet.create({
  fondo: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
  },
  panel: {
    width: ANCHO,
    flex: 1,
    backgroundColor: COLORES.fondo,
  },
  panelInterior: {
    flex: 1,
  },
  cabecera: {
    backgroundColor: COLORES.marca,
    paddingHorizontal: 16,
    paddingVertical: 18,
  },
  nombre: {
    color: COLORES.textoSobreMarca,
    fontSize: 17,
    fontWeight: '700',
  },
  subtitulo: {
    color: COLORES.textoSobreMarca,
    fontSize: 13,
    marginTop: 2,
    opacity: 0.9,
  },
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 15,
    paddingHorizontal: 16,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#DDD',
  },
  icono: {
    fontSize: 20,
    width: 32,
  },
  filaTexto: {
    fontSize: 16,
    color: COLORES.texto,
  },
  vacio: {
    padding: 16,
    color: COLORES.texto,
  },
  salir: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#DDD',
    borderBottomWidth: 0,
  },
  salirTexto: {
    color: COLORES.marca,
    fontWeight: '600',
  },
});
