import React, { useState, useEffect } from 'react';
import { View, Text, TouchableOpacity, ActivityIndicator, Alert, StyleSheet, FlatList } from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { useNavigation } from '@react-navigation/native';

export const ProfileSelectionScreen = ({ navigation }: { navigation: any }) => {
  const [perfiles, setPerfiles] = useState([]);
  const [loading, setLoading] = useState(true);
  const { login } = useAuth();

  useEffect(() => {
    cargarPerfiles();
  }, []);

  const cargarPerfiles = async () => {
    setLoading(true);
    try {
      const response = await api.get('/acceso/seleccionar_perfil');
      
      if (response.data && response.data.perfiles) {
        setPerfiles(response.data.perfiles);
      } else {
        Alert.alert('Error', 'No se pudieron cargar los perfiles');
      }
    } catch (error: any) {
      console.error('Error cargando perfiles:', error);
      Alert.alert('Error', 'No se pudo conectar al servidor. Verifique su conexi├│n y que el backend est├® en ejecuci├│n.');
    } finally {
      setLoading(false);
    }
  };

  const seleccionarPerfil = async (perfilId: string) => {
    setLoading(true);
    try {
      const response = await api.post('/acceso/procesar_perfil', {
        id: perfilId,
      });

      if (response.data.success) {
        // Guardar informaci├│n del usuario si viene en la respuesta
        if (response.data.user) {
          await login(response.data.token || '', response.data.user);
        }
        
        // Navegar al home
        navigation.navigate('Home');
      } else {
        Alert.alert('Error', response.data.errors || 'No se pudo procesar la selecci├│n');
      }
    } catch (error: any) {
      console.error('Error seleccionando perfil:', error);
      Alert.alert('Error', 'No se pudo conectar al servidor. Verifique su conexi├│n y que el backend est├® en ejecuci├│n.');
    } finally {
      setLoading(false);
    }
  };

  const renderPerfil = ({ item }: { item: any }) => {
    return (
      <TouchableOpacity
        onPress={() => seleccionarPerfil(item.id)}
        style={styles.perfilItem}
      >
        <View style={styles.perfilInfo}>
          <Text style={styles.perfilNombre}>{item.nombre}</Text>
          {item.descripcion && (
            <Text style={styles.perfilDescripcion}>{item.descripcion}</Text>
          )}
        </View>
        <View style={styles.perfilArrow}>
          {/* Icono de flecha hacia la derecha */}
        </View>
      </TouchableOpacity>
    );
  };

  if (loading) {
    return (
      <View style={styles.container}>
        <View style={styles.logoContainer}>
          <Text style={styles.appName}>Total Secure App</Text>
        </View>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#007AFF" />
          <Text style={styles.loadingText}>Cargando perfiles...</Text>
        </View>
      </View>
    );
  }

  if (perfiles.length === 0) {
    return (
      <View style={styles.container}>
        <View style={styles.logoContainer}>
          <Text style={styles.appName}>Total Secure App</Text>
        </View>
        <View style={styles.emptyContainer}>
          <Text style={styles.emptyText}>No hay perfiles disponibles</Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.logoContainer}>
        <Text style={styles.appName}>Total Secure App</Text>
      </View>
      
      <View style={styles.titleContainer}>
        <Text style={styles.title}>Seleccione su Perfil</Text>
      </View>
      
      <View style={styles.listContainer}>
        <FlatList
          data={perfiles}
          keyExtractor={(item) => item.id.toString()}
          renderItem={renderPerfil}
          contentContainerStyle={styles.listContentContainer}
        />
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    padding: 20,
  },
  logoContainer: {
    marginBottom: 30,
  },
  appName: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
  },
  titleContainer: {
    marginBottom: 25,
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
  },
  listContainer: {
    flex: 1,
  },
  listContentContainer: {
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
  perfilInfo: {
    flex: 1,
  },
  perfilNombre: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 4,
  },
  perfilDescripcion: {
    fontSize: 14,
    color: '#666',
  },
  perfilArrow: {
    width: 20,
  },
  loadingContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    flex: 1,
  },
  loadingText: {
    marginTop: 15,
    color: '#666',
    fontSize: 16,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    flex: 1,
  },
  emptyText: {
    color: '#666',
    fontSize: 16,
    textAlign: 'center',
  },
});