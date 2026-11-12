<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar dirección</title>
    <?= view('partials/mapa_config') ?>
    <script src="<?= base_url('js/geo_direccion.js') ?>" defer></script>
</head>
<body>
<?php include 'Header.php'; ?>
    <div class="formulario-pagina">

        <header class="modal-header">
            <h2>Modificar dirección</h2>
        </header>

        <form action="<?= base_url('modifica_direccion') ?>" method="post" class="modal-form">
            <?= csrf_field() ?>
            <?= view('partials/aviso_formulario') ?>

            <input type="hidden" name="id" value="<?= (int) $direcciones['id'] ?>">

            <?= view('partials/form_direccion', [
                'dir'      => $direcciones,
                'clientes' => $clientes,
                'conGeo'   => array_key_exists('lat', $direcciones),
                'sufijo'   => '_modifica',
            ]) ?>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
                <a href="<?= base_url('lista_direccion') ?>" class="btn-cancelar btn-enlace">Cancelar</a>
            </footer>
        </form>
    </div>
</body>
</html>
