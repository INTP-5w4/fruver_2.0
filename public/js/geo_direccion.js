/*
 * Ubicación de una dirección en el mapa (formularios de dirección y almacén).
 *
 * Marcado esperado dentro del <form> (ver app/Views/partials/geo_direccion.php):
 *   <div data-geo data-lat="" data-lng="">
 *     <div data-geo-buscador></div>   ← autocompletado de Google Places
 *     <div data-geo-mapa></div>       ← mapa con pin arrastrable
 *     <input type="hidden" name="geo_lat"> ... geo_lng, geo_place_id, geo_formateada, geo_origen
 *   </div>
 *
 * Al elegir un resultado del autocompletado se llenan calle, número, colonia,
 * municipio, estado y CP del formulario. Si el usuario mueve el pin, el origen
 * pasa a "manual" (dato propio del negocio, no vence).
 *
 * Los campos ocultos SOLO se llenan cuando el usuario elige un lugar o mueve el
 * pin; las coordenadas que ya tenía la dirección se muestran pero no se reenvían
 * (así no se "renueva" artificialmente el caché de 30 días de Google).
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
                if (v === buscado || t === buscado || buscado.indexOf(v) !== -1 || v.indexOf(buscado) !== -1) {
                    el.value = o.value;
                    return;
                }
            }
            return;
        }
        el.value = valor;
    }

    /** Convierte addressComponents (Places New o Geocoder clásico) a {tipo: texto}. */
    function componentes(lista) {
        var r = {};
        (lista || []).forEach(function (c) {
            var largo = c.longText || c.long_name || '';
            (c.types || []).forEach(function (t) { if (!r[t]) { r[t] = largo; } });
        });
        return r;
    }

    function llenarFormulario(form, comp) {
        poner(form, ['calle'], comp.route);
        poner(form, ['num'], comp.street_number);
        poner(form, ['col'], comp.sublocality_level_1 || comp.neighborhood || comp.sublocality);
        poner(form, ['mun'], comp.locality || comp.administrative_area_level_2);
        poner(form, ['edo'], comp.administrative_area_level_1);
        poner(form, ['cp'], comp.postal_code);
    }

    function iniciar(caja) {
        if (caja.__geoListo) { return; }
        caja.__geoListo = true;

        var form   = caja.closest('form');
        var mapaEl = caja.querySelector('[data-geo-mapa]');
        var busEl  = caja.querySelector('[data-geo-buscador]');
        var estado = caja.querySelector('[data-mapa-estado]');
        var h      = function (n) { return caja.querySelector('input[name="' + n + '"]'); };

        function fijar(pos, origen, placeId, formateada) {
            h('geo_lat').value = pos.lat.toFixed(7);
            h('geo_lng').value = pos.lng.toFixed(7);
            h('geo_origen').value = origen;
            h('geo_place_id').value = placeId || '';
            h('geo_formateada').value = formateada || '';
            caja.classList.add('geo-ubicada');
        }

        FruverMapas.cargar().then(function () {
            var c       = FruverMapas.config();
            var lat     = parseFloat(caja.getAttribute('data-lat'));
            var lng     = parseFloat(caja.getAttribute('data-lng'));
            var tiene   = !isNaN(lat) && !isNaN(lng);
            var centro  = tiene ? { lat: lat, lng: lng } : { lat: c.lat, lng: c.lng };
            var mapa    = FruverMapas.crearMapa(mapaEl, centro, tiene ? 17 : 12);
            var pin     = FruverMapas.marcador(mapa, centro, { arrastrable: true, titulo: 'Arrastra para ajustar' });

            caja.__geo = { mapa: mapa, pin: pin };

            pin.alSoltar(function () { fijar(pin.posicion(), 'manual', h('geo_place_id').value, h('geo_formateada').value); });
            mapa.addListener('click', function (ev) {
                var p = { lat: ev.latLng.lat(), lng: ev.latLng.lng() };
                pin.mover(p);
                fijar(p, 'manual', h('geo_place_id').value, h('geo_formateada').value);
            });

            return google.maps.importLibrary('places').then(function (places) {
                if (!busEl || !places.PlaceAutocompleteElement) { return; }

                var auto = new places.PlaceAutocompleteElement({
                    includedRegionCodes: [c.region.toLowerCase()],
                    locationBias: centro
                });
                auto.setAttribute('placeholder', 'Busca la dirección (calle y número, colonia…)');
                busEl.appendChild(auto);

                function usarLugar(lugar) {
                    return lugar.fetchFields({ fields: ['location', 'formattedAddress', 'addressComponents', 'id'] }).then(function () {
                        if (!lugar.location) { return; }
                        var p = { lat: lugar.location.lat(), lng: lugar.location.lng() };
                        pin.mover(p);
                        mapa.setCenter(p);
                        mapa.setZoom(18);
                        llenarFormulario(form, componentes(lugar.addressComponents));
                        var texto = form.querySelector('input[type="text"][name="direccion"]');
                        if (texto && !texto.value && lugar.formattedAddress) { texto.value = lugar.formattedAddress; }
                        fijar(p, 'google', lugar.id, lugar.formattedAddress);
                    });
                }

                // Evento actual: gmp-select (placePrediction). Anterior: gmp-placeselect (place).
                auto.addEventListener('gmp-select', function (ev) {
                    if (ev.placePrediction) { usarLugar(ev.placePrediction.toPlace()); }
                });
                auto.addEventListener('gmp-placeselect', function (ev) {
                    if (ev.place) { usarLugar(ev.place); }
                });
            });
        }).catch(function (err) {
            if (estado) {
                estado.textContent = err && err.message ? err.message + ' La dirección se ubicará al guardar.' : 'Mapa no disponible.';
                estado.hidden = false;
            }
            if (mapaEl) { mapaEl.hidden = true; }
        });
    }

    /** Mueve el pin a una dirección existente (p. ej. al abrir el modal de edición). */
    function posicionar(caja, lat, lng) {
        ['geo_lat', 'geo_lng', 'geo_place_id', 'geo_formateada', 'geo_origen'].forEach(function (n) {
            var el = caja.querySelector('input[name="' + n + '"]');
            if (el) { el.value = ''; }
        });
        caja.classList.remove('geo-ubicada');
        caja.setAttribute('data-lat', lat || '');
        caja.setAttribute('data-lng', lng || '');

        if (caja.__geo && lat && lng) {
            var p = { lat: parseFloat(lat), lng: parseFloat(lng) };
            caja.__geo.pin.mover(p);
            caja.__geo.mapa.setCenter(p);
            caja.__geo.mapa.setZoom(17);
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
                if (e) { e.textContent = 'Mapa desactivado: la dirección se ubicará automáticamente al guardar (si Google Maps está configurado en el servidor).'; e.hidden = false; }
            });
            return;
        }

        // Los mapas dentro de modales se crean cuando el modal se vuelve visible.
        var obs = 'IntersectionObserver' in window ? new IntersectionObserver(function (entradas) {
            entradas.forEach(function (en) {
                if (en.isIntersecting) { iniciar(en.target); obs.unobserve(en.target); }
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
