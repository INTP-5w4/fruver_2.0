document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('id_entrada');
    const campoUnidad = document.getElementById('u_venta');

    // Datos de entradas pasados desde PHP
    const entradas = JSON.parse(document.getElementById('entradas-data').textContent);

    select.addEventListener('change', function () {
        const idSeleccionado = parseInt(this.value);
        const entrada = entradas.find(e => e.id == idSeleccionado);

        if (entrada) {
            campoUnidad.value = entrada.u_venta;
        } else {
            campoUnidad.value = '';
        }
    });
});