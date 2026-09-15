import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, ActivityIndicator, Alert, StyleSheet } from 'react-native';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { COLORES } from '../utils/tema';

export const PasswordResetRequestScreen = ({ navigation }: { navigation: any }) => {
  const [identificacion, setIdentificacion] = useState('');
  const [loading, setLoading] = useState(false);

  const extraerError = (data: any): string => {
    if (data && data.errors) {
      const errors = data.errors;
      if (typeof errors === 'object') {
        const first = Object.keys(errors)[0];
        if (first !== undefined && Array.isArray(errors[first])) {
          return errors[first][0] || 'No se pudo procesar la solicitud';
        }
      }
    }
    return data && data.message ? data.message : 'No se pudo procesar la solicitud';
  };

  const handleSubmit = async () => {
    if (!identificacion.trim()) {
      Alert.alert('Error', 'Por favor ingrese su identificación');
      return;
    }

    setLoading(true);
    try {
      const response = await api.post(API_ENDPOINTS.AUTH.SOLICITUD_PASS, {
        usu_cedula: identificacion.trim(),
      });

      const data = response.data;

      if (data && data.errors) {
        Alert.alert('Error', extraerError(data));
        return;
      }

      /*
       * La respuesta ya NO trae `user_id` ni el token, y es la misma exista o no
       * la cédula: antes los devolvía, y con eso cualquiera que supiera una
       * cédula cambiaba la contraseña de esa persona.
       *
       * El código llega por fuera (WhatsApp o correo), así que la pantalla
       * siguiente pide la cédula y ese código.
       */
      Alert.alert(
        'Solicitud enviada',
        (data && data.message) ||
          'Si la cédula está registrada recibirá un código.',
        [
          {
            text: 'Ya tengo el código',
            onPress: () =>
              navigation.replace('PasswordReset', { usu_cedula: identificacion.trim() }),
          },
          { text: 'Salir', style: 'cancel', onPress: () => navigation.goBack() },
        ]
      );
    } catch (error: any) {
      console.error('Solicitud de cambio de contraseña error:', error);
      if (error.response && error.response.data) {
        Alert.alert('Error', extraerError(error.response.data));
      } else {
        Alert.alert('Error', 'No se pudo conectar al servidor. Verifique su conexión y que el backend esté en ejecución.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.logoContainer}>
        <Text style={styles.appName}>Total Secure App</Text>
      </View>

      <View style={styles.formContainer}>
        <Text style={styles.title}>Recuperar Contraseña</Text>
        <Text style={styles.description}>
          Ingrese su identificación para recibir un correo con las instrucciones para restablecer su contraseña.
        </Text>

        <TextInput
          placeholder="Identificación"
          value={identificacion}
          onChangeText={setIdentificacion}
          style={styles.input}
          autoCapitalize="none"
          keyboardType="numeric"
        />

        <TouchableOpacity
          onPress={handleSubmit}
          style={styles.button}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator size="small" color="white" />
          ) : (
            <Text style={styles.buttonText}>Enviar Solicitud</Text>
          )}
        </TouchableOpacity>

        <TouchableOpacity
          onPress={() => navigation.goBack()}
          style={styles.backButton}
        >
          <Text style={styles.backButtonText}>Volver al Inicio de Sesión</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORES.fondo,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
  },
  logoContainer: {
    marginBottom: 30,
  },
  appName: {
    fontSize: 24,
    fontWeight: 'bold',
    color: COLORES.texto,
  },
  formContainer: {
    width: '100%',
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 12,
    color: COLORES.texto,
  },
  description: {
    textAlign: 'center',
    marginBottom: 25,
    color: COLORES.textoSuave,
    fontSize: 14,
  },
  input: {
    borderWidth: 1,
    borderColor: COLORES.borde,
    borderRadius: 8,
    padding: 12,
    marginBottom: 16,
    fontSize: 16,
  },
  button: {
    backgroundColor: COLORES.marca,
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  backButton: {
    marginTop: 20,
    paddingVertical: 8,
    alignItems: 'center',
  },
  backButtonText: {
    color: COLORES.marca,
    fontSize: 14,
  },
});
