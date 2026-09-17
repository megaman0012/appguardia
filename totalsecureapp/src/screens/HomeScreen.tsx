import React, { useState } from 'react';
import { View, Text, TouchableOpacity, Alert, StyleSheet, ScrollView } from 'react-native';
import { useAuth } from '../context/AuthContext';
import { Encabezado } from '../components/Encabezado';
import { MenuLateral } from '../components/MenuLateral';
import { BotonEmergencia } from '../components/BotonEmergencia';
import { MODULOS } from '../utils/modulos';
import { COLORES } from '../utils/tema';

export const HomeScreen = ({ navigation }: { navigation: any }) => {
  const { user, perfil, institucion, logout, can } = useAuth();
  const [menuAbierto, setMenuAbierto] = useState(false);

  const nombres = user?.nombres || user?.usu_nombres || 'Usuario';
  const email = user?.email || user?.usu_email || '';

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

  /*
   * La lista sale de `utils/modulos`, compartida con el menú lateral: antes
   * estaba escrita acá y agregar un módulo en un sitio y olvidarlo en el otro
   * era cuestión de tiempo.
   *
   * Cada módulo se muestra solo si el perfil activo tiene el permiso de lectura.
   * El backend vuelve a validarlo en cada endpoint (middleware permission.api).
   */
  const menu = MODULOS.filter((item) => can(item.permiso));

  return (
    <View style={styles.container}>
      <Encabezado
        titulo="Total Secure"
        // La hamburguesa ocupa el lugar de la flecha de volver, que en el Home
        // no tiene a dónde ir.
        onVolver={() => setMenuAbierto(true)}
        iconoVolver="☰"
        derecha={
          <TouchableOpacity onPress={confirmarSalida} hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}>
            <Text style={styles.salir}>Salir</Text>
          </TouchableOpacity>
        }
      />

      <MenuLateral
        visible={menuAbierto}
        onCerrar={() => setMenuAbierto(false)}
        onIr={(pantalla) => navigation.navigate(pantalla)}
        puede={can}
        onSalir={confirmarSalida}
        nombre={nombres}
        perfil={perfil?.nombre}
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
            El correo ocupaba dos líneas con su etiqueta encima y empujaba el
            menú fuera de la pantalla. Va en una sola línea y sólo si existe:
            505 usuarios no tienen correo cargado.

            El código de acceso **ya no se muestra acá**: esta pantalla vive en
            la tablet del puesto, a la vista de quien pase. Quedó en Perfil,
            oculto y visible a demanda.
          */}
          {email !== '' && (
            <Text style={styles.secundario} numberOfLines={1}>
              {email}
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
                onPress={() => navigation.navigate(item.pantalla)}
                style={styles.celda}
                activeOpacity={0.7}
              >
                <Text style={styles.celdaIcono}>{item.icono}</Text>
                <Text style={styles.celdaTexto}>{item.titulo}</Text>
              </TouchableOpacity>
            ))}
          </View>
        )}
        {/*
          El botón de pánico, debajo de los módulos y siempre a la vista.

          Estaba dentro de la pantalla de Alertas, a dos pasos de navegación, y
          además exigía escribir el motivo antes de enviar. Lo que hace útil a un
          botón de emergencia es estar donde ya se está mirando y salir de un
          toque.
        */}
        {can('alertas.crear') ? (
          <BotonEmergencia insCode={institucion?.ins_code} />
        ) : null}
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
  celdaIcono: {
    fontSize: 26,
    marginBottom: 6,
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
