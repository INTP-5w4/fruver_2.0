/**
 * Ofertas (lista_oferta): ventana para crear o editar, con selección por productos o por
 * categoría. Solo interfaz; el precio con descuento y toda validación de peso los decide el
 * servidor (Ofertas::guardar, Modelo_producto::sqlCatalogo).
 */
(function () {
    'use strict';

    var modal = document.getElementById('modalOferta');
    if (!modal) { return; }

    var form     = document.getElementById('form-oferta');
    var titulo   = document.getElementById('oferta-titulo');
    var signo    = document.getElementById('of-signo');
    var nota     = document.getElementById('of-valor-nota');
    var picker   = document.getElementById('of-picker');
    var buscar   = document.getElementById('of-buscar-prod');
    var selN     = document.getElementById('of-sel-n');
    var radioPrecio = form.querySelector('input[name="tipo"][value="precio"]');

    function bloque(nombre) { return form.querySelector('[data-bloque="' + nombre + '"]'); }
    function alcance() { var r = form.querySelector('input[name="alcance"]:checked'); return r ? r.value : 'productos'; }
    function tipo() { var r = form.querySelector('input[name="tipo"]:checked'); return r ? r.value : 'porcentaje'; }
    function casillas() { return picker ? picker.querySelectorAll('input[type="checkbox"]') : []; }

    function abrir() { modal.style.display = 'block'; }
    function cerrar() { modal.style.display = 'none'; }

    // ── Reglas visibles según las opciones ─────────────────────────────
    function pintarTipo() {
        var esPct = tipo() === 'porcentaje';
        if (signo) { signo.textContent = esPct ? '%' : '$'; }
        if (nota) { nota.textContent = esPct ? 'De 0 a 100.' : 'Nuevo precio en pesos.'; }
    }

    function pintarAlcance() {
        var porCat = alcance() === 'categoria';
        bloque('categoria').hidden = !porCat;
        bloque('productos').hidden = porCat;

        // El precio fijo no aplica a una categoría entera: se fuerza porcentaje.
        if (radioPrecio) {
            radioPrecio.disabled = porCat;
            if (porCat && radioPrecio.checked) {
                form.querySelector('input[name="tipo"][value="porcentaje"]').checked = true;
                pintarTipo();
            }
        }
        var sel = document.getElementById('of-categoria');
        if (sel) { sel.required = porCat; sel.disabled = !porCat; }
        // No enviar casillas cuando la oferta es por categoría.
        casillas().forEach(function (c) { c.disabled = porCat; });
    }

    function contar() {
        var n = 0;
        casillas().forEach(function (c) { if (c.checked && !c.disabled) { n++; } });
        if (selN) { selN.textContent = String(n); }
    }

    function filtrar() {
        var q = (buscar && buscar.value ? buscar.value : '').trim().toLowerCase();
        picker.querySelectorAll('.of-item').forEach(function (item) {
            var txt = item.getAttribute('data-nombre') || '';
            item.classList.toggle('oculto', q !== '' && txt.indexOf(q) === -1);
        });
    }

    // ── Abrir en modo «nueva» o «editar» ───────────────────────────────
    function reset() {
        form.reset();
        document.getElementById('of-id').value = '';
        casillas().forEach(function (c) { c.checked = false; });
        if (buscar) { buscar.value = ''; }
        filtrar();
        pintarTipo();
        pintarAlcance();
        contar();
    }

    function nueva() {
        reset();
        titulo.textContent = 'Nueva oferta';
        abrir();
        document.getElementById('of-nombre').focus();
    }

    function editar(btn) {
        reset();
        titulo.textContent = 'Editar oferta';
        document.getElementById('of-id').value = btn.getAttribute('data-id') || '';
        document.getElementById('of-nombre').value = btn.getAttribute('data-nombre') || '';
        document.getElementById('of-valor').value = btn.getAttribute('data-valor') || '';
        document.getElementById('of-inicio').value = btn.getAttribute('data-inicio') || '';
        document.getElementById('of-fin').value = btn.getAttribute('data-fin') || '';
        document.getElementById('of-activa').checked = btn.getAttribute('data-activa') === '1';

        var elTipo = form.querySelector('input[name="tipo"][value="' + (btn.getAttribute('data-tipo') || 'porcentaje') + '"]');
        if (elTipo) { elTipo.checked = true; }
        var elAlc = form.querySelector('input[name="alcance"][value="' + (btn.getAttribute('data-alcance') || 'productos') + '"]');
        if (elAlc) { elAlc.checked = true; }

        var cat = btn.getAttribute('data-categoria') || '';
        var sel = document.getElementById('of-categoria');
        if (sel && cat) { sel.value = cat; }

        var ids = [];
        try { ids = JSON.parse(btn.getAttribute('data-productos') || '[]'); } catch (e) { ids = []; }
        var set = {};
        ids.forEach(function (id) { set[String(id)] = true; });
        casillas().forEach(function (c) { c.checked = !!set[c.value]; });

        pintarTipo();
        pintarAlcance();
        contar();
        abrir();
    }

    // ── Enlaces de eventos ─────────────────────────────────────────────
    document.querySelectorAll('[data-nueva-oferta]').forEach(function (b) {
        b.addEventListener('click', nueva);
    });
    document.querySelectorAll('[data-editar-oferta]').forEach(function (b) {
        b.addEventListener('click', function () { editar(b); });
    });
    document.querySelectorAll('[data-cerrar-oferta]').forEach(function (b) {
        b.addEventListener('click', cerrar);
    });

    form.querySelectorAll('input[name="tipo"]').forEach(function (r) { r.addEventListener('change', pintarTipo); });
    form.querySelectorAll('input[name="alcance"]').forEach(function (r) { r.addEventListener('change', pintarAlcance); });
    if (picker) { picker.addEventListener('change', contar); }
    if (buscar) { buscar.addEventListener('input', filtrar); }

    modal.addEventListener('click', function (e) { if (e.target === modal) { cerrar(); } });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'block') { cerrar(); }
    });

    // Estado inicial coherente aunque no se abra la ventana.
    pintarTipo();
    pintarAlcance();
})();
