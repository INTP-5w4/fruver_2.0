<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar producto</title>
</head>
<body>
    <form action="<?= base_url('modifica_producto') ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= esc($productos['id']) ?>">
        <label for="nombre">Nombre</label><br>
        <input type="text" name="nom" value="<?= esc($productos['nombre']) ?>"><br>
        <label for="descripcion">Descripcion</label><br>
        <textarea name="desc" cols="20" rows="5"><?= esc($productos['descripcion']) ?></textarea>
        
        <label for="categoria">Categoria</label><br>
        <select name="cat" id="">
            <option value="<?=$productos['categoria'] ?>" selected><?= $productos['categoria'] ?></option>
            <option value="frutas">Frutas</option>
            <option value="verduras">Verdura</option>
            <option value="yerbas">Yerba</option>
        </select><br>
        
        
        <label for="imagen">Imagen</label><br>
        <input type="file" name="img" id="" value="<?= esc($productos['img']) ?>"><br>
        <input type="submit" value="Enviar">
    </form>
</body>
</html>