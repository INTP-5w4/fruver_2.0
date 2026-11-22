<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica cliente</title>
</head>
<body>
<?php include 'Header.php'; ?>
    <div class="modal-contenido" style="max-width:500px; margin:40px auto;">

        <header class="modal-header">
            <h2>Modificar Cliente</h2>
        </header>

        <form action="<?= base_url('guarda_cliente') ?>" method="post" class="modal-form">
<?= csrf_field() ?>

            <input type="hidden" name="id" value="<?= esc($clientes['id']) ?>">

            <label for="editar-cliente-nom"><b>Nombre</b></label>
            <input id="editar-cliente-nom" type="text" name="nom" class="modal-input" value="<?= esc($clientes['nombre']) ?>" required>

            <label for="editar-cliente-ape-pat"><b>Apellido Paterno</b></label>
            <input id="editar-cliente-ape-pat" type="text" name="ape_pat" class="modal-input" value="<?= esc($clientes['ape_pat']) ?>" required>

            <label for="editar-cliente-ape-mat"><b>Apellido Materno</b></label>
            <input id="editar-cliente-ape-mat" type="text" name="ape_mat" class="modal-input" value="<?= esc($clientes['ape_mat']) ?>" required>

            <label for="editar-cliente-tel"><b>Teléfono</b></label>
            <input id="editar-cliente-tel" type="text" name="tel" class="modal-input" value="<?= esc($clientes['telefono']) ?>" required>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
            </footer>

        </form>
    </div>
</body>