/*
 * FruverMapas — capa común para los mapas de Fruver.
 *
 * Dos motores con la MISMA interfaz, según <meta name="fruver-mapas" data-proveedor>:
 *   - leaflet : OpenStreetMap con Leaflet (openrouteservice o modo local). Sin llave.
 *   - google  : Google Maps JavaScript API (llave del navegador).
 * Sin la etiqueta meta, FruverMapas.disponible es false y las páginas funcionan sin mapa.
 *
 * Interfaz (lo único que usan las pantallas):
 *   cargar() → Promise · crearMapa(el, centro?, zoom?) → mapa
 *   marcador(mapa, pos, {texto, color, borde, titulo, arrastrable}) → {posicion, mover, alSoltar, alClic, quitar}
 *   dibujarRuta(mapa, polylineCodificada|null, puntos, color) → {quitar}
 *   encuadrar(mapa, puntos) · centrar(mapa, pos, zoom) · alClicMapa(mapa, fn(pos))
 *   puntoUsuario(mapa) → {mover(pos)} · ajustar(mapa)  (tras mostrar un contenedor oculto)
 *   decodificar(polyline) → [{lat, lng}]   (algoritmo estándar, precisión 5)
 */
(function () {
    'use strict';

    var meta = document.querySelector('meta[name="fruver-mapas"]');

    function config() {
        if (!meta) { return null; }
        var a = function (n, d) { return meta.getAttribute(n) || d; };
        return {
            proveedor: a('data-proveedor', 'leaflet'),
            lat: parseFloat(a('data-lat', '19.0433')),
            lng: parseFloat(a('data-lng', '-98.1981')),
            idioma: a('data-idioma', 'es'),
            region: a('data-region', 'MX'),
            // leaflet
            tiles: a('data-tiles', ''),
            atribucion: a('data-atribucion', ''),
            buscar: a('data-buscar', ''),
            // google
            llave: a('data-llave', ''),
            mapId: a('data-map-id', ''),
            // 1 = superadmin/developer: los errores de configuración muestran el detalle técnico
            tecnico: a('data-tecnico', '0') === '1'
        };
    }

    var cfg = config();

    function estado(texto) {
        document.querySelectorAll('[data-mapa-estado]').forEach(function (el) {
            el.textContent = texto;
            el.hidden = false;
        });
    }

    /** Polyline codificada (Google / openrouteservice, precisión 5) → puntos. */
    function decodificar(codigo) {
        var puntos = [], i = 0, lat = 0, lng = 0;
        while (i < codigo.length) {
            var b, desplazamiento = 0, resultado = 0;
            do { b = codigo.charCodeAt(i++) - 63; resultado |= (b & 0x1f) << desplazamiento; desplazamiento += 5; } while (b >= 0x20);
            lat += (resultado & 1) ? ~(resultado >> 1) : (resultado >> 1);
            desplazamiento = 0; resultado = 0;
            do { b = codigo.charCodeAt(i++) - 63; resultado |= (b & 0x1f) << desplazamiento; desplazamiento += 5; } while (b >= 0x20);
            lng += (resultado & 1) ? ~(resultado >> 1) : (resultado >> 1);
            puntos.push({ lat: lat / 1e5, lng: lng / 1e5 });
        }
        return puntos;
    }

    function textoSeguro(t) {
        return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ═════════════════════════ LEAFLET ═════════════════════════
    var Leaflet = {
        cargar: function () {
            return window.L ? Promise.resolve(window.L) : Promise.reject(new Error('No se pudo cargar la librería del mapa (Leaflet). Revisa tu conexión a internet.'));
        },

        crearMapa: function (el, centro, zoom) {
            var c = centro || { lat: cfg.lat, lng: cfg.lng };
            var mapa = L.map(el, { zoomControl: true, attributionControl: true }).setView([c.lat, c.lng], zoom || 13);
            L.tileLayer(cfg.tiles || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: cfg.atribucion || '&copy; OpenStreetMap',
                referrerPolicy: 'strict-origin-when-cross-origin'
            }).addTo(mapa);
            setTimeout(function () { mapa.invalidateSize(); }, 200);
            return mapa;
        },

        marcador: function (mapa, pos, op) {
            op = op || {};
            var color = op.color || '#2e7d32';
            var icono = L.divIcon({
                className: 'fv-pin-caja',
                html: '<div class="fv-pin" style="background:' + textoSeguro(color) + ';border-color:' + textoSeguro(op.borde || color) + '"><span>' + textoSeguro(op.texto || '') + '</span></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            });
            var m = L.marker([pos.lat, pos.lng], { icon: icono, draggable: !!op.arrastrable, title: op.titulo || '', keyboard: true }).addTo(mapa);

            return {
                posicion: function () { var p = m.getLatLng(); return { lat: p.lat, lng: p.lng }; },
                mover: function (p) { m.setLatLng([p.lat, p.lng]); },
                alSoltar: function (fn) { m.on('dragend', fn); },
                alClic: function (fn) { m.on('click', fn); },
                quitar: function () { m.remove(); }
            };
        },

        dibujarRuta: function (mapa, codificada, puntos, color) {
            var camino = (codificada ? decodificar(codificada) : puntos).map(function (p) { return [p.lat, p.lng]; });
            var linea = L.polyline(camino, {
                color: color || '#1565c0',
                weight: codificada ? 5 : 3,
                opacity: codificada ? 0.85 : 0.7,
                dashArray: codificada ? null : '6 8'
            }).addTo(mapa);
            return { quitar: function () { linea.remove(); } };
        },

        encuadrar: function (mapa, puntos) {
            if (!puntos || !puntos.length) { return; }
            if (puntos.length === 1) { mapa.setView([puntos[0].lat, puntos[0].lng], 16); return; }
            mapa.fitBounds(puntos.map(function (p) { return [p.lat, p.lng]; }), { padding: [30, 30] });
        },

        centrar: function (mapa, p, zoom) { mapa.setView([p.lat, p.lng], zoom || mapa.getZoom()); },

        alClicMapa: function (mapa, fn) { mapa.on('click', function (ev) { fn({ lat: ev.latlng.lat, lng: ev.latlng.lng }); }); },

        puntoUsuario: function (mapa) {
            var c = null;
            return {
                mover: function (p) {
                    if (!c) { c = L.circleMarker([p.lat, p.lng], { radius: 8, color: '#ffffff', weight: 2, fillColor: '#1a73e8', fillOpacity: 0.95 }).addTo(mapa); }
                    else { c.setLatLng([p.lat, p.lng]); }
                }
            };
        },

        ajustar: function (mapa) { mapa.invalidateSize(); }
    };

    // ═════════════════════════ GOOGLE ═════════════════════════
    var promesaGoogle = null;
    var Google = {
        cargar: function () {
            if (!cfg.llave) { return Promise.reject(new Error('Google Maps no está configurado.')); }
            if (promesaGoogle) { return promesaGoogle; }
            promesaGoogle = new Promise(function (resolver, rechazar) {
                var cb = '__fruverMapasListo';
                window[cb] = function () { resolver(window.google); };
                var s = document.createElement('script');
                s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(cfg.llave)
                    + '&v=weekly&loading=async&libraries=places,marker,geometry'
                    + '&language=' + encodeURIComponent(cfg.idioma) + '&region=' + encodeURIComponent(cfg.region)
                    + '&callback=' + cb;
                s.async = true;
                s.onerror = function () { promesaGoogle = null; rechazar(new Error('No se pudo cargar Google Maps.')); };
                document.head.appendChild(s);
            });
            window.gm_authFailure = function () {
                estado(cfg.tecnico
                    ? 'Google Maps rechazó la llave del navegador (revisa rutas.llaveNavegador y sus restricciones).'
                    : 'El mapa no está disponible por ahora. Avisa al administrador del sistema.');
            };
            return promesaGoogle;
        },

        crearMapa: function (el, centro, zoom) {
            var op = {
                center: centro || { lat: cfg.lat, lng: cfg.lng }, zoom: zoom || 13,
                streetViewControl: false, mapTypeControl: false, fullscreenControl: true,
                clickableIcons: false, gestureHandling: 'greedy'
            };
            if (cfg.mapId) { op.mapId = cfg.mapId; }
            return new google.maps.Map(el, op);
        },

        marcador: function (mapa, pos, op) {
            op = op || {};
            var m;
            if (cfg.mapId && google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
                var pin = new google.maps.marker.PinElement({
                    glyph: op.texto ? String(op.texto) : undefined,
                    background: op.color || '#2e7d32', borderColor: op.borde || '#1b5e20', glyphColor: '#ffffff'
                });
                m = new google.maps.marker.AdvancedMarkerElement({
                    map: mapa, position: pos, content: pin.element, title: op.titulo || '', gmpDraggable: !!op.arrastrable
                });
                return {
                    posicion: function () {
                        var p = m.position;
                        return p ? { lat: typeof p.lat === 'function' ? p.lat() : p.lat, lng: typeof p.lng === 'function' ? p.lng() : p.lng } : null;
                    },
                    mover: function (p) { m.position = p; },
                    alSoltar: function (fn) { m.addListener('dragend', fn); },
                    alClic: function (fn) { m.addListener('click', fn); },
                    quitar: function () { m.map = null; }
                };
            }
            m = new google.maps.Marker({
                map: mapa, position: pos, draggable: !!op.arrastrable, title: op.titulo || '',
                label: op.texto ? { text: String(op.texto), color: '#ffffff', fontWeight: 'bold' } : undefined
            });
            return {
                posicion: function () { var p = m.getPosition(); return p ? { lat: p.lat(), lng: p.lng() } : null; },
                mover: function (p) { m.setPosition(p); },
                alSoltar: function (fn) { m.addListener('dragend', fn); },
                alClic: function (fn) { m.addListener('click', fn); },
                quitar: function () { m.setMap(null); }
            };
        },

        dibujarRuta: function (mapa, codificada, puntos, color) {
            var linea = new google.maps.Polyline({
                map: mapa,
                path: codificada ? decodificar(codificada) : puntos,
                strokeColor: color || '#1565c0',
                strokeOpacity: codificada ? 0.85 : 0.6,
                strokeWeight: codificada ? 5 : 3
            });
            return { quitar: function () { linea.setMap(null); } };
        },

        encuadrar: function (mapa, puntos) {
            if (!puntos || !puntos.length) { return; }
            if (puntos.length === 1) { mapa.setCenter(puntos[0]); mapa.setZoom(16); return; }
            var b = new google.maps.LatLngBounds();
            puntos.forEach(function (p) { b.extend(p); });
            mapa.fitBounds(b, 40);
        },

        centrar: function (mapa, p, zoom) { mapa.setCenter(p); if (zoom) { mapa.setZoom(zoom); } },

        alClicMapa: function (mapa, fn) { mapa.addListener('click', function (ev) { fn({ lat: ev.latLng.lat(), lng: ev.latLng.lng() }); }); },

        puntoUsuario: function (mapa) {
            var c = null;
            return {
                mover: function (p) {
                    if (!c) { c = new google.maps.Circle({ map: mapa, center: p, radius: 12, fillColor: '#1a73e8', fillOpacity: 0.9, strokeColor: '#fff', strokeWeight: 2 }); }
                    else { c.setCenter(p); }
                }
            };
        },

        ajustar: function () {}
    };

    var motor = cfg && cfg.proveedor === 'google' ? Google : Leaflet;

    window.FruverMapas = {
        disponible: !!cfg && (cfg.proveedor !== 'google' || !!cfg.llave),
        proveedor: cfg ? cfg.proveedor : '',
        config: function () { return cfg; },
        cargar: function () { return cfg ? motor.cargar() : Promise.reject(new Error('Mapa no configurado.')); },
        crearMapa: function (el, c, z) { return motor.crearMapa(el, c, z); },
        marcador: function (m, p, o) { return motor.marcador(m, p, o); },
        dibujarRuta: function (m, c, p, col) { return motor.dibujarRuta(m, c, p, col); },
        encuadrar: function (m, p) { return motor.encuadrar(m, p); },
        centrar: function (m, p, z) { return motor.centrar(m, p, z); },
        alClicMapa: function (m, fn) { return motor.alClicMapa(m, fn); },
        puntoUsuario: function (m) { return motor.puntoUsuario(m); },
        ajustar: function (m) { return motor.ajustar(m); },
        decodificar: decodificar
    };
})();
