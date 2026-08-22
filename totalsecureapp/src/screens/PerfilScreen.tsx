import React from 'react';
import { View, Text, TouchableOpacity, Alert, StyleSheet, ScrollView } from 'react-native';
import { useAuth } from '../context/AuthContext';

export const PerfilScreen = ({ navigation }: { navigation: any }) => {
  const { user, institucion, perfil, permisos, logout } = useAuth();

  const cerrarSesion = async () => {
    Alert.alert('Cerrar sesión', '¿Desea cerrar la sesión?', [
      { text: 'No', style: 'cancel' },
      {
        text: 'Sí',
        onPress: async () => {
          await logout();
          navigation.reset({ index: 0, routes: [{ name: 'Login' }] });
        },
      },
    ]);
  };

  const nombres = user?.nombres || user?.usu_nombres || 'Usuario';
  const email = user?.email || user?.usu_email || '';
  const acc = user?.acc || user?.usu_acc || '';
  const perfilesUsuario = (user?.perfiles || []) as string[];

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Perfil</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.label}>Nombre</Text>
        <Text style={styles.value}>{nombres}</Text>

        {email ? (
          <>
            <Text style={styles.label}>Email</Text>
            <Text style={styles.value}>{email}</Text>
          </>
        ) : null}

        {acc ? (
          <>
            <Text style={styles.label}>Código de acceso</Text>
            <Text style={styles.value}>{acc}</Text>
          </>
        ) : null}

        {perfil ? (
          <>
            <Text style={styles.label}>Perfil activo</Text>
            <Text style={styles.value}>
              {perfil.nombre} ({permisos.length} permisos)
            </Text>
          </>
        ) : null}

        {perfilesUsuario.length > 0 ? (
          <>
            <Text style={styles.label}>Perfiles asignados</Text>
            <View style={styles.badges}>
              {perfilesUsuario.map((a) => (
                <View key={a} style={styles.badge}>
                  <Text style={styles.badgeText}>{a}</Text>
                </View>
              ))}
            </View>
          </>
        ) : null}

        <Text style={styles.label}>Institución</Text>
        <Text style={styles.value}>
          {institucion?.ins_descripcion || 'No seleccionada'}
        </Text>
        {institucion?.ins_direccion ? (
          <Text style={styles.subValue}>{institucion.ins_direccion}</Text>
        ) : null}
      </View>

      <TouchableOpacity style={styles.logoutButton} onPress={cerrarSesion}>
        <Text style={styles.logoutText}>Cerrar sesión</Text>
      </TouchableOpacity>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  content: { paddingBottom: 40 },
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
  card: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 20,
    margin: 20,
  },
  label: {
    fontSize: 13,
    color: '#666',
    fontWeight: '500',
    marginTop: 12,
  },
  value: {
    fontSize: 16,
    color: '#333',
    fontWeight: '600',
    marginTop: 2,
  },
  subValue: {
    fontSize: 14,
    color: '#666',
    marginTop: 2,
  },
  badges: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginTop: 6,
  },
  badge: {
    backgroundColor: '#cfe8ff',
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 4,
    marginRight: 8,
    marginTop: 4,
  },
  badgeText: { fontSize: 12, fontWeight: '700', color: '#333' },
  logoutButton: {
    backgroundColor: '#dc3545',
    marginHorizontal: 20,
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  logoutText: { color: '#fff', fontSize: 16, fontWeight: '600' },
});
