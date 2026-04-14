<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Repartidor</title>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
</head>
<body>
    <div class="modal-contenido" style="max-width:500px; margin:40px auto;">

        <header class="modal-header">
            <h2>Registrar Repartidor</h2>
        </header>

        <form action="<?= base_url('guarda_repartidor') ?>" method="post" class="modal-form">

            <label><b>Nombre</b></label>
            <input type="text" name="nom" class="modal-input" required>

            <label><b>Apellido Paterno</b></label>
            <input type="text" name="ape_pat" class="modal-input" required>

            <label><b>Apellido Materno</b></label>
            <input type="text" name="ape_mat" class="modal-input" required>

            <label><b>Teléfono</b></label>
            <input type="text" name="tel" class="modal-input" required>

            <label><b>Dirección</b></label>
            <input type="text" name="dir" class="modal-input" required>

            <label><b>Notas</b></label>
            <textarea name="not" class="modal-input" rows="4" required></textarea>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
            </footer>

        </form>
    </div>
</body>
</html>