// Ticket del pedido: botón "Copiar mensaje" (solo mejora de usabilidad; el envío no depende de él).
(function () {
    'use strict';

    var boton = document.getElementById('btn-copiar');
    var fuente = document.getElementById('ticket-mensaje');
    var aviso = document.getElementById('copiado-aviso');
    if (!boton || !fuente) { return; }

    function avisar(texto) {
        if (!aviso) { return; }
        aviso.textContent = texto;
        window.setTimeout(function () { aviso.textContent = ''; }, 2500);
    }

    function copiaAntigua() {
        fuente.focus();
        fuente.select();
        try {
            return document.execCommand('copy');
        } catch (e) {
            return false;
        }
    }

    boton.addEventListener('click', function () {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(fuente.value).then(
                function () { avisar('Mensaje copiado'); },
                function () { avisar(copiaAntigua() ? 'Mensaje copiado' : 'No se pudo copiar'); }
            );
            return;
        }
        avisar(copiaAntigua() ? 'Mensaje copiado' : 'No se pudo copiar');
    });
})();
