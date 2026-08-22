import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useAuth, Perfil } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS, APP_NAME } from '../utils/constants';
import { RootStackScreenProps } from '../navigation/AppNavigator';

interface SeleccionarPerfilResponse {
  perfiles?: Perfil[];
  message?: string;
}

interface ProcesarPerfilResponse {
  perfil?: Perfil;
  permisos?: string[];
  message?: string;
}

const ERROR_CONEXION =
  'No se pudo conectar al servidor. Verifique su conexión y que el backend esté en ejecución.';

export const ProfileSelectionScreen = ({
  navigation,
}: RootStackScreenProps<'ProfileSelection'>) => {
  const { setPerfil } = useAuth();
  const [perfiles, setPerfiles] = useState<Perfil[]>([]);
  const [loading, setLoading] = useState(true);
  const [procesando, setProcesando] = useState<number | null>(null);

  // Guarda el perfil elegido con sus permisos y continua el flujo.
  const aplicarPerfil = useCallback(
    async (perfilId: number) => {
      setProcesando(perfilId);
      try {
        const { data } = await api.post<ProcesarPerfilResponse>(
          API_ENDPOINTS.PERFIL.PROCESAR,
          { id: perfilId }
        );

        if (!data?.perfil) {
          Alert.alert('Error', data?.message || 'No se pudo procesar el perfil');
          return;
        }

        await setPerfil(data.perfil, data.permisos ?? []);
        navigation.reset({ index: 0, routes: [{ name: 'SeleccionInstitucion' }] });
      } catch (error: any) {
        const msg = error?.response?.data?.message;
        console.error('Error procesando perfil:', error);
        Alert.alert('Error', msg || ERROR_CONEXION);
      } finally {
        setProcesando(null);
      }
    },
    [navigation, setPerfil]
  );

  useEffect(() => {
    let activo = true;

    const cargarPerfiles = async () => {
      try {
        const { data } = await api.post<SeleccionarPerfilResponse>(
          API_ENDPOINTS.PERFIL.SELECCIONAR
        );
        if (!activo) return;

        const lista = data?.perfiles ?? [];
        setPerfiles(lista);

        // Con un unico perfil no tiene sentido pedir que lo elija.
        if (lista.length === 1) {
          await aplicarPerfil(lista[0].id);
          return;
        }
        setLoading(false);
      } catch (error: any) {
        if (!activo) return;
        console.error('Error cargando perfiles:', error);
        Alert.alert('Error', error?.response?.data?.message || ERROR_CONEXION);
        setLoading(false);
      }
    };

    cargarPerfiles();
    return () => {
      activo = false;
    };
  }, [aplicarPerfil]);

  const renderPerfil = ({ item }: { item: Perfil }) => (
    <TouchableOpacity
      onPress={() => aplicarPerfil(item.id)}
      disabled={procesando !== null}
      style={[styles.perfilItem, procesando !== null && styles.perfilItemDisabled]}
    >
      <View style={styles.perfilInfo}>
        <Text style={styles.perfilNombre}>{item.nombre}</Text>
        {item.descripcion && item.descripcion !== item.nombre ? (
          <Text style={styles.perfilDescripcion}>{item.descripcion}</Text>
        ) : null}
      </View>
      {procesando === item.id ? (
        <ActivityIndicator size="small" color="#007AFF" />
      ) : (
        <Text style={styles.perfilArrow}>›</Text>
      )}
    </TouchableOpacity>
  );

  if (loading) {
    return (
      <View style={styles.container}>
        <Text style={styles.appName}>{APP_NAME}</Text>
        <View style={styles.centered}>
          <ActivityIndicator size="large" color="#007AFF" />
          <Text style={styles.mensaje}>Cargando perfiles...</Text>
        </View>
      </View>
    );
  }

  if (perfiles.length === 0) {
    return (
      <View style={styles.container}>
        <Text style={styles.appName}>{APP_NAME}</Text>
        <View style={styles.centered}>
          <Text style={styles.mensaje}>No hay perfiles disponibles</Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.appName}>{APP_NAME}</Text>
      <Text style={styles.title}>Seleccione su perfil</Text>
      <FlatList
        data={perfiles}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderPerfil}
        contentContainerStyle={styles.listContent}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    padding: 20,
  },
  appName: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 30,
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 25,
  },
  listContent: {
    paddingBottom: 20,
  },
  perfilItem: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 15,
    marginBottom: 10,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  perfilItemDisabled: {
    opacity: 0.6,
  },
  perfilInfo: {
    flex: 1,
  },
  perfilNombre: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  perfilDescripcion: {
    fontSize: 14,
    color: '#666',
    marginTop: 4,
  },
  perfilArrow: {
    fontSize: 22,
    color: '#999',
    paddingHorizontal: 4,
  },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  mensaje: {
    marginTop: 15,
    color: '#666',
    fontSize: 16,
    textAlign: 'center',
  },
});
