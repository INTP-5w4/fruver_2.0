<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta de productos</title>
</head>
<body>
    <form action="<?= base_url('guarda_producto') ?>" method="post">
        <label for="nombre">Nombre</label><br>
        <input type="text" name="nom" id="" placeholder="Ej: Tomate Saladet" required><br>
        <label for="descripcion">Descripcion</label><br>
        <textarea name="desc" id="" cols="20" rows="5" required></textarea><br>
        <input type="submit" value="Enviar">
    </form>
</body>
</html>