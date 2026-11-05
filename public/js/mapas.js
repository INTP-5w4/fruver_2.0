/*
 * FruverMapas — carga Google Maps JavaScript API una sola vez y ofrece utilidades
 * comunes (mapa, pines numerados, dibujo de rutas).
 *
 * La configuración llega en <meta name="fruver-mapas" data-llave data-map-id ...>
 * (ver app/Views/partials/mapa_config.php). Sin esa etiqueta, FruverMapas.disponible
 * es false y las páginas funcionan sin mapa.
 */
(function () {
    'use strict';

    var meta = document.querySelector('meta[name="fruver-mapas"]');
    var promesa = null;

    function config() {
        if (!meta) { return null; }
        return {
            llave: meta.getAttribute('data-llave') || '',
            mapId: meta.getAttribute('data-map-id') || '',
            lat: parseFloat(meta.getAttribute('data-lat')) || 19.0433,
            lng: parseFloat(meta.getAttribute('data-lng')) || -98.1981,
            idioma: meta.getAttribute('data-idioma') || 'es',
            region: meta.getAttribute('data-region') || 'MX'
        };
    }

    function cargar() {
        var c = config();
        if (!c || !c.llave) { return Promise.reject(new Error('Google Maps no está configurado.')); }
        if (promesa) { return promesa; }

        promesa = new Promise(function (resolver, rechazar) {
            var cb = '__fruverMapasListo';
            window[cb] = function () { resolver(window.google); };

            var s = document.createElement('script');
            s.src = 'https://maps.googleapis.com/maps/api/js'
                + '?key=' + encodeURIComponent(c.llave)
                + '&v=weekly&loading=async'
                + '&libraries=places,marker,geometry'
                + '&language=' + encodeURIComponent(c.idioma)
                + '&region=' + encodeURIComponent(c.region)
                + '&callback=' + cb;
            s.async = true;
            s.onerror = function () { promesa = null; rechazar(new Error('No se pudo cargar Google Maps.')); };
            document.head.appendChild(s);
        });

        // Google llama a gm_authFailure si la llave es inválida o el dominio no está permitido.
        window.gm_authFailure = function () {
            document.querySelectorAll('[data-mapa-estado]').forEach(function (el) {
                el.textContent = 'Google Maps rechazó la llave del navegador (revisa rutas.llaveNavegador y sus restricciones).';
                el.hidden = false;
            });
        };

        return promesa;
    }

    function crearMapa(elemento, centro, zoom) {
        var c = config();
        var opciones = {
            center: centro || { lat: c.lat, lng: c.lng },
            zoom: zoom || 13,
            streetViewControl: false,
            mapTypeControl: false,
            fullscreenControl: true,
            clickableIcons: false,
            gestureHandling: 'greedy'
        };
        if (c.mapId) { opciones.mapId = c.mapId; }

        return new google.maps.Map(elemento, opciones);
    }

    /**
     * Pin con texto (número de parada). Con Map ID usa AdvancedMarkerElement;
     * sin Map ID usa el marcador clásico (sigue funcionando).
     * Devuelve { posicion(), mover(pos), alSoltar(fn), quitar(), alClic(fn) }.
     */
    function marcador(mapa, posicion, op) {
        op = op || {};
        var c = config();
        var m;

        if (c.mapId && google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
            var pin = new google.maps.marker.PinElement({
                glyph: op.texto ? String(op.texto) : undefined,
                background: op.color || '#2e7d32',
                borderColor: op.borde || '#1b5e20',
                glyphColor: '#ffffff'
            });
            m = new google.maps.marker.AdvancedMarkerElement({
                map: mapa, position: posicion, content: pin.element,
                title: op.titulo || '', gmpDraggable: !!op.arrastrable
            });

            return {
                posicion: function () {
                    var p = m.position;
                    return p ? { lat: typeof p.lat === 'function' ? p.lat() : p.lat, lng: typeof p.lng === 'function' ? p.lng() : p.lng } : null;
                },
                mover: function (pos) { m.position = pos; },
                alSoltar: function (fn) { m.addListener('dragend', fn); },
                alClic: function (fn) { m.addListener('click', fn); },
                quitar: function () { m.map = null; }
            };
        }

        m = new google.maps.Marker({
            map: mapa, position: posicion, draggable: !!op.arrastrable, title: op.titulo || '',
            label: op.texto ? { text: String(op.texto), color: '#ffffff', fontWeight: 'bold' } : undefined
        });

        return {
            posicion: function () { var p = m.getPosition(); return p ? { lat: p.lat(), lng: p.lng() } : null; },
            mover: function (pos) { m.setPosition(pos); },
            alSoltar: function (fn) { m.addListener('dragend', fn); },
            alClic: function (fn) { m.addListener('click', fn); },
            quitar: function () { m.setMap(null); }
        };
    }

    /** Dibuja una polyline codificada (Routes API) o, sin ella, líneas rectas entre puntos. */
    function dibujarRuta(mapa, codificada, puntos, color) {
        var camino = codificada && google.maps.geometry
            ? google.maps.geometry.encoding.decodePath(codificada)
            : puntos;

        return new google.maps.Polyline({
            map: mapa,
            path: camino,
            strokeColor: color || '#1565c0',
            strokeOpacity: codificada ? 0.85 : 0.6,
            strokeWeight: codificada ? 5 : 3,
            icons: codificada ? [] : [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, scale: 3 }, offset: '0', repeat: '14px' }]
        });
    }

    function encuadrar(mapa, puntos) {
        if (!puntos || !puntos.length) { return; }
        if (puntos.length === 1) { mapa.setCenter(puntos[0]); mapa.setZoom(16); return; }
        var b = new google.maps.LatLngBounds();
        puntos.forEach(function (p) { b.extend(p); });
        mapa.fitBounds(b, 40);
    }

    window.FruverMapas = {
        disponible: !!(config() && config().llave),
        config: config,
        cargar: cargar,
        crearMapa: crearMapa,
        marcador: marcador,
        dibujarRuta: dibujarRuta,
        encuadrar: encuadrar
    };
})();
