import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, ActivityIndicator, Alert, StyleSheet, ScrollView } from 'react-native';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';

export const PasswordResetScreen = ({ navigation, route }: { navigation: any; route: any }) => {
  const { user_id } = route.params || {};
  const [password, setPassword] = useState('');
  const [password2, setPassword2] = useState('');
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
    if (!password || !password2) {
      Alert.alert('Error', 'Por favor ingrese la nueva contraseña y su confirmación');
      return;
    }
    if (password !== password2) {
      Alert.alert('Error', 'Las contraseñas no coinciden');
      return;
    }
    if (password.length < 8) {
      Alert.alert('Error', 'La contraseña debe tener mínimo 8 caracteres');
      return;
    }

    setLoading(true);
    try {
      const response = await api.post(API_ENDPOINTS.AUTH.PROCESAR_PASS, {
        user_id,
        password,
        password2,
      });

      const data = response.data;
      if (data && data.success) {
        Alert.alert('Contraseña cambiada', data.message || 'Su contraseña fue actualizada correctamente.', [
          { text: 'OK', onPress: () => navigation.navigate('Login') },
        ]);
      } else {
        Alert.alert('Error', extraerError(data));
      }
    } catch (error: any) {
      console.error('Cambio de contraseña error:', error);
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
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.logoContainer}>
        <Text style={styles.appName}>Total Secure App</Text>
      </View>

      <View style={styles.formContainer}>
        <Text style={styles.title}>Cambiar Contraseña</Text>
        <Text style={styles.description}>
          Ingrese su nueva contraseña. Debe tener mínimo 8 caracteres e incluir una mayúscula, una minúscula y un número.
        </Text>

        <TextInput
          placeholder="Nueva contraseña"
          value={password}
          onChangeText={setPassword}
          style={styles.input}
          secureTextEntry={true}
        />
        <TextInput
          placeholder="Repetir contraseña"
          value={password2}
          onChangeText={setPassword2}
          style={styles.input}
          secureTextEntry={true}
        />

        <TouchableOpacity
          onPress={handleSubmit}
          style={styles.button}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator size="small" color="white" />
          ) : (
            <Text style={styles.buttonText}>Guardar Nueva Contraseña</Text>
          )}
        </TouchableOpacity>

        <TouchableOpacity
          onPress={() => navigation.navigate('Login')}
          style={styles.backButton}
        >
          <Text style={styles.backButtonText}>Volver al Inicio de Sesión</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flexGrow: 1,
    backgroundColor: '#fff',
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
    color: '#333',
  },
  formContainer: {
    width: '100%',
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 12,
    color: '#333',
  },
  description: {
    textAlign: 'center',
    marginBottom: 25,
    color: '#666',
    fontSize: 14,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    marginBottom: 16,
    fontSize: 16,
  },
  button: {
    backgroundColor: '#007AFF',
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
    color: '#007AFF',
    fontSize: 14,
  },
});
