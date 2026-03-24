<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica cliente</title>
</head>
<body>
    <form action="<?= base_url('guarda_cliente')?>" method="post">
        <input type="hidden" name="id" value="<?= $clientes['id'] ?> ">
        <label for="nom">Nombre</label><br>
        <input type="text" name="nom" id="" value="<?= $clientes['nombre'] ?> "><br>

        <label for="ape_pat">Apellido Paterno</label><br>
        <input type="text" name="ape_pat" id="" value="<?= $clientes['ape_pat'] ?> "><br>

        <label for="ape_mat">Apellido materno</label><br>
        <input type="text" name="ape_mat" id="" value="<?= $clientes['ape_mat'] ?> "><br>

        <label for="tel">Telefono</label><br>
        <input type="text" name="tel" id="" value="<?= $clientes['telefono'] ?> "><br>

        <input type="submit" value="Enviar">
    </form>
    
</body>
</html>