/*
 * Ubicación de una dirección en el mapa (formularios de dirección y almacén).
 *
 * Marcado esperado dentro del <form> (ver app/Views/partials/geo_direccion.php):
 *   <div data-geo data-lat="" data-lng="">
 *     <div data-geo-buscador></div>   ← buscador de direcciones
 *     <div data-geo-mapa></div>       ← mapa con pin arrastrable
 *     <input type="hidden" name="geo_lat"> ... geo_lng, geo_place_id, geo_formateada, geo_origen, geo_precision
 *   </div>
 *
 * Buscador según el proveedor:
 *   - openrouteservice: caja propia que consulta /direcciones/buscar (nuestro servidor;
 *     la llave nunca llega al navegador).
 *   - Google: autocompletado de Google Places.
 *   - local: sin buscador; se ubica tocando el mapa.
 * Al elegir un resultado se llenan calle, número, colonia, municipio, estado y CP.
 * Si el usuario mueve el pin, el origen pasa a "manual" (dato propio, no vence).
 *
 * Los campos ocultos SOLO se llenan cuando el usuario elige un lugar o mueve el pin;
 * las coordenadas que ya tenía la dirección se muestran pero no se reenvían.
 */
(function () {
    'use strict';

    if (!window.FruverMapas) { return; }

    function quitarAcentos(t) {
        return (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    }

    function campo(form, nombres) {
        for (var i = 0; i < nombres.length; i++) {
            var el = form.querySelector('[name="' + nombres[i] + '"]');
            if (el) { return el; }
        }
        return null;
    }

    function poner(form, nombres, valor) {
        var el = campo(form, nombres);
        if (!el || !valor) { return; }

        if (el.tagName === 'SELECT') {
            var buscado = quitarAcentos(valor);
            for (var i = 0; i < el.options.length; i++) {
                var o = el.options[i];
                var v = quitarAcentos(o.value), t = quitarAcentos(o.text);
                if (v && (v === buscado || t === buscado || buscado.indexOf(v) !== -1 || v.indexOf(buscado) !== -1)) {
                    el.value = o.value;
                    return;
                }
            }
            return;
        }
        el.value = valor;
    }

    /** addressComponents de Google → {tipo: texto}. */
    function componentesGoogle(lista) {
        var r = {};
        (lista || []).forEach(function (c) {
            var largo = c.longText || c.long_name || '';
            (c.types || []).forEach(function (t) { if (!r[t]) { r[t] = largo; } });
        });
        return {
            calle: r.route, numero: r.street_number,
            colonia: r.sublocality_level_1 || r.neighborhood || r.sublocality,
            municipio: r.locality || r.administrative_area_level_2,
            estado: r.administrative_area_level_1, cp: r.postal_code
        };
    }

    function llenarFormulario(form, d, formateada) {
        poner(form, ['calle'], d.calle);
        poner(form, ['num'], d.numero);
        poner(form, ['col'], d.colonia);
        poner(form, ['mun'], d.municipio);
        poner(form, ['edo'], d.estado);
        poner(form, ['cp'], d.cp || d.codigo_postal);
        var texto = form.querySelector('input[type="text"][name="direccion"]');
        if (texto && !texto.value && formateada) { texto.value = formateada; }
    }

    // ── Buscador propio (openrouteservice vía nuestro servidor) ──────────
    function buscadorPropio(contenedor, urlBuscar, alElegir) {
        var caja = document.createElement('div');
        caja.className = 'geo-busqueda';
        var input = document.createElement('input');
        input.type = 'search';
        input.className = 'modal-input';
        input.placeholder = 'Busca la dirección (calle y número, colonia, municipio)…';
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('aria-label', 'Buscar dirección en el mapa');
        var lista = document.createElement('ul');
        lista.className = 'geo-sugerencias';
        lista.hidden = true;
        caja.appendChild(input);
        caja.appendChild(lista);
        contenedor.appendChild(caja);

        var temporizador = null, ultimo = '', control = null;

        function cerrar() { lista.hidden = true; lista.innerHTML = ''; }

        function mostrar(resultados) {
            lista.innerHTML = '';
            if (!resultados.length) {
                var li = document.createElement('li');
                li.className = 'vacio';
                li.textContent = 'Sin resultados: prueba con calle y colonia, o toca el mapa.';
                lista.appendChild(li);
            }
            resultados.forEach(function (r) {
                var li = document.createElement('li');
                li.tabIndex = 0;
                li.textContent = r.direccion_formateada || (r.lat + ', ' + r.lng);
                if (r.precision_geo === 'APPROXIMATE') {
                    var s = document.createElement('small');
                    s.textContent = ' (aproximada)';
                    li.appendChild(s);
                }
                function elegir() { cerrar(); input.value = r.direccion_formateada || ''; alElegir(r); }
                li.addEventListener('click', elegir);
                li.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); elegir(); } });
                lista.appendChild(li);
            });
            lista.hidden = false;
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(temporizador);
            if (q.length < 4) { cerrar(); return; }
            temporizador = setTimeout(function () {
                if (q === ultimo) { return; }
                ultimo = q;
                if (control) { control.abort(); }
                control = 'AbortController' in window ? new AbortController() : null;
                fetch(urlBuscar + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: control ? control.signal : undefined
                }).then(function (r) { return r.json(); })
                  .then(function (j) { mostrar(j.resultados || []); })
                  .catch(function (e) { if (!e || e.name !== 'AbortError') { cerrar(); } });
            }, 450); // espera a que se deje de escribir: cuida la cuota diaria
        });
        // Enter en el buscador no debe enviar el formulario.
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
        document.addEventListener('click', function (e) { if (!caja.contains(e.target)) { cerrar(); } });
    }

    // ── Autocompletado de Google Places ─────────────────────────────────
    function buscadorGoogle(contenedor, centro, alElegir) {
        return google.maps.importLibrary('places').then(function (places) {
            if (!places.PlaceAutocompleteElement) { return; }
            var auto = new places.PlaceAutocompleteElement({
                includedRegionCodes: [FruverMapas.config().region.toLowerCase()],
                locationBias: centro
            });
            auto.setAttribute('placeholder', 'Busca la dirección (calle y número, colonia…)');
            contenedor.appendChild(auto);

            function usarLugar(lugar) {
                return lugar.fetchFields({ fields: ['location', 'formattedAddress', 'addressComponents', 'id'] }).then(function () {
                    if (!lugar.location) { return; }
                    var d = componentesGoogle(lugar.addressComponents);
                    d.lat = lugar.location.lat();
                    d.lng = lugar.location.lng();
                    d.place_id = lugar.id;
                    d.direccion_formateada = lugar.formattedAddress;
                    alElegir(d, 'google');
                });
            }
            auto.addEventListener('gmp-select', function (ev) { if (ev.placePrediction) { usarLugar(ev.placePrediction.toPlace()); } });
            auto.addEventListener('gmp-placeselect', function (ev) { if (ev.place) { usarLugar(ev.place); } });
        });
    }

    function iniciar(caja) {
        if (caja.__geoListo) { return; }
        caja.__geoListo = true;

        var form   = caja.closest('form');
        var mapaEl = caja.querySelector('[data-geo-mapa]');
        var busEl  = caja.querySelector('[data-geo-buscador]');
        var estado = caja.querySelector('[data-mapa-estado]');
        var h      = function (n) { return caja.querySelector('input[name="' + n + '"]'); };

        function fijar(pos, origen, placeId, formateada, precision) {
            h('geo_lat').value = pos.lat.toFixed(7);
            h('geo_lng').value = pos.lng.toFixed(7);
            h('geo_origen').value = origen;
            h('geo_place_id').value = placeId || '';
            h('geo_formateada').value = formateada || '';
            if (h('geo_precision')) { h('geo_precision').value = precision || ''; }
            caja.classList.add('geo-ubicada');
        }

        FruverMapas.cargar().then(function () {
            var c      = FruverMapas.config();
            var lat    = parseFloat(caja.getAttribute('data-lat'));
            var lng    = parseFloat(caja.getAttribute('data-lng'));
            var tiene  = !isNaN(lat) && !isNaN(lng);
            var centro = tiene ? { lat: lat, lng: lng } : { lat: c.lat, lng: c.lng };
            var mapa   = FruverMapas.crearMapa(mapaEl, centro, tiene ? 17 : 12);
            var pin    = FruverMapas.marcador(mapa, centro, { arrastrable: true, titulo: 'Arrastra para ajustar' });

            caja.__geo = { mapa: mapa, pin: pin };

            pin.alSoltar(function () { fijar(pin.posicion(), 'manual', h('geo_place_id').value, h('geo_formateada').value, ''); });
            FruverMapas.alClicMapa(mapa, function (p) {
                pin.mover(p);
                fijar(p, 'manual', h('geo_place_id').value, h('geo_formateada').value, '');
            });

            function alElegir(d, origen) {
                var p = { lat: parseFloat(d.lat), lng: parseFloat(d.lng) };
                pin.mover(p);
                FruverMapas.centrar(mapa, p, d.precision_geo === 'APPROXIMATE' ? 16 : 18);
                llenarFormulario(form, d, d.direccion_formateada);
                fijar(p, origen || 'osm', d.place_id, d.direccion_formateada, d.precision_geo);
                if (estado) {
                    estado.hidden = d.precision_geo !== 'APPROXIMATE';
                    estado.textContent = 'Ubicación aproximada: arrastra el pin hasta la entrada exacta.';
                }
            }

            if (busEl) {
                if (c.proveedor === 'google') {
                    return buscadorGoogle(busEl, centro, alElegir);
                }
                if (c.buscar) {
                    buscadorPropio(busEl, c.buscar, alElegir);
                } else if (estado) {
                    estado.textContent = 'Toca el mapa (o arrastra el pin) para marcar la entrada exacta.';
                    estado.hidden = false;
                }
            }
        }).catch(function (err) {
            if (estado) {
                estado.textContent = (err && err.message ? err.message : 'Mapa no disponible.') + ' La dirección se ubicará al guardar, si es posible.';
                estado.hidden = false;
            }
            if (mapaEl) { mapaEl.hidden = true; }
        });
    }

    /** Mueve el pin a una dirección existente (p. ej. al abrir el modal de edición). */
    function posicionar(caja, lat, lng) {
        ['geo_lat', 'geo_lng', 'geo_place_id', 'geo_formateada', 'geo_origen', 'geo_precision'].forEach(function (n) {
            var el = caja.querySelector('input[name="' + n + '"]');
            if (el) { el.value = ''; }
        });
        caja.classList.remove('geo-ubicada');
        caja.setAttribute('data-lat', lat || '');
        caja.setAttribute('data-lng', lng || '');

        if (caja.__geo) {
            FruverMapas.ajustar(caja.__geo.mapa);
            if (lat && lng) {
                var p = { lat: parseFloat(lat), lng: parseFloat(lng) };
                caja.__geo.pin.mover(p);
                FruverMapas.centrar(caja.__geo.mapa, p, 17);
            }
        }
    }

    function preparar() {
        var cajas = document.querySelectorAll('[data-geo]');
        if (!cajas.length) { return; }

        if (!FruverMapas.disponible) {
            cajas.forEach(function (caja) {
                var m = caja.querySelector('[data-geo-mapa]');
                if (m) { m.hidden = true; }
                var e = caja.querySelector('[data-mapa-estado]');
                if (e) { e.textContent = 'Mapa desactivado: la dirección se ubicará automáticamente al guardar, si hay servicio de mapas configurado.'; e.hidden = false; }
            });
            return;
        }

        // Los mapas dentro de modales se crean cuando el modal se vuelve visible.
        var obs = 'IntersectionObserver' in window ? new IntersectionObserver(function (entradas) {
            entradas.forEach(function (en) {
                if (en.isIntersecting) {
                    iniciar(en.target);
                    if (en.target.__geo) { FruverMapas.ajustar(en.target.__geo.mapa); }
                }
            });
        }) : null;

        cajas.forEach(function (caja) { obs ? obs.observe(caja) : iniciar(caja); });
    }

    window.FruverGeo = { posicionar: posicionar };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', preparar);
    } else {
        preparar();
    }
})();
