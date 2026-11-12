<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar dirección</title>
    <?= view('partials/mapa_config') ?>
    <script src="<?= base_url('js/geo_direccion.js') ?>" defer></script>
</head>
<body>
<?php include 'Header.php'; ?>
    <div class="formulario-pagina">

        <header class="modal-header">
            <h2>Registrar dirección</h2>
        </header>

        <form action="<?= base_url('guarda_direccion') ?>" method="post" class="modal-form">
            <?= csrf_field() ?>
            <?= view('partials/aviso_formulario') ?>

            <?= view('partials/form_direccion', [
                'clientes' => $clientes,
                'conGeo'   => rutas_listas(),
                'sufijo'   => '_crea',
            ]) ?>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
                <a href="<?= base_url('lista_direccion') ?>" class="btn-cancelar btn-enlace">Cancelar</a>
            </footer>
        </form>
    </div>
</body>
</html>
