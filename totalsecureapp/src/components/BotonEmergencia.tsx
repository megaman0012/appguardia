import React, { useState } from 'react';
import { Alert, StyleSheet, Text, TouchableOpacity, ActivityIndicator } from 'react-native';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { getCurrentLocation } from '../utils/location';
import { ahoraDelDispositivo, useIdempotencia } from '../utils/idempotencia';
import { mensajeDeError, mensajeDeExcepcion } from '../utils/errores';
import { COLORES } from '../utils/tema';

interface Props {
  /**
   * `ins_code` viene como número o como texto según de dónde salga la sesión,
   * así que se acepta lo que haya y se normaliza al enviar.
   */
  insCode?: number | string;
  /** Se llama cuando la alerta salió, por si la pantalla quiere recargar. */
  onEnviada?: () => void;
}

/**
 * El botón de pánico, a un toque y desde donde se esté.
 *
 * **Por qué no pide escribir nada.** El motivo era obligatorio, y eso convertía
 * una emergencia en un formulario: primero entrar a Alertas, después redactar,
 * después enviar. En una emergencia real nadie escribe. Ahora el texto es
 * opcional en el servidor y este botón no lo pide: un toque, una confirmación
 * corta para que no salga sin querer, y sale.
 *
 * **Por qué vive en el inicio.** Estaba dentro de la pantalla de Alertas, a dos
 * pasos de navegación. Lo que hace útil a un botón de pánico es estar donde ya
 * se está mirando.
 *
 * La confirmación es a propósito: es un botón grande y rojo en una tablet que se
 * manipula con guantes, y una alerta falsa moviliza gente.
 */
export const BotonEmergencia: React.FC<Props> = ({ insCode, onEnviada }) => {
  const [enviando, setEnviando] = useState(false);
  const { uuidPara, confirmar } = useIdempotencia();

  const enviar = async () => {
    if (insCode === undefined) {
      Alert.alert('Sin local', 'Seleccione un local antes de enviar una emergencia.');
      return;
    }

    setEnviando(true);
    try {
      /*
       * La ubicación NO bloquea el envío. El endpoint exige lat/lng, pero una
       * emergencia no se puede perder porque el GPS tarde o el guardia esté bajo
       * techo: si no hay lectura se manda 0/0, que el servidor ya interpreta
       * como «no se sabe» y no como una coordenada real.
       */
      let coords = { lat: '0', lng: '0' };
      try {
        coords = await getCurrentLocation();
      } catch (e) {
        // Se sigue igual, a propósito.
      }

      const { data } = await api.post(API_ENDPOINTS.NOTIFICACION.ALERT_CREAR, {
        ins: Number(insCode),
        lat: coords.lat,
        lng: coords.lng,
        // Sin texto: el servidor lo registra como «Botón de emergencia».
        prioridad: 'critica',
        client_uuid: uuidPara('panico'),
        ocurrido_en: ahoraDelDispositivo(),
      });

      if (data?.success) {
        confirmar('panico');
        Alert.alert('Emergencia enviada', 'La central fue notificada.');
        onEnviada?.();
      } else {
        Alert.alert('No se pudo enviar', mensajeDeError(data, 'Intente de nuevo.'));
      }
    } catch (error: any) {
      Alert.alert('No se pudo enviar', mensajeDeExcepcion(error, 'Sin conexión. Vuelva a intentar.'));
    } finally {
      setEnviando(false);
    }
  };

  const confirmarEnvio = () => {
    Alert.alert(
      '¿Enviar emergencia?',
      'Se avisa de inmediato a la central y a su supervisor.',
      [
        { text: 'No', style: 'cancel' },
        { text: 'Sí, enviar', style: 'destructive', onPress: enviar },
      ]
    );
  };

  return (
    <TouchableOpacity
      style={styles.boton}
      onPress={confirmarEnvio}
      disabled={enviando}
      activeOpacity={0.8}
      accessibilityLabel="Enviar emergencia"
    >
      {enviando ? (
        <ActivityIndicator color={COLORES.textoSobreMarca} />
      ) : (
        <>
          <Text style={styles.icono}>🚨</Text>
          <Text style={styles.texto}>EMERGENCIA</Text>
        </>
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  boton: {
    backgroundColor: COLORES.marca,
    borderRadius: 14,
    paddingVertical: 20,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 18,
    // Sombra: tiene que destacar sobre la cuadrícula de módulos, no parecer uno
    // más de ellos.
    elevation: 4,
    shadowColor: '#000',
    shadowOpacity: 0.25,
    shadowRadius: 4,
    shadowOffset: { width: 0, height: 2 },
  },
  icono: {
    fontSize: 30,
    marginBottom: 4,
  },
  texto: {
    color: COLORES.textoSobreMarca,
    fontSize: 20,
    fontWeight: '800',
    letterSpacing: 1,
  },
});
