import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
  FlatList,
} from 'react-native';
import { useAuth, Institucion } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { COLORES } from '../utils/tema';

export const SeleccionInstitucionScreen = ({ navigation }: { navigation: any }) => {
  const { setInstitucion, logout } = useAuth();
  const [instituciones, setInstituciones] = useState<Institucion[]>([]);
  const [loading, setLoading] = useState(true);

  const cargar = async () => {
    setLoading(true);
    try {
      const response = await api.post(API_ENDPOINTS.INSTITUCIONES);
      const data = response.data;
      if (data && Array.isArray(data.instituciones)) {
        setInstituciones(data.instituciones);
      } else {
        setInstituciones([]);
      }
    } catch (error: any) {
      console.error('Error cargando instituciones:', error);
      const msg =
        error.response?.data?.message || 'No se pudieron cargar las instituciones';
      Alert.alert('Error', msg);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    cargar();
  }, []);

  const seleccionar = async (inst: Institucion) => {
    await setInstitucion(inst);
    navigation.replace('Home');
  };

  const cerrarSesion = async () => {
    await logout();
    navigation.replace('Login');
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#007AFF" />
        <Text style={styles.loadingText}>Cargando instituciones...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Seleccione la institución</Text>
      </View>

      {instituciones.length === 0 ? (
        <View style={styles.center}>
          <Text style={styles.emptyText}>
            No tiene instituciones asignadas. Contacte al administrador.
          </Text>
          <TouchableOpacity style={styles.logoutButton} onPress={cerrarSesion}>
            <Text style={styles.logoutText}>Cerrar sesión</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={instituciones}
          keyExtractor={(item) => String(item.ins_code)}
          contentContainerStyle={styles.list}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.item}
              onPress={() => seleccionar(item)}
            >
              <Text style={styles.itemName}>{item.ins_descripcion}</Text>
              {item.ins_direccion ? (
                <Text style={styles.itemAddress}>{item.ins_direccion}</Text>
              ) : null}
            </TouchableOpacity>
          )}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORES.fondo,
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
  },
  loadingText: {
    marginTop: 12,
    color: COLORES.textoSuave,
  },
  emptyText: {
    fontSize: 16,
    color: COLORES.textoSuave,
    textAlign: 'center',
    marginBottom: 24,
  },
  header: {
    padding: 20,
    paddingTop: 60,
    borderBottomWidth: 1,
    borderBottomColor: COLORES.borde,
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: COLORES.texto,
  },
  list: {
    padding: 20,
  },
  item: {
    backgroundColor: COLORES.fondoSuave,
    borderRadius: 10,
    padding: 16,
    marginBottom: 12,
  },
  itemName: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORES.texto,
  },
  itemAddress: {
    fontSize: 14,
    color: COLORES.textoSuave,
    marginTop: 4,
  },
  logoutButton: {
    backgroundColor: COLORES.fondoSuave,
    borderRadius: 8,
    padding: 14,
    alignItems: 'center',
  },
  logoutText: {
    color: COLORES.critico,
    fontSize: 16,
    fontWeight: '600',
  },
});
