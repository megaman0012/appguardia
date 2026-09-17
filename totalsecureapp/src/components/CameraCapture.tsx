import React, { useRef, useState } from 'react';
import { View, Text, TouchableOpacity, Image, StyleSheet, ActivityIndicator } from 'react-native';
import { CameraView, useCameraPermissions, CameraCapturedPicture, CameraType } from 'expo-camera';
import { COLORES } from '../utils/tema';

interface Props {
  onCapture: (photo: CameraCapturedPicture) => void;
  onCancel: () => void;
  title?: string;
}

export const CameraCapture = ({ onCapture, onCancel, title = 'Tomar foto' }: Props) => {
  const [permission, requestPermission] = useCameraPermissions();
  const [photo, setPhoto] = useState<CameraCapturedPicture | null>(null);
  const [taking, setTaking] = useState(false);
  /*
   * La trasera es la de siempre: la marcación del guardia y las fotos de ronda
   * apuntan a algo que está enfrente. La frontal hace falta para la selfie de
   * marcación, que es donde el guardia se fotografía a sí mismo y a ciegas con
   * la trasera.
   */
  const [facing, setFacing] = useState<CameraType>('back');
  const cameraRef = useRef<CameraView | null>(null);

  if (!permission) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#007AFF" />
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.center}>
        <Text style={styles.centerText}>Se requiere permiso de cámara</Text>
        <TouchableOpacity style={styles.button} onPress={requestPermission}>
          <Text style={styles.buttonText}>Permitir cámara</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.cancelButton} onPress={onCancel}>
          <Text style={styles.cancelText}>Cancelar</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const handleCapture = async () => {
    if (!cameraRef.current || taking) return;
    setTaking(true);
    try {
      /*
       * `quality` 0.6 y no la máxima, y esto no es un detalle estético.
       *
       * ⚠️ Una foto sin comprimir de la cámara de una tablet pesa entre 3 y
       * 5 MB, y **el marcaje fallaba por eso**: PHP venía con
       * `upload_max_filesize = 2M`, descartaba el archivo antes de que Laravel
       * lo viera y el guardia recibía un error de subida que no explicaba nada.
       * El límite del servidor ya se subió, pero además conviene no mandar 5 MB
       * por una red de garita: con 0.6 la foto ronda los 300-600 KB, se sigue
       * reconociendo perfectamente a la persona, sube mucho más rápido y ocupa
       * una fracción en disco -- que ya lleva 1,3 GB solo de fotos.
       */
      const pic = await cameraRef.current.takePictureAsync({
        base64: false,
        quality: 0.6,
      });
      setPhoto(pic);
    } catch (e) {
      console.error('Error al capturar foto:', e);
    } finally {
      setTaking(false);
    }
  };

  return (
    <View style={styles.container}>
      {photo ? (
        <>
          <Image source={{ uri: photo.uri }} style={styles.preview} />
          <View style={styles.actions}>
            <TouchableOpacity style={styles.cancelButton} onPress={() => setPhoto(null)}>
              <Text style={styles.cancelText}>Repetir</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.button}
              onPress={() => onCapture(photo)}
            >
              <Text style={styles.buttonText}>Usar foto</Text>
            </TouchableOpacity>
          </View>
        </>
      ) : (
        <>
          <CameraView
            ref={cameraRef}
            style={styles.camera}
            facing={facing}
          >
            <View style={styles.cameraTop}>
              <TouchableOpacity style={styles.cancelButton} onPress={onCancel}>
                <Text style={styles.cancelText}>Cancelar</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.voltear}
                onPress={() => setFacing((c) => (c === 'back' ? 'front' : 'back'))}
                accessibilityLabel="Cambiar de cámara"
              >
                <Text style={styles.voltearIcono}>⟲</Text>
                <Text style={styles.voltearTexto}>
                  {facing === 'back' ? 'Frontal' : 'Trasera'}
                </Text>
              </TouchableOpacity>
            </View>
            <View style={styles.cameraBottom}>
              <TouchableOpacity
                style={styles.shutter}
                onPress={handleCapture}
                disabled={taking}
              >
                {taking ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.shutterText}>{title}</Text>
                )}
              </TouchableOpacity>
            </View>
          </CameraView>
        </>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORES.fondoCamara,
  },
  camera: {
    flex: 1,
    justifyContent: 'space-between',
  },
  cameraTop: {
    padding: 20,
    paddingTop: 50,
    // En fila: «Cancelar» a la izquierda y el cambio de cámara a la derecha.
    // Sin esto quedaban uno debajo del otro, tapando la imagen.
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  voltear: {
    backgroundColor: 'rgba(0,0,0,0.55)',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    alignItems: 'center',
    // Área táctil cómoda: en una tablet y con guantes, un icono suelto no se
    // acierta.
    minWidth: 68,
  },
  voltearIcono: {
    color: '#FFF',
    fontSize: 20,
    lineHeight: 22,
  },
  voltearTexto: {
    color: '#FFF',
    fontSize: 12,
    fontWeight: '600',
  },
  cameraBottom: {
    padding: 30,
    alignItems: 'center',
  },
  shutter: {
    backgroundColor: COLORES.marca,
    borderRadius: 50,
    paddingVertical: 14,
    paddingHorizontal: 40,
  },
  shutterText: {
    color: COLORES.textoSobreMarca,
    fontSize: 16,
    fontWeight: '600',
  },
  preview: {
    flex: 1,
    width: '100%',
  },
  actions: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    padding: 30,
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
    backgroundColor: COLORES.fondoCamara,
  },
  centerText: {
    color: COLORES.textoSobreMarca,
    fontSize: 16,
    marginBottom: 20,
  },
  button: {
    backgroundColor: COLORES.marca,
    borderRadius: 8,
    paddingVertical: 12,
    paddingHorizontal: 24,
  },
  buttonText: {
    color: COLORES.textoSobreMarca,
    fontSize: 16,
    fontWeight: '600',
  },
  cancelButton: {
    backgroundColor: COLORES.marcaGris,
    borderRadius: 8,
    paddingVertical: 12,
    paddingHorizontal: 24,
  },
  cancelText: {
    color: COLORES.textoSobreMarca,
    fontSize: 16,
  },
});
