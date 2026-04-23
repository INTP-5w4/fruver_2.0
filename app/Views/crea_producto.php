<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Productos</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
</head>
<body class="w3-light-grey">

<!-- Mensajes de error y éxito -->
<?php if (session()->getFlashdata('error')): ?>
    <div class="w3-panel w3-red w3-animate-opacity">
        <p><?= session()->getFlashdata('error') ?></p>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('mensaje')): ?>
    <div class="w3-panel w3-green w3-animate-opacity">
        <p><?= session()->getFlashdata('mensaje') ?></p>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('errors')): ?>
    <div class="w3-panel w3-red w3-animate-opacity">
        <?php foreach (session()->getFlashdata('errors') as $e): ?>
            <p><?= $e ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="w3-container w3-padding-32">
    <button onclick="document.getElementById('modalProducto').style.display='block'"
            class="w3-button w3-green">
        + Nuevo Producto
    </button>
</div>

<div id="modalProducto" class="w3-modal">
    <div class="w3-modal-content">

        <header class="w3-container w3-green">
            <span onclick="document.getElementById('modalProducto').style.display='none'"
                  class="w3-button w3-display-topright">&times;</span>
            <h2>Registrar Producto</h2>
        </header>

        <!-- Error dentro del modal -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="w3-panel w3-red w3-margin">
                <p><?= session()->getFlashdata('error') ?></p>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('errors')): ?>
            <div class="w3-panel w3-red w3-margin">
                <?php foreach (session()->getFlashdata('errors') as $e): ?>
                    <p><?= $e ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('guarda_producto') ?>" method="post"
              enctype="multipart/form-data" class="w3-container w3-padding-16">

            <label><b>Nombre</b></label>
            <input type="text" name="nom" class="w3-input w3-border w3-margin-bottom"
                   placeholder="Ej: Tomate Saladet" required>

            <label><b>Descripción</b></label>
            <textarea name="desc" class="w3-input w3-border w3-margin-bottom"
                      rows="4" required></textarea>

            <label><b>Categoría</b></label>
            <select name="cat" class="w3-select w3-border w3-margin-bottom">
                <option value="frutas">Frutas</option>
                <option value="verduras">Verduras</option>
                <option value="yerbas">Yerbas</option>
            </select>

            <label><b>Imagen</b></label>
            <input type="file" name="img" class="w3-input w3-border w3-margin-bottom">

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalProducto').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>

<script>
    window.onclick = function(event) {
        const modal = document.getElementById('modalProducto');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };

    // Si hay error, abre el modal automáticamente
    <?php if (session()->getFlashdata('error') || session()->getFlashdata('errors')): ?>
        document.getElementById('modalProducto').style.display = 'block';
    <?php endif; ?>
</script>

</body>
</html>