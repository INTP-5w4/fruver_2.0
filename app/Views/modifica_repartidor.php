<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Title</title>
</head>
<body>
    <form action="<?= base_url('modifica_repartidor')?>" method="post">
        <input type="hidden" name="id" value="<?= $repartidores['id'] ?>">

        <label for="nom">Nombre</label><br>
        <input type="text" name="nom" id="" value="<?= $repartidores['nombre']  ?>"><br>

        <label for="ape_pat">Apellido Paterno</label><br>
        <input type="text" name="ape_pat" id="" value="<?= $repartidores['ape_pat']  ?>"><br>

        <label for="ape_mat">Apellido materno</label><br>
        <input type="text" name="ape_mat" id="" value="<?= $repartidores['ape_mat']  ?>"><br>

        <label for="tel">Telefono</label><br>
        <input type="text" name="tel" id="" value="<?= $repartidores['telefono']  ?>"><br>

        <label for="dir">Dirección</label><br>
        <input type="text" name="dir" id="" value="<?= $repartidores['direccion']  ?>"><br>

        <label for="not">Notas</label><br>
        <textarea name="not" id=""><?= $repartidores['notas'] ?></textarea><br>

        <input type="submit" value="Enviar">
    </form>
</body>
</html>