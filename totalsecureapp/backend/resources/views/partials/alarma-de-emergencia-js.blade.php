{{--
    La logica de audio de la alarma, en UN solo lugar.

    ⚠️ Estaba copiada en dos sitios --el widget del tablero y el componente
    global-- y por eso el mismo fallo volvio cuatro veces: se arreglaba uno y el
    otro quedaba mudo. Se inyecta por `PanelsRenderHook::HEAD_END`, antes que
    cualquier `x-data` del cuerpo, asi que la funcion existe cuando Alpine monta.

    Los tres fallos que corrige, y que hacian que «la alerta llega pero no suena»:

    1. **El contexto de audio se abria sin gesto del usuario.** `preparar()` se
       llamaba en la carga de la pagina si `localStorage` decia que ya se habia
       activado antes. El navegador crea ese contexto en estado `suspended`, pero
       el codigo ponia `listo = true` igual, y eso **escondia el unico boton que
       podia desbloquearlo**. La primera vez sonaba (el clic era el gesto) y a
       partir de la segunda carga quedaba mudo para siempre, sin sintoma visible.
       Ahora `listo` sale del estado REAL del contexto, no de `localStorage`:
       si el navegador no concedio el audio, el boton sigue a la vista.

    2. **No se esperaba a que el audio despertara.** `resume()` devuelve una
       promesa y no se aguardaba. Mientras el contexto esta suspendido
       `currentTime` NO avanza, asi que los osciladores se programaban contra un
       reloj parado y, al arrancar, esos instantes ya habian pasado: se
       descartaban en silencio. Ahora se espera el `resume()` antes de leer el
       reloj.

    3. **Sin audio no habia ningun aviso.** La funcion salia por `return` al
       principio si el contexto no existia, asi que tampoco se dibujaba el cartel
       rojo. Ahora **el aviso visual va siempre** y el sonido es lo unico
       condicional: una emergencia que no suena tiene que verse.
--}}
<script>
    if (! window.alarmaDeEmergencia) {
        window.alarmaDeEmergencia = function () {
            return {
                audio: null,
                /** ¿El navegador esta dejando sonar AHORA? Sale del contexto, no de localStorage. */
                listo: false,
                /** Para el cartel rojo. */
                sonando: false,
                _apagar: null,

                /**
                 * Lo que se llama cuando entra una emergencia.
                 *
                 * El aviso visual primero y sin condiciones: si el audio esta
                 * bloqueado, que al menos se vea.
                 */
                dispararAlarma() {
                    this.sonando = true;
                    clearTimeout(this._apagar);
                    this._apagar = setTimeout(() => { this.sonando = false; }, 5200);

                    this.sirena();
                },

                /**
                 * Abre el contexto y espera a que este REALMENTE sonando.
                 *
                 * Sin gesto previo del usuario esto falla a proposito: el
                 * navegador no permite audio automatico. Ese caso deja `listo`
                 * en false y el boton a la vista, que es el comportamiento
                 * correcto.
                 */
                async prepararAudio() {
                    try {
                        if (! this.audio) {
                            const Ctx = window.AudioContext || window.webkitAudioContext;
                            if (! Ctx) return false;

                            this.audio = new Ctx();

                            // Si el navegador lo suspende mas tarde (cambio de
                            // pestana, ahorro de energia), el boton vuelve solo.
                            this.audio.addEventListener('statechange', () => {
                                this.listo = this.audio.state === 'running';
                            });
                        }

                        if (this.audio.state !== 'running') {
                            await this.audio.resume();
                        }
                    } catch (e) {
                        console.warn('alarma: no se pudo abrir el audio', e);
                    }

                    this.listo = !! this.audio && this.audio.state === 'running';

                    if (this.listo) {
                        localStorage.setItem('alertas-sonido', '1');
                    }

                    return this.listo;
                },

                /** El boton. Al venir de un clic, aca el `resume()` si prospera. */
                async activar() {
                    await this.prepararAudio();
                    this.dispararAlarma();
                },

                /**
                 * Sirena de emergencia, no un pitido.
                 *
                 * Un barrido continuo entre 600 y 1200 Hz repetido seis veces
                 * durante unos 5 segundos: es el patron que se reconoce como
                 * alarma a traves de una puerta y con ruido de oficina. Los
                 * pitidos cortos anteriores se confundian con una notificacion
                 * de correo. Se genera, no se descarga: no hay archivo que
                 * desplegar ni ruta que pueda faltar.
                 */
                async sirena() {
                    if (! await this.prepararAudio()) {
                        return; // el cartel rojo ya esta puesto
                    }

                    const ctx = this.audio;
                    // Margen: el reloj acaba de arrancar y programar en el
                    // instante exacto se pierde el primer ciclo.
                    const t0 = ctx.currentTime + 0.05;
                    const ciclos = 6;
                    const dur = 0.8;

                    for (let i = 0; i < ciclos; i++) {
                        const t = t0 + i * dur;

                        const osc = ctx.createOscillator();
                        const vol = ctx.createGain();

                        osc.type = 'sawtooth';
                        osc.frequency.setValueAtTime(600, t);
                        osc.frequency.linearRampToValueAtTime(1200, t + dur / 2);
                        osc.frequency.linearRampToValueAtTime(600, t + dur);

                        vol.gain.setValueAtTime(0.0001, t);
                        vol.gain.exponentialRampToValueAtTime(0.85, t + 0.05);
                        vol.gain.setValueAtTime(0.85, t + dur - 0.1);
                        vol.gain.exponentialRampToValueAtTime(0.0001, t + dur);

                        osc.connect(vol).connect(ctx.destination);
                        osc.start(t);
                        osc.stop(t + dur);
                    }
                },

                /**
                 * Engancha el evento del servidor.
                 *
                 * Se usa `Livewire.on`, el API explicito, y no solo
                 * `x-on:...window`: el atajo de Alpine depende de que el evento
                 * llegue como evento del DOM en `window`, y eso no ocurria de
                 * forma fiable dentro de un widget que Livewire vuelve a dibujar
                 * en cada refresco.
                 */
                escucharEmergencias() {
                    // Intento silencioso: si el navegador ya concedio audio en
                    // esta pestana, queda listo sin molestar. Si no, `listo`
                    // sigue en false y el boton permanece visible -- que es
                    // justo lo que antes fallaba.
                    if (localStorage.getItem('alertas-sonido') === '1') {
                        this.prepararAudio();
                    }

                    const enganchar = () => window.Livewire.on(
                        'emergencia-nueva',
                        () => this.dispararAlarma(),
                    );

                    if (window.Livewire) {
                        enganchar();
                    } else {
                        document.addEventListener('livewire:init', enganchar);
                    }
                },
            };
        };
    }
</script>
