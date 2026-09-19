{{--
    El motor de la alarma de emergencia. UNO solo para todo el panel.

    ════════════════════════════════════════════════════════════════════════
    POR QUE SEGUIA SIN SONAR DESPUES DE ARREGLAR EL AUDIO
    ════════════════════════════════════════════════════════════════════════

    Se comprobo la cadena entera y el servidor esta bien: `AvisoDeEmergencia`
    detecta la alerta y **despacha `emergencia-nueva`** (hay test). El HTML
    entregado trae el componente, el sondeo y esta funcion (comprobado volcando
    la pagina autenticada). La CSP permite `unsafe-inline`. OPcache revalida.

    El fallo estaba en **cuando se arma**, no en como suena:

    ⚠️ **El panel NO usa SPA**, asi que cada clic en el menu es una **carga
    completa de pagina**. El permiso de audio del navegador («sticky
    activation») es **por documento**: se pierde entero en cada navegacion. Con
    el diseño anterior habia que pulsar «Activar alarma» **en cada pagina que se
    abriera**. Nadie va a hacer eso, asi que en la practica la alarma estaba
    armada casi nunca -- y el boton, al esconderse tras `localStorage`, hacia
    creer que si.

    La solucion es dejar de pedir un gesto dedicado: **cualquier interaccion
    desbloquea el audio**. El operador hace clic en algo a los pocos segundos de
    abrir cualquier pagina, y con eso basta.

    Ademas, tres refuerzos para que una emergencia no dependa de una sola via:

    - **El titulo de la pestaña parpadea.** Es lo unico que se ve cuando el panel
      esta en segundo plano, que es donde suele estar. No pide permiso a nadie.
    - **El cartel rojo se dibuja siempre**, suene o no.
    - **El aviso de «sonido bloqueado» es un banner**, no un boton chico abajo a
      la derecha que nadie mira.

    ⚠️ Las **notificaciones de escritorio** serian lo ideal para avisar con el
    navegador minimizado, pero el navegador las bloquea fuera de un contexto
    seguro y el sitio va por **HTTP** (SEC-01, a la espera del dominio). En
    cuanto haya HTTPS se pueden sumar aca.

    ════════════════════════════════════════════════════════════════════════

    El motor vive **fuera de Alpine**, en `window.__alarmaTS`, y no dentro del
    componente: Livermire vuelve a dibujar el widget en cada sondeo y cada
    navegacion recrea los componentes. Con la logica adentro, cada recreacion
    abria otro AudioContext y registraba otro oyente de `emergencia-nueva` --la
    alarma acababa sonando varias veces--, y el estado se perdia. Asi hay uno y
    solo uno.
--}}
<script>
window.__alarmaTS = window.__alarmaTS || (function () {
    let ctx    = null;
    let listo  = false;
    const suscriptores = new Set();

    function avisarSuscriptores() {
        suscriptores.forEach(function (f) { try { f(listo); } catch (e) {} });
    }

    /**
     * Abre el contexto y comprueba si el navegador esta dejando sonar AHORA.
     *
     * ⚠️ El `resume()` va con carrera contra un temporizador: mientras el audio
     * esta bloqueado, Chrome puede dejar esa promesa **pendiente para siempre**.
     * Con un `await` a secas esta funcion no volveria nunca, y el estado se
     * quedaria congelado sin que nada lo indique.
     */
    async function desbloquear() {
        try {
            if (! ctx) {
                const C = window.AudioContext || window.webkitAudioContext;
                if (! C) return false;

                ctx = new C();
                ctx.addEventListener('statechange', function () {
                    listo = ctx.state === 'running';
                    avisarSuscriptores();
                });
            }

            if (ctx.state !== 'running') {
                await Promise.race([
                    ctx.resume(),
                    new Promise(function (r) { setTimeout(r, 300); }),
                ]);
            }
        } catch (e) {
            console.warn('alarma: no se pudo abrir el audio', e);
        }

        listo = !! ctx && ctx.state === 'running';
        avisarSuscriptores();

        return listo;
    }

    /*
     * ⚠️ ESTA ES LA CORRECCION DE FONDO.
     *
     * Cualquier interaccion con la pagina desbloquea el audio: un clic en el
     * menu, en una fila, una tecla. No hace falta encontrar ningun boton, y
     * funciona en cada pagina nueva sin que el operador haga nada distinto de
     * lo que ya hace.
     *
     * En captura y `passive`, para no interferir con nada. No se quita tras el
     * primer disparo a proposito: si el navegador vuelve a suspender el
     * contexto --cambio de pestaña, ahorro de energia--, la siguiente
     * interaccion lo recupera sola.
     */
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
        document.addEventListener(ev, function () { desbloquear(); },
            { capture: true, passive: true });
    });

    /**
     * Sirena: barrido continuo entre 600 y 1200 Hz, seis veces, ~5 segundos.
     *
     * Es el patron que se reconoce como alarma a traves de una puerta y con
     * ruido de oficina; los pitidos cortos se confundian con una notificacion de
     * correo. Se genera, no se descarga: no hay archivo que desplegar ni ruta
     * que pueda faltar.
     */
    async function sonar() {
        if (! await desbloquear()) return false;

        // El reloj acaba de arrancar: sin margen se pierde el primer ciclo.
        const t0     = ctx.currentTime + 0.05;
        const ciclos = 6;
        const dur    = 0.8;

        for (let i = 0; i < ciclos; i++) {
            const t   = t0 + i * dur;
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

        return true;
    }

    /*
     * El titulo de la pestaña, parpadeando.
     *
     * Es la unica via que funciona con el panel en segundo plano --que es donde
     * suele estar-- y no pide permiso a nadie. Si el audio esta bloqueado, esto
     * es lo unico que el operador va a percibir.
     */
    const tituloOriginal = document.title;
    let   temporizadorTitulo = null;

    function parpadearTitulo(segundos) {
        clearInterval(temporizadorTitulo);

        let encendido = false;

        temporizadorTitulo = setInterval(function () {
            encendido = ! encendido;
            document.title = encendido ? '🚨 EMERGENCIA' : tituloOriginal;
        }, 700);

        setTimeout(function () {
            clearInterval(temporizadorTitulo);
            document.title = tituloOriginal;
        }, segundos * 1000);
    }

    /** Lo que se llama cuando entra una emergencia. */
    function alarma() {
        // Lo que NO depende de ningun permiso va primero y siempre.
        parpadearTitulo(20);
        sonar();
    }

    return {
        estaListo:  function () { return listo; },
        desbloquear: desbloquear,
        sonar:      sonar,
        alarma:     alarma,
        suscribir:  function (f) { suscriptores.add(f); f(listo); },
    };
})();

/*
 * El oyente del evento del servidor se registra UNA vez, no por componente.
 *
 * ⚠️ Antes vivia dentro del `x-data`. Livewire vuelve a dibujar el widget en
 * cada sondeo y cada navegacion recrea los componentes, asi que se acumulaban
 * oyentes y la alarma sonaba varias veces encima de si misma.
 */
if (! window.__alarmaTSEnganchada) {
    window.__alarmaTSEnganchada = true;

    const enganchar = function () {
        window.Livewire.on('emergencia-nueva', function () {
            window.__alarmaTS.alarma();
            window.dispatchEvent(new CustomEvent('ts-emergencia'));
        });
    };

    if (window.Livewire) {
        enganchar();
    } else {
        document.addEventListener('livewire:init', enganchar);
    }
}

/** La parte de Alpine es solo pantalla: el estado vive en el motor. */
window.alarmaDeEmergencia = function () {
    return {
        listo:   false,
        sonando: false,
        _apagar: null,

        iniciar() {
            window.__alarmaTS.suscribir((v) => { this.listo = v; });

            window.addEventListener('ts-emergencia', () => {
                this.sonando = true;
                clearTimeout(this._apagar);
                this._apagar = setTimeout(() => { this.sonando = false; }, 20000);
            });
        },

        /** Botón «Activar»: sirve de respaldo y para comprobar que se oye. */
        async activar() {
            await window.__alarmaTS.desbloquear();
            window.__alarmaTS.sonar();
        },

        probar() { window.__alarmaTS.alarma(); },
    };
};
</script>
