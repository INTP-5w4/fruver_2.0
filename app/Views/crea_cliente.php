<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
</head>
<body>
    <form action="<?= base_url('guarda_cliente')?>" method="post">
        
<label for="nom">Nombre</label><br>
<input type="text" name="nom" id="" required><br>

<label for="ape_pat">Apellido Paterno</label><br>
<input type="text" name="ape_pat" id="" required><br>

<label for="ape_mat">Apellido materno</label><br>
<input type="text" name="ape_mat" id="" required><br>

<label for="tel">Telefono</label><br>
<input type="text" name="tel" id="" required><br>

<input type="submit" value="Enviar">
    </form>
</body>
</html>