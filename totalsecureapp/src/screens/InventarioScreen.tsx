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
import { Encabezado } from '../components/Encabezado';
import { COLORES } from '../utils/tema';

/**
 * Una lista de inventario, **con los nombres que devuelve la API de v2**.
 *
 * ⚠️ Esto decia `lp_id`, `lp_nombre`, `lp_descripcion` y, en los productos,
 * `pr_id` / `pr_nombre` / `pr_especificacion`: son los nombres de la base de
 * v1. `/inventario/listbyinst` devuelve `li_*` para la lista e `ipc_*` para el
 * producto, asi que **todo salia vacio**:
 *
 *  - En la lista solo se leia «4 producto(s)», sin nombre ni descripcion.
 *  - En el detalle, las filas de productos aparecian en blanco.
 *  - Y al guardar, `id_producto` iba `undefined`; `JSON.stringify` descarta las
 *    claves undefined, asi que el backend recibia productos sin id y respondia
 *    «Undefined property: stdClass::$id_producto». **El inventario del APK no
 *    podia guardar nada.**
 */
export interface ListaInventario {
  li_id: number | string;
  li_nombre: string;
  li_descripcion?: string;
  productos: Array<{
    ipc_id: number | string;
    ipc_nombre: string;
    ipc_especificacion?: string;
    ipc_descripcion?: string;
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
      <Encabezado titulo="Inventario" onVolver={() => navigation.goBack()} />

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={COLORES.marca} />
        </View>
      ) : (
        <FlatList
          data={listas}
          keyExtractor={(item) => String(item.li_id)}
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
                  lp_id: item.li_id,
                  lp_nombre: item.li_nombre,
                })
              }
              activeOpacity={0.7}
            >
              <Text style={styles.itemName}>{item.li_nombre}</Text>
              {item.li_descripcion ? (
                <Text style={styles.itemDesc}>{item.li_descripcion}</Text>
              ) : null}
              <Text style={styles.itemCount}>
                {item.productos?.length || 0}{' '}
                {(item.productos?.length || 0) === 1 ? 'producto' : 'productos'}
              </Text>
            </TouchableOpacity>
          )}
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
    borderLeftWidth: 4,
    borderLeftColor: COLORES.marca,
    padding: 16,
    marginBottom: 12,
  },
  itemName: { fontSize: 17, fontWeight: '700', color: COLORES.texto },
  itemDesc: { fontSize: 14, color: COLORES.textoSuave, marginTop: 4 },
  itemCount: { fontSize: 13, color: COLORES.marca, marginTop: 8, fontWeight: '700' },
  emptyText: { textAlign: 'center', color: COLORES.textoSuave, marginTop: 40, fontSize: 16 },
});
