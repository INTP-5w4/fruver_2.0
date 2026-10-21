// Buzón de sugerencias: contador de caracteres de la descripción (solo mejora de usabilidad).
(function () {
    'use strict';

    var area = document.getElementById('descripcion');
    var cuenta = document.getElementById('contador-desc');
    if (!area || !cuenta) { return; }

    function actualiza() { cuenta.textContent = area.value.length; }

    area.addEventListener('input', actualiza);
    actualiza();
})();
