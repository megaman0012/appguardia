import React from 'react';
import { View, Text, TouchableOpacity, Alert, StyleSheet } from 'react-native';
import { useAuth } from '../context/AuthContext';

export const HomeScreen = ({ navigation }: { navigation: any }) => {
  const { user, logout } = useAuth();

  const nombres = user?.nombres || user?.usu_nombres || 'Usuario';
  const email = user?.email || user?.usu_email || '';
  const acc = user?.acc || user?.usu_acc || '';
  const abilities = (user?.abilities || []) as string[];

  const handleLogout = async () => {
    try {
      await logout();
      navigation.navigate('Login');
    } catch (error: any) {
      console.error('Error en logout:', error);
      Alert.alert('Error', 'No se pudo cerrar la sesión correctamente');
    }
  };

  const menu = [
    { titulo: 'Rondas', accion: () => navigation.navigate('RondaList') },
    { titulo: 'Accesos', accion: () => navigation.navigate('AccesoList') },
    { titulo: 'Novedades', accion: () => navigation.navigate('NovedadList') },
    { titulo: 'Alertas', accion: () => navigation.navigate('Alertas') },
    { titulo: 'Inventario', accion: () => navigation.navigate('Inventario') },
    { titulo: 'Biometría', accion: () => navigation.navigate('Biometria') },
    { titulo: 'Perfil', accion: () => navigation.navigate('Perfil') },
  ];

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <Text style={styles.appName}>Total Secure App</Text>
        </View>
        <View style={styles.headerRight}>
          <TouchableOpacity onPress={handleLogout}>
            <Text style={styles.logoutText}>Cerrar Sesión</Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.content}>
        <View style={styles.userInfoContainer}>
          <Text style={styles.userInfoLabel}>Nombre:</Text>
          <Text style={styles.userInfoValue}>{nombres}</Text>

          {email !== '' && (
            <>
              <Text style={styles.userInfoLabel}>Email:</Text>
              <Text style={styles.userInfoValue}>{email}</Text>
            </>
          )}

          {acc !== '' && (
            <>
              <Text style={styles.userInfoLabel}>Código de acceso:</Text>
              <Text style={styles.userInfoValue}>{acc}</Text>
            </>
          )}

          {abilities.length > 0 && (
            <>
              <Text style={styles.userInfoLabel}>Perfil:</Text>
              <Text style={styles.userInfoValue}>{abilities.join(', ')}</Text>
            </>
          )}
        </View>

        <View style={styles.menuContainer}>
          <Text style={styles.menuTitle}>Menú Principal</Text>

          {menu.map((item, index) => (
            <TouchableOpacity
              key={index}
              onPress={item.accion}
              style={styles.menuItem}
            >
              <Text style={styles.menuItemText}>{item.titulo}</Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  headerLeft: {
    flex: 1,
  },
  headerRight: {
    flex: 1,
    alignItems: 'flex-end',
  },
  appName: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
  },
  logoutText: {
    color: '#dc3545',
    fontSize: 16,
    fontWeight: '600',
  },
  content: {
    flex: 1,
    padding: 20,
  },
  userInfoContainer: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 20,
    marginBottom: 30,
  },
  userInfoLabel: {
    fontSize: 14,
    color: '#666',
    marginBottom: 4,
    fontWeight: '500',
  },
  userInfoValue: {
    fontSize: 16,
    color: '#333',
    marginBottom: 8,
  },
  menuContainer: {
    marginBottom: 20,
  },
  menuTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 15,
  },
  menuItem: {
    backgroundColor: '#f8f9fa',
    borderRadius: 8,
    padding: 15,
    marginBottom: 10,
  },
  menuItemText: {
    fontSize: 16,
    color: '#333',
  },
});
