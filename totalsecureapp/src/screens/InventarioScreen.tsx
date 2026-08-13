import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
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

export interface ListaInventario {
  lp_id: number | string;
  lp_nombre: string;
  lp_descripcion?: string;
  productos: Array<{
    pr_id: number | string;
    pr_nombre: string;
    pr_especificacion?: string;
    pr_descripcion?: string;
    cantidad_default?: number;
  }>;
}

export const InventarioScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [listas, setListas] = useState<ListaInventario[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    if (insCode === undefined) return;
    try {
      const response = await api.post(API_ENDPOINTS.INVENTARIO.LIST_BY_INST, {
        ins_code: insCode,
      });
      const data = response.data;
      if (data && Array.isArray(data.listas)) {
        setListas(data.listas);
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al cargar inventario');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [insCode]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Inventario</Text>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
        </View>
      ) : (
        <FlatList
          data={listas}
          keyExtractor={(item) => String(item.lp_id)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>No hay listas de inventario disponibles</Text>
          }
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.item}
              onPress={() =>
                navigation.navigate('InventarioDetalle', {
                  lp_id: item.lp_id,
                  lp_nombre: item.lp_nombre,
                })
              }
            >
              <Text style={styles.itemName}>{item.lp_nombre}</Text>
              {item.lp_descripcion ? (
                <Text style={styles.itemDesc}>{item.lp_descripcion}</Text>
              ) : null}
              <Text style={styles.itemCount}>
                {item.productos?.length || 0} producto(s)
              </Text>
            </TouchableOpacity>
          )}
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
  title: { fontSize: 20, fontWeight: 'bold', color: '#333' },
  list: { padding: 20, paddingBottom: 30 },
  item: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 16,
    marginBottom: 12,
  },
  itemName: { fontSize: 16, fontWeight: '600', color: '#333' },
  itemDesc: { fontSize: 14, color: '#666', marginTop: 4 },
  itemCount: { fontSize: 13, color: '#007AFF', marginTop: 6, fontWeight: '600' },
  emptyText: { textAlign: 'center', color: '#999', marginTop: 40, fontSize: 16 },
});
