<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar entrada</title>
</head>
<body>
<?php include 'Header.php'; ?>
    <div class="formulario-pagina">

        <header class="modal-header">
            <h2>Modificar entrada</h2>
        </header>

        <form action="<?= base_url('modifica_entrada') ?>" method="post" class="modal-form">
            <?= csrf_field() ?>
            <?= view('partials/aviso_formulario') ?>
            <input type="hidden" name="id" value="<?= (int) $entradas['id'] ?>">

            <?= view('partials/form_entrada', ['ent' => $entradas, 'productos' => $productos]) ?>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
                <a href="<?= base_url('lista_entrada') ?>" class="btn-cancelar btn-enlace">Cancelar</a>
            </footer>
        </form>
    </div>
</body>
</html>
