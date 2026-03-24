<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar producto</title>
</head>
<body>
    <form action="<?= base_url('modifica_producto') ?>" method="post">
        <input type="hidden" name="id" value="<?= $productos['id'] ?>">
        <label for="nombre">Nombre</label><br>
        <input type="text" name="nom" value="<?= esc($productos['nombre']) ?>"><br>
        <label for="descripcion">Descripcion</label><br>
        <textarea name="desc" cols="20" rows="5"><?= esc($productos['descripcion']) ?></textarea>
        <input type="submit" value="Enviar">
    </form>
</body>
</html>