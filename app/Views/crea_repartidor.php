<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Title</title>
</head>
<body>
    <form action="<?= base_url('guarda_repartidor')?>" method="post">
        <label for="nom">Nombre</label><br>
        <input type="text" name="nom" id="" required><br>

        <label for="ape_pat">Apellido Paterno</label><br>
        <input type="text" name="ape_pat" id="" required><br>

        <label for="ape_mat">Apellido materno</label><br>
        <input type="text" name="ape_mat" id="" required><br>

        <label for="tel">Telefono</label><br>
        <input type="text" name="tel" id="" required><br>

        <label for="dir">Dirección</label><br>
        <input type="text" name="dir" id="" required><br>

        <label for="not">Notas</label><br>
        <textarea name="not" id="" required></textarea><br>

        <input type="submit" value="Enviar">
    </form>
</body>
</html>