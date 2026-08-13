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

interface ItemConteo {
  pr_id: number | string;
  pr_nombre: string;
  pr_especificacion?: string;
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

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    if (insCode === undefined) return;
    try {
      const response = await api.post(API_ENDPOINTS.INVENTARIO.LIST_BY_INST, {
        ins_code: insCode,
      });
      const data = response.data;
      const listas = (data?.listas || []) as any[];
      const lista = listas.find((l) => String(l.lp_id) === String(lp_id));
      if (lista) {
        setProductos(
          (lista.productos || []).map((p: any) => ({
            pr_id: p.pr_id,
            pr_nombre: p.pr_nombre,
            pr_especificacion: p.pr_especificacion,
            cantidad_default: Number(p.cantidad_default || 0),
            cantidad: String(p.cantidad_default || 0),
            nota: '',
            estado: 1,
          }))
        );
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
        productos: JSON.stringify(
          productos.map((p) => ({
            id_producto: p.pr_id,
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
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>{lp_nombre}</Text>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
        </View>
      ) : (
        <FlatList
          data={productos}
          keyExtractor={(item) => String(item.pr_id)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          renderItem={({ item, index }) => (
            <View style={styles.item}>
              <View style={styles.itemHeader}>
                <Text style={styles.itemName}>{item.pr_nombre}</Text>
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
              {item.pr_especificacion ? (
                <Text style={styles.itemSpec}>{item.pr_especificacion}</Text>
              ) : null}
              <Text style={styles.itemDefault}>
                Cantidad asignada: {item.cantidad_default}
              </Text>
              <TextInput
                placeholder="Cantidad recibida"
                value={item.cantidad}
                onChangeText={(v) => actualizar(index, 'cantidad', v)}
                style={styles.qtyInput}
                keyboardType="numeric"
              />
              <TextInput
                placeholder="Nota"
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
                  <ActivityIndicator color="#fff" />
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
  container: { flex: 1, backgroundColor: '#fff' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    paddingTop: 50,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  backBtn: { marginRight: 12 },
  backText: { fontSize: 16, color: '#007AFF' },
  title: { fontSize: 18, fontWeight: 'bold', color: '#333', flex: 1 },
  list: { padding: 20, paddingBottom: 30 },
  item: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 14,
    marginBottom: 12,
  },
  itemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  itemName: { fontSize: 15, fontWeight: '600', color: '#333', flex: 1, marginRight: 8 },
  estadoChip: { borderRadius: 12, paddingHorizontal: 10, paddingVertical: 4 },
  estadoOk: { backgroundColor: '#d4edda' },
  estadoMal: { backgroundColor: '#f8d7da' },
  estadoText: { fontSize: 12, fontWeight: '700', color: '#333' },
  itemSpec: { fontSize: 13, color: '#666', marginTop: 4 },
  itemDefault: { fontSize: 13, color: '#007AFF', marginTop: 4, fontWeight: '600' },
  qtyInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 6,
    padding: 10,
    backgroundColor: '#fff',
    marginTop: 8,
    fontSize: 14,
  },
  noteInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 6,
    padding: 10,
    backgroundColor: '#fff',
    marginTop: 8,
    fontSize: 14,
  },
  footer: { marginTop: 10 },
  saveButton: {
    backgroundColor: '#007AFF',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  finishButton: {
    backgroundColor: '#28a745',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 10,
  },
  finishButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
});
