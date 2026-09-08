import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
  FlatList,
  RefreshControl,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { getCurrentLocation } from '../utils/location';
import { ahoraDelDispositivo, useIdempotencia } from '../utils/idempotencia';
import { Encabezado } from '../components/Encabezado';
import { COLORES } from '../utils/tema';

/**
 * Un producto de la lista, con su conteo.
 *
 * ⚠️ Los campos son `ipc_*`, que es lo que devuelve la API de v2. Antes se
 * leian `pr_id` / `pr_nombre` / `pr_especificacion` (nombres de v1) y quedaban
 * todos `undefined`: las filas se dibujaban **en blanco** y al guardar el
 * backend recibia productos sin `id_producto` y respondia «Undefined property:
 * stdClass::$id_producto». El inventario no podia guardar nada.
 */
interface ItemConteo {
  ipc_id: number | string;
  ipc_nombre: string;
  ipc_especificacion?: string;
  cantidad_default: number;
  cantidad: string;
  nota: string;
  estado: number;
}

export const InventarioDetalleScreen = ({ navigation, route }: any) => {
  const { lp_id, lp_nombre } = route.params;
  const { institucion } = useAuth();
  const [productos, setProductos] = useState<ItemConteo[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [guardando, setGuardando] = useState(false);
  const [movId, setMovId] = useState<string | null>(null);
  const { uuidPara, confirmar } = useIdempotencia();

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    if (insCode === undefined) return;
    try {
      const response = await api.post(API_ENDPOINTS.INVENTARIO.LIST_BY_INST, {
        ins_code: insCode,
      });
      const data = response.data;
      const listas = (data?.listas || []) as any[];
      // Antes comparaba `l.lp_id` con `lp_id`, y **las dos cosas eran
      // undefined**: `String(undefined) === String(undefined)` da true, asi que
      // siempre "encontraba" la primera lista del local por accidente. Con un
      // local de dos listas, abrir la segunda mostraba la primera.
      const lista = listas.find((l) => String(l.li_id) === String(lp_id));

      if (lista) {
        setProductos(
          (lista.productos || []).map((p: any) => ({
            ipc_id: p.ipc_id,
            ipc_nombre: p.ipc_nombre,
            ipc_especificacion: p.ipc_especificacion,
            cantidad_default: Number(p.cantidad_default || 0),
            cantidad: String(p.cantidad_default || 0),
            nota: '',
            estado: 1,
          }))
        );
      } else {
        // Antes esta rama no existia y la pantalla quedaba vacia sin decir nada.
        Alert.alert('Lista no disponible', 'La lista ya no está activa en este local.');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al cargar la lista');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [insCode, lp_id]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const actualizar = (index: number, campo: string, valor: any) => {
    setProductos((prev) => prev.map((p, i) => (i === index ? { ...p, [campo]: valor } : p)));
  };

  const guardarRecepcion = async () => {
    if (insCode === undefined) return;
    setGuardando(true);
    try {
      let coords = { lat: '0', lng: '0' };
      try {
        coords = await getCurrentLocation();
      } catch (e: any) {
        Alert.alert('Error', e.message || 'No se pudo obtener la ubicación');
        setGuardando(false);
        return;
      }

      const payload = {
        ins_code: insCode,
        list_code: lp_id,
        latitud: coords.lat,
        longitud: coords.lng,
        // Si esto se envía dos veces (sin señal, el guardia vuelve a tocar
        // «Guardar»), el servidor devuelve el mismo movimiento en vez de crear
        // otro. El uuid se suelta recién cuando confirma.
        client_uuid: uuidPara('recepcion'),
        ocurrido_en: ahoraDelDispositivo(),
        productos: JSON.stringify(
          productos.map((p) => ({
            id_producto: p.ipc_id,
            estado: p.estado,
            cantidaddf: p.cantidad_default,
            cantidad: Number(p.cantidad) || 0,
            nota: p.nota,
          }))
        ),
      };

      const response = await api.post(API_ENDPOINTS.INVENTARIO.LIST_SAVE, payload);
      const data = response.data;
      if (data && data.message) {
        confirmar('recepcion');
        setMovId(String(data.id));
        Alert.alert('Éxito', data.message);
      } else if (data && data.errors) {
        Alert.alert('Error', String(Object.values(data.errors)[0]));
      } else {
        Alert.alert('Error', data?.message || 'No se pudo guardar la recepción');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al guardar la recepción');
    } finally {
      setGuardando(false);
    }
  };

  const finalizarDevolucion = async () => {
    if (insCode === undefined || !movId) return;
    Alert.alert('Devolución', '¿Finalizar devolución de la lista?', [
      { text: 'No', style: 'cancel' },
      {
        text: 'Sí',
        onPress: async () => {
          setGuardando(true);
          try {
            let coords = { lat: '0', lng: '0' };
            try {
              coords = await getCurrentLocation();
            } catch (e: any) {
              Alert.alert('Error', e.message || 'No se pudo obtener la ubicación');
              setGuardando(false);
              return;
            }
            const response = await api.post(API_ENDPOINTS.INVENTARIO.FINISH_SAVE, {
              ins_code: insCode,
              code_mov: movId,
              latitud: coords.lat,
              longitud: coords.lng,
              // Sin client_uuid: `code_mov` ya identifica el movimiento que se
              // cierra, así que el reintento es reconocible sin uuid.
              ocurrido_en: ahoraDelDispositivo(),
            });
            const data = response.data;
            if (data && data.message) {
              Alert.alert('Éxito', data.message, [
                { text: 'OK', onPress: () => navigation.goBack() },
              ]);
            } else {
              Alert.alert('Error', data?.message || 'No se pudo finalizar');
            }
          } catch (error: any) {
            Alert.alert('Error', error.response?.data?.message || 'Error al finalizar');
          } finally {
            setGuardando(false);
          }
        },
      },
    ]);
  };

  return (
    <View style={styles.container}>
      <Encabezado titulo={lp_nombre || 'Lista de inventario'} onVolver={() => navigation.goBack()} />

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={COLORES.marca} />
        </View>
      ) : (
        <FlatList
          data={productos}
          keyExtractor={(item) => String(item.ipc_id)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          renderItem={({ item, index }) => (
            <View style={styles.item}>
              <View style={styles.itemHeader}>
                <Text style={styles.itemName}>{item.ipc_nombre}</Text>
                <TouchableOpacity
                  onPress={() => actualizar(index, 'estado', item.estado === 1 ? 0 : 1)}
                  style={[
                    styles.estadoChip,
                    item.estado === 1 ? styles.estadoOk : styles.estadoMal,
                  ]}
                >
                  <Text style={styles.estadoText}>
                    {item.estado === 1 ? 'Existe' : 'Falta'}
                  </Text>
                </TouchableOpacity>
              </View>
              {item.ipc_especificacion ? (
                <Text style={styles.itemSpec}>{item.ipc_especificacion}</Text>
              ) : null}
              <Text style={styles.itemDefault}>
                Cantidad asignada: {item.cantidad_default}
              </Text>
              {/* El placeholder desaparece en cuanto hay valor, y la cantidad
                  viene precargada con la asignada: el campo quedaba con un
                  numero y sin decir de que era. */}
              <Text style={styles.campoEtiqueta}>Cantidad recibida</Text>
              <TextInput
                placeholder="0"
                placeholderTextColor={COLORES.textoSuave}
                value={item.cantidad}
                onChangeText={(v) => actualizar(index, 'cantidad', v)}
                style={styles.qtyInput}
                keyboardType="numeric"
              />

              <Text style={styles.campoEtiqueta}>Nota (opcional)</Text>
              <TextInput
                placeholder="Ej. le falta la tapa"
                placeholderTextColor={COLORES.textoSuave}
                value={item.nota}
                onChangeText={(v) => actualizar(index, 'nota', v)}
                style={styles.noteInput}
              />
            </View>
          )}
          ListFooterComponent={
            <View style={styles.footer}>
              <TouchableOpacity
                style={styles.saveButton}
                onPress={guardarRecepcion}
                disabled={guardando}
              >
                {guardando ? (
                  <ActivityIndicator color={COLORES.textoSobreMarca} />
                ) : (
                  <Text style={styles.saveButtonText}>
                    {movId ? 'Recepción registrada' : 'Guardar recepción'}
                  </Text>
                )}
              </TouchableOpacity>
              {movId && (
                <TouchableOpacity
                  style={styles.finishButton}
                  onPress={finalizarDevolucion}
                  disabled={guardando}
                >
                  <Text style={styles.finishButtonText}>Finalizar devolución</Text>
                </TouchableOpacity>
              )}
            </View>
          }
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORES.fondo },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  list: { padding: 16, paddingBottom: 30 },
  item: {
    backgroundColor: COLORES.fondo,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORES.borde,
    padding: 14,
    marginBottom: 12,
  },
  itemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  itemName: { fontSize: 16, fontWeight: '700', color: COLORES.texto, flex: 1, marginRight: 8 },
  estadoChip: { borderRadius: 12, paddingHorizontal: 12, paddingVertical: 5 },
  estadoOk: { backgroundColor: COLORES.exito },
  estadoMal: { backgroundColor: COLORES.critico },
  estadoText: { fontSize: 12, fontWeight: '800', color: COLORES.textoSobreMarca },
  itemSpec: { fontSize: 13, color: COLORES.textoSuave, marginTop: 4 },
  itemDefault: { fontSize: 13, color: COLORES.marca, marginTop: 6, fontWeight: '700' },
  campoEtiqueta: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORES.textoSuave,
    marginTop: 10,
    marginBottom: 4,
  },
  qtyInput: {
    borderWidth: 1,
    borderColor: COLORES.borde,
    borderRadius: 8,
    padding: 10,
    backgroundColor: COLORES.fondo,
    fontSize: 16,
    // ⚠️ El `color` explicito es lo que evita el texto invisible: sin el,
    // Android usa el del tema, y con el tema DayNight en un dispositivo en modo
    // oscuro el texto salia claro sobre este fondo blanco.
    color: COLORES.texto,
  },
  noteInput: {
    borderWidth: 1,
    borderColor: COLORES.borde,
    borderRadius: 8,
    padding: 10,
    backgroundColor: COLORES.fondo,
    fontSize: 15,
    color: COLORES.texto,
  },
  footer: { marginTop: 10 },
  saveButton: {
    backgroundColor: COLORES.marca,
    borderRadius: 10,
    paddingVertical: 15,
    alignItems: 'center',
  },
  saveButtonText: { color: COLORES.textoSobreMarca, fontSize: 16, fontWeight: '700' },
  finishButton: {
    backgroundColor: '#28a745',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 10,
  },
  finishButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
});
