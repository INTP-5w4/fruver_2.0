/*
 * Reglas de formato de cada campo, una a la vez.
 *
 * Al enfocar un campo aparece debajo de él la lista de sus reglas, y cada una se marca
 * mientras se escribe: ✓ se cumple, ✗ no se cumple, • pendiente. Solo hay un panel
 * visible: el del campo enfocado. Al salir del campo, si algo no se cumple, el campo
 * queda marcado en rojo. Al enviar, el navegador muestra el mensaje en español.
 *
 * De dónde salen las reglas (en este orden; las últimas mandan):
 *   1. Atributos HTML del campo: required, minlength, maxlength, min, max, step,
 *      type="email|number|date", pattern (con su texto en data-regla-patron).
 *   2. CATALOGO (abajo): copia de las reglas de los controladores, por acción del
 *      formulario y nombre del campo. Si cambias una regla en un controlador, cámbiala aquí.
 *   3. data-reglas='["Nota 1", "Nota 2"]' en el campo: notas fijas que no se pueden
 *      comprobar en el navegador (p. ej. "no uses tu correo en la contraseña").
 *   data-coincide="password" compara el campo con otro del mismo formulario.
 *   data-sin-reglas apaga el panel en un campo.
 *
 * El servidor valida siempre; esto es solo ayuda para llenar bien el formulario.
 * Carga su propia hoja de estilos (estilos/reglas_campo.css).
 */
(function () {
    'use strict';

    // ── Catálogo: acción del formulario → reglas por campo ─────────────────
    // req: obligatorio (true/false; si no se indica, se respeta el atributo required)
    // min / max: caracteres · digitos: solo 0-9 · alfanum: letras y números
    // num: { gt, gte, lt, lte, entero } · fecha · correo · notas: [textos]
    var PERSONA = {
        nom: { req: true, max: 100 },
        ape_pat: { req: true, max: 100 },
        ape_mat: { req: true, max: 100 },
        tel: { req: true, digitos: true, max: 12 }
    };
    var CATALOGO = [
        { accion: /\/(guarda|modifica)_cliente$/, campos: PERSONA },
        {
            accion: /\/(guarda|modifica)_repartidor$/,
            campos: extender(PERSONA, {
                dir: { req: true, max: 500 },
                not: { req: false, max: 500 },
                vehiculo: { req: true }
            })
        },
        {
            accion: /\/(guarda|modifica)_direccion$/,
            campos: {
                col: { req: true, max: 200 },
                calle: { req: true, max: 200 },
                num: { req: true, max: 50 },
                mun: { req: true, max: 200 },
                edo: { req: true },
                id_cliente: { req: true },
                cp: { req: false, alfanum: true, max: 10 },
                referencias: { req: false, max: 300 }
            }
        },
        {
            accion: /\/(guarda_producto|modifica_producto)$/,
            campos: { nom: { req: true, max: 200 }, desc: { req: true, max: 300 }, cat: { req: true } }
        },
        {
            accion: /\/(guarda|modifica)_entrada$/,
            campos: {
                f_ent: { req: true, fecha: true },
                f_cad: { req: false, fecha: true },
                cant: { req: true, num: { gt: 0, lt: 100000000 } },
                u_com: { req: true },
                u_ven: { req: true },
                equi: { req: true, num: { gt: 0 } },
                p_compra: { req: true, num: { gte: 0, lt: 100000000 } },
                p_venta: { req: false, num: { gte: 0 } },
                id_producto: { req: true }
            }
        },
        {
            accion: /\/(guarda|modifica)_existencia$/,
            campos: {
                e_total: { req: true, num: { gte: 0, entero: true } },
                e_bloqueado: { req: true, num: { gte: 0, entero: true } },
                e_venta: { req: true, num: { gte: 0, entero: true } },
                id_producto: { req: true }
            }
        },
        {
            accion: /\/(guarda|modifica)_merma$/,
            campos: {
                cant: { req: true, num: { gt: 0 } },
                cantidad: { req: true, num: { gt: 0 } },
                fecha: { req: true, fecha: true },
                notas: { req: false, max: 500 },
                id_entrada: { req: true }
            }
        },
        { accion: /\/(guarda|modifica)_pedido$/, campos: { fecha: { req: true, fecha: true }, id_cliente: { req: true } } },
        { accion: /\/(guarda|modifica)_estatus$/, campos: { edo: { req: true }, id_pedido: { req: true } } },
        { accion: /\/asigna_entrega$/, campos: { id_pedido: { req: true }, id_repartidor: { req: true } } },
        {
            accion: /\/entregas\/\d+\/entregar$/,
            campos: { monto: { num: { gte: 0 } }, notas: { req: false, max: 500 } }
        },
        { accion: /\/entregas\/\d+\/fallar$/, campos: { incidencia: { req: true, max: 500 } } },
        { accion: /\/rutas\/almacen$/, campos: { nombre: { req: true, max: 100 }, direccion: { req: true, max: 300 } } },
        { accion: /\/tienda\/pedido$/, campos: { tel: { digitos: true, min: 7, max: 12 }, dir_cp: { alfanum: true, max: 10 } } }
    ];

    function extender(a, b) {
        var r = {}, k;
        for (k in a) { if (Object.prototype.hasOwnProperty.call(a, k)) { r[k] = a[k]; } }
        for (k in b) { if (Object.prototype.hasOwnProperty.call(b, k)) { r[k] = b[k]; } }
        return r;
    }

    function rutaAccion(form) {
        if (!form) { return ''; }
        try { return new URL(form.getAttribute('action') || location.href, location.href).pathname.replace(/\/+$/, ''); }
        catch (e) { return ''; }
    }

    function delCatalogo(el) {
        var ruta = rutaAccion(el.form), nombre = el.name;
        for (var i = 0; i < CATALOGO.length; i++) {
            if (CATALOGO[i].accion.test(ruta) && CATALOGO[i].campos[nombre]) { return CATALOGO[i].campos[nombre]; }
        }
        return null;
    }

    function esCampoValido(el) {
        if (!el || !el.matches || !el.matches('input, select, textarea')) { return false; }
        if (el.hasAttribute('data-sin-reglas') || el.readOnly || el.disabled) { return false; }
        var t = (el.type || '').toLowerCase();
        return ['hidden', 'checkbox', 'radio', 'submit', 'button', 'reset', 'image', 'search', 'range', 'color'].indexOf(t) === -1
            && el.name !== 'buscar';
    }

    // ── Construcción de la lista de reglas ─────────────────────────────────
    function numero(v) { return /^-?\d+(\.\d+)?$/.test(v) ? parseFloat(v) : NaN; }
    function largo(v) { return Array.from(v).length; }
    function fmt(n) { return Number(n).toLocaleString('es-MX'); }

    function reglasDe(el) {
        var spec = {}, cat = delCatalogo(el), r = [], t = (el.type || '').toLowerCase();
        var esLista = el.tagName === 'SELECT';

        // 1. Atributos
        spec.req = el.required;
        if (el.minLength > 0) { spec.min = el.minLength; }
        if (el.maxLength > 0 && el.maxLength < 524288) { spec.max = el.maxLength; }
        if (t === 'email') { spec.correo = true; }
        if (t === 'date') { spec.fecha = true; }
        if (t === 'number') {
            spec.num = {};
            if (el.min !== '') { spec.num.gte = parseFloat(el.min); }
            if (el.max !== '') { spec.num.lte = parseFloat(el.max); }
            if (el.step === '' || el.step === '1') { spec.num.entero = true; }
        }
        if (el.pattern) { spec.patron = { re: el.pattern, texto: el.getAttribute('data-regla-patron') || el.title || 'Formato válido' }; }
        if (el.getAttribute('data-coincide')) { spec.coincide = el.getAttribute('data-coincide'); }

        // 2. Catálogo
        if (cat) {
            Object.keys(cat).forEach(function (k) { spec[k] = cat[k]; });
        }

        // Sin repetir: el pattern simple ya está cubierto por "solo números" / "letras y números".
        if (spec.patron && ((spec.digitos && spec.patron.re === '[0-9]*') || (spec.alfanum && spec.patron.re === '[A-Za-z0-9]*'))) {
            delete spec.patron;
        }

        // 3. Notas fijas
        var notas = [];
        try { notas = JSON.parse(el.getAttribute('data-reglas') || '[]'); } catch (e) { notas = []; }
        if (spec.notas) { notas = spec.notas.concat(notas); }

        if (spec.req) {
            r.push({ texto: esLista ? 'Elige una opción' : 'Obligatorio', req: true, prueba: function (v) { return v.trim() !== ''; } });
        }
        if (spec.digitos) {
            r.push({ texto: 'Solo números (sin espacios ni guiones)', prueba: function (v) { return /^\d*$/.test(v); } });
        }
        if (spec.alfanum) {
            r.push({ texto: 'Solo letras y números, sin espacios ni acentos', prueba: function (v) { return /^[A-Za-z0-9]*$/.test(v); } });
        }
        if (spec.correo) {
            r.push({ texto: 'Formato de correo: nombre@dominio.com', prueba: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); } });
        }
        if (spec.fecha) {
            r.push({ texto: 'Fecha válida (día/mes/año)', prueba: function (v) { return /^\d{4}-\d{2}-\d{2}$/.test(v); } });
        }
        if (spec.num) {
            var n = spec.num;
            r.push({ texto: n.entero ? 'Número entero' : 'Número (puede llevar decimales)', prueba: function (v) {
                var x = numero(v.trim());
                return !isNaN(x) && (!n.entero || Math.floor(x) === x);
            } });
            if (n.gt !== undefined) { r.push({ texto: 'Mayor que ' + fmt(n.gt), prueba: function (v) { return numero(v) > n.gt; } }); }
            if (n.gte !== undefined) { r.push({ texto: 'Mayor o igual a ' + fmt(n.gte), prueba: function (v) { return numero(v) >= n.gte; } }); }
            if (n.lt !== undefined) { r.push({ texto: 'Menor que ' + fmt(n.lt), prueba: function (v) { return numero(v) < n.lt; } }); }
            if (n.lte !== undefined) { r.push({ texto: 'Menor o igual a ' + fmt(n.lte), prueba: function (v) { return numero(v) <= n.lte; } }); }
        }
        if (spec.min && spec.max) {
            r.push({ texto: 'Entre ' + spec.min + ' y ' + spec.max + ' caracteres', contador: spec.max,
                prueba: function (v) { var l = largo(v); return l >= spec.min && l <= spec.max; } });
        } else if (spec.min) {
            r.push({ texto: 'Mínimo ' + spec.min + ' caracteres', prueba: function (v) { return largo(v) >= spec.min; } });
        } else if (spec.max && !esLista) {
            r.push({ texto: 'Máximo ' + spec.max + ' caracteres', contador: spec.max, prueba: function (v) { return largo(v) <= spec.max; } });
        }
        if (spec.patron) {
            var re;
            try { re = new RegExp('^(?:' + spec.patron.re + ')$', 'u'); } catch (e) { re = null; }
            if (re) { r.push({ texto: spec.patron.texto, prueba: function (v) { return re.test(v); } }); }
        }
        if (spec.coincide && el.form) {
            var otro = el.form.elements[spec.coincide];
            r.push({ texto: 'Igual a la anterior', prueba: function (v) { return !!otro && v === otro.value; } });
        }
        if (!spec.req && r.length) {
            r.push({ texto: 'Opcional: puedes dejarlo vacío', nota: true });
        }
        notas.forEach(function (txt) { r.push({ texto: String(txt), nota: true }); });

        return { lista: r, spec: spec };
    }

    /** Pone en el campo los atributos que corresponden a su regla (límite, teclado, obligatorio). */
    function aplicarAtributos(el) {
        var cat = delCatalogo(el);
        if (!cat) { return; }
        if (cat.req === true) { el.required = true; }
        if (cat.req === false) { el.required = false; }
        if (cat.max && el.tagName !== 'SELECT' && !(el.maxLength > 0)) { el.maxLength = cat.max; }
        if (cat.min && !(el.minLength > 0)) { el.minLength = cat.min; }
        if (cat.digitos) {
            if (!el.getAttribute('inputmode')) { el.setAttribute('inputmode', 'numeric'); }
            if (!el.pattern) { el.pattern = '[0-9]*'; el.setAttribute('data-regla-patron', 'Solo números'); }
        }
        if (cat.alfanum && !el.pattern) { el.pattern = '[A-Za-z0-9]*'; el.setAttribute('data-regla-patron', 'Solo letras y números'); }
    }

    // ── Estado de cada regla ───────────────────────────────────────────────
    function evaluar(el, reglas) {
        var v = el.value || '';
        var tocado = el.dataset.reglaTocado === '1';
        return reglas.lista.map(function (r) {
            if (r.nota) { return 'nota'; }
            // Vacío: pendiente; "obligatorio" solo se marca en rojo si ya pasó por el campo.
            if (v === '' && (!r.req || !tocado)) { return 'pendiente'; }
            return r.prueba(v) ? 'ok' : 'mal';
        });
    }

    function primerError(el) {
        var reglas = reglasDe(el), estados = evaluar(el, reglas);
        for (var i = 0; i < estados.length; i++) {
            if (estados[i] === 'mal') {
                if (reglas.lista[i].req) { return el.tagName === 'SELECT' ? 'Elige una opción de la lista.' : 'Este campo es obligatorio.'; }
                return reglas.lista[i].texto + '.';
            }
        }
        return '';
    }

    function etiqueta(el) {
        var lab = (el.id && document.querySelector('label[for="' + CSS.escape(el.id) + '"]')) || el.closest('label');
        if (!lab) {
            var prev = el.previousElementSibling;
            if (prev && prev.tagName === 'LABEL') { lab = prev; }
        }
        if (!lab && el.parentElement && el.parentElement.classList.contains('form-floating')) {
            lab = el.parentElement.querySelector('label');
        }
        var txt = lab ? lab.textContent : (el.getAttribute('aria-label') || el.placeholder || el.name);
        return txt.replace(/\*/g, '').replace(/\s+/g, ' ').trim();
    }

    // ── Panel (uno a la vez) ───────────────────────────────────────────────
    var panel = null, campoActual = null, contador = 0;

    function ancla(el) {
        var p = el.parentElement;
        if (p && (p.tagName === 'LABEL' || p.classList.contains('form-floating') || p.classList.contains('geo-busqueda')
            || p.classList.contains('prod-search-wrap'))) { return p; }
        return el;
    }

    function quitarPanel() {
        if (panel && panel.parentNode) { panel.parentNode.removeChild(panel); }
        if (campoActual) {
            var ids = (campoActual.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (x) { return x && x !== 'reglas-campo-panel'; });
            if (ids.length) { campoActual.setAttribute('aria-describedby', ids.join(' ')); } else { campoActual.removeAttribute('aria-describedby'); }
        }
        panel = null;
        campoActual = null;
    }

    function pintar(el) {
        var reglas = reglasDe(el);
        if (!reglas.lista.length) { quitarPanel(); return; }

        if (campoActual !== el) {
            quitarPanel();
            panel = document.createElement('div');
            panel.className = 'regla-campo';
            panel.id = 'reglas-campo-panel';
            var a = ancla(el);
            a.parentNode.insertBefore(panel, a.nextSibling);
            // Pegado al campo: compensa el margen inferior que traiga (los márgenes se suman o se colapsan).
            var mb = parseFloat(getComputedStyle(a).marginBottom) || 0;
            panel.style.marginTop = (6 - mb) + 'px';
            campoActual = el;
            var ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (ids.indexOf(panel.id) === -1) { ids.push(panel.id); }
            el.setAttribute('aria-describedby', ids.join(' '));
        }

        var estados = evaluar(el, reglas);
        var titulo = document.createElement('p');
        titulo.className = 'regla-campo-titulo';
        titulo.textContent = 'Formato de «' + etiqueta(el) + '»';
        var ul = document.createElement('ul');
        reglas.lista.forEach(function (r, i) {
            var li = document.createElement('li');
            li.className = estados[i];
            var texto = r.texto;
            if (r.contador) { texto += ' (' + largo(el.value || '') + '/' + r.contador + ')'; }
            li.textContent = texto;
            var sr = document.createElement('span');
            sr.className = 'regla-campo-sr';
            sr.textContent = { ok: ' (cumple)', mal: ' (no cumple)', pendiente: '', nota: '' }[estados[i]];
            li.appendChild(sr);
            ul.appendChild(li);
        });
        panel.replaceChildren(titulo, ul);
    }

    function marcar(el) {
        var error = primerError(el);
        var mostrar = error !== '' && (el.value !== '' || el.dataset.reglaTocado === '1');
        el.classList.toggle('campo-con-error', mostrar);
        if (mostrar) { el.setAttribute('aria-invalid', 'true'); } else { el.removeAttribute('aria-invalid'); }
        // Mensaje en español al enviar (el navegador lo muestra en su globo de aviso).
        el.setCustomValidity(error);
    }

    // ── Eventos ────────────────────────────────────────────────────────────
    document.addEventListener('focusin', function (ev) {
        var el = ev.target;
        if (!esCampoValido(el)) { if (panel && !panel.contains(el)) { quitarPanel(); } return; }
        aplicarAtributos(el);
        el.setCustomValidity('');
        pintar(el);
    });

    document.addEventListener('focusout', function (ev) {
        var el = ev.target;
        if (!esCampoValido(el)) { return; }
        el.dataset.reglaTocado = '1';
        marcar(el);
        // Se quita después, por si el foco pasa a otro campo (ese pinta el suyo).
        setTimeout(function () {
            if (campoActual === el && document.activeElement !== el) { quitarPanel(); }
        }, 0);
    });

    function alCambiar(ev) {
        var el = ev.target;
        if (!esCampoValido(el)) { return; }
        el.setCustomValidity('');
        if (el === campoActual) { pintar(el); }
        if (el.classList.contains('campo-con-error')) { marcar(el); }
        // Si otro campo depende de este (confirmar contraseña), se vuelve a revisar.
        if (el.form) {
            el.form.querySelectorAll('[data-coincide="' + el.name + '"]').forEach(function (o) {
                if (o.value !== '') { marcar(o); }
            });
        }
    }
    document.addEventListener('input', alCambiar);
    document.addEventListener('change', alCambiar);

    // Al pulsar "Guardar" (o Enter), antes de que el navegador valide: se revisan todos los
    // campos con los valores actuales (los modales de edición los llenan por código).
    document.addEventListener('click', function (ev) {
        var boton = ev.target.closest ? ev.target.closest('button, input[type="submit"]') : null;
        if (!boton || !boton.form || (boton.type || '').toLowerCase() !== 'submit' || boton.formNoValidate) { return; }
        Array.prototype.forEach.call(boton.form.elements, function (el) {
            if (esCampoValido(el)) {
                aplicarAtributos(el);
                el.dataset.reglaTocado = '1';
                el.setCustomValidity(primerError(el));
            }
        });
    }, true);

    // Campo rechazado al enviar: se marca en rojo (el navegador muestra el mensaje en español).
    document.addEventListener('invalid', function (ev) {
        var el = ev.target;
        if (!esCampoValido(el)) { return; }
        el.dataset.reglaTocado = '1';
        el.classList.add('campo-con-error');
        el.setAttribute('aria-invalid', 'true');
        if (!el.validity.customError) {
            var error = primerError(el);
            if (error) { el.setCustomValidity(error); }
        }
    }, true);

    // Atributos (maxlength, inputmode, required) desde el inicio, para que el navegador los respete.
    function prepararTodo() {
        document.querySelectorAll('form input, form select, form textarea').forEach(function (el) {
            if (esCampoValido(el)) { aplicarAtributos(el); }
        });
    }

    // Hoja de estilos propia, junto a este script.
    (function () {
        var actual = document.currentScript;
        if (!actual || document.querySelector('link[data-reglas-campo]')) { return; }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = actual.src.replace(/js\/reglas_campo\.js(\?.*)?$/, 'estilos/reglas_campo.css');
        link.setAttribute('data-reglas-campo', '');
        document.head.appendChild(link);
    })();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepararTodo);
    } else {
        prepararTodo();
    }
})();
