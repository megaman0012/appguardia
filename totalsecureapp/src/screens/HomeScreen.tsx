import React from 'react';
import { View, Text, TouchableOpacity, Alert, StyleSheet, ScrollView } from 'react-native';
import { useAuth } from '../context/AuthContext';
import { Encabezado } from '../components/Encabezado';
import { COLORES } from '../utils/tema';

export const HomeScreen = ({ navigation }: { navigation: any }) => {
  const { user, perfil, logout, can } = useAuth();

  const nombres = user?.nombres || user?.usu_nombres || 'Usuario';
  const email = user?.email || user?.usu_email || '';
  const acc = user?.acc || user?.usu_acc || '';

  const handleLogout = async () => {
    try {
      await logout();
      navigation.navigate('Login');
    } catch (error: any) {
      console.error('Error en logout:', error);
      Alert.alert('Error', 'No se pudo cerrar la sesión correctamente');
    }
  };

  const confirmarSalida = () => {
    Alert.alert('Cerrar sesión', '¿Salir de la aplicación?', [
      { text: 'No', style: 'cancel' },
      { text: 'Salir', style: 'destructive', onPress: handleLogout },
    ]);
  };

  // Cada modulo se muestra solo si el perfil activo tiene el permiso de lectura.
  // El backend vuelve a validarlo en cada endpoint (middleware permission.api).
  const menu = [
    { titulo: 'Rondas', permiso: 'rondas.ver', accion: () => navigation.navigate('RondaList') },
    { titulo: 'Accesos', permiso: 'acceso.ver', accion: () => navigation.navigate('AccesoList') },
    { titulo: 'Novedades', permiso: 'novedades.ver', accion: () => navigation.navigate('NovedadList') },
    { titulo: 'Alertas', permiso: 'alertas.ver', accion: () => navigation.navigate('Alertas') },
    { titulo: 'Inventario', permiso: 'inventario.ver', accion: () => navigation.navigate('Inventario') },
    { titulo: 'Biometría', permiso: 'biometria.marcar', accion: () => navigation.navigate('Biometria') },
    { titulo: 'Turnos disponibles', permiso: 'vacantes.ver', accion: () => navigation.navigate('Vacantes') },
    { titulo: 'Perfil', permiso: 'perfil.ver', accion: () => navigation.navigate('Perfil') },
  ].filter((item) => can(item.permiso));

  return (
    <View style={styles.container}>
      <Encabezado
        titulo="Total Secure"
        derecha={
          <TouchableOpacity onPress={confirmarSalida} hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}>
            <Text style={styles.salir}>Salir</Text>
          </TouchableOpacity>
        }
      />

      {/*
        ⚠️ Esto era un `View` con `flex: 1` y ahi estaba el problema que se
        reporto: con ocho modulos en el menu, los ultimos quedaban por debajo
        del borde de la pantalla y **no habia forma de llegar a ellos**. Ahora
        hay scroll de verdad, y ademas el menu va en dos columnas, asi que en la
        practica los ocho entran sin desplazar.
      */}
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.tarjetaUsuario}>
          <Text style={styles.nombre}>{nombres}</Text>
          {perfil && <Text style={styles.perfil}>{perfil.nombre}</Text>}

          {/*
            El correo y el codigo de acceso ocupaban dos lineas cada uno con su
            etiqueta encima, y empujaban el menu fuera de la pantalla. Se
            muestran en una sola linea y solo si existen: 505 usuarios no tienen
            correo cargado.
          */}
          {(email !== '' || acc !== '') && (
            <Text style={styles.secundario} numberOfLines={1}>
              {[email, acc && `Cód. ${acc}`].filter(Boolean).join('  ·  ')}
            </Text>
          )}
        </View>

        {menu.length === 0 ? (
          <Text style={styles.menuEmptyText}>
            El perfil activo no tiene módulos habilitados.
          </Text>
        ) : (
          <View style={styles.grilla}>
            {menu.map((item) => (
              <TouchableOpacity
                key={item.permiso}
                onPress={item.accion}
                style={styles.celda}
                activeOpacity={0.7}
              >
                <Text style={styles.celdaTexto}>{item.titulo}</Text>
              </TouchableOpacity>
            ))}
          </View>
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORES.fondo,
  },
  salir: {
    color: COLORES.textoSobreMarca,
    fontSize: 15,
    fontWeight: '600',
  },
  content: {
    padding: 16,
    paddingBottom: 32,
  },
  tarjetaUsuario: {
    backgroundColor: COLORES.fondoSuave,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORES.borde,
    padding: 16,
    marginBottom: 20,
  },
  nombre: {
    fontSize: 18,
    fontWeight: '700',
    color: COLORES.texto,
  },
  perfil: {
    fontSize: 14,
    color: COLORES.marca,
    fontWeight: '600',
    marginTop: 2,
  },
  secundario: {
    fontSize: 13,
    color: COLORES.textoSuave,
    marginTop: 6,
  },
  grilla: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    // `space-between` con ancho 48% deja el hueco justo entre columnas sin
    // tener que calcular margenes.
    justifyContent: 'space-between',
  },
  celda: {
    width: '48%',
    minHeight: 84,
    backgroundColor: COLORES.fondo,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORES.borde,
    borderLeftWidth: 4,
    borderLeftColor: COLORES.marca,
    padding: 14,
    marginBottom: 12,
    justifyContent: 'center',
  },
  celdaTexto: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORES.texto,
  },
  menuEmptyText: {
    fontSize: 15,
    color: COLORES.textoSuave,
  },
});
