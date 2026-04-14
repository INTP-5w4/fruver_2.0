<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica cliente</title>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
</head>
<body>
    <div class="modal-contenido" style="max-width:500px; margin:40px auto;">

        <header class="modal-header">
            <h2>Modificar Cliente</h2>
        </header>

        <form action="<?= base_url('guarda_cliente') ?>" method="post" class="modal-form">

            <input type="hidden" name="id" value="<?= $clientes['id'] ?>">

            <label><b>Nombre</b></label>
            <input type="text" name="nom" class="modal-input" value="<?= $clientes['nombre'] ?>" required>

            <label><b>Apellido Paterno</b></label>
            <input type="text" name="ape_pat" class="modal-input" value="<?= $clientes['ape_pat'] ?>" required>

            <label><b>Apellido Materno</b></label>
            <input type="text" name="ape_mat" class="modal-input" value="<?= $clientes['ape_mat'] ?>" required>

            <label><b>Teléfono</b></label>
            <input type="text" name="tel" class="modal-input" value="<?= $clientes['telefono'] ?>" required>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
            </footer>

        </form>
    </div>
</body>