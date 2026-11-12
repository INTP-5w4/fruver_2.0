<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar repartidor</title>
</head>
<body>
<?php include 'Header.php'; ?>
    <div class="formulario-pagina">

        <header class="modal-header">
            <h2>Registrar repartidor</h2>
        </header>

        <form action="<?= base_url('guarda_repartidor') ?>" method="post" class="modal-form">
            <?= csrf_field() ?>
            <?= view('partials/aviso_formulario') ?>

            <?= view('partials/form_repartidor', ['sufijo' => '_crea']) ?>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
                <a href="<?= base_url('lista_repartidor') ?>" class="btn-cancelar btn-enlace">Cancelar</a>
            </footer>
        </form>
    </div>
</body>
</html>
