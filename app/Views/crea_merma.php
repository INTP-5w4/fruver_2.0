<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea merma</title>
</head>
<body>
    <form action="<?= base_url('guarda_merma')?>" method="post">
        <label for="cantidad">Cantidad:</label>
        <input type="number" name="cant" id="cantidad" required><br>
        
        <label for="fecha">Fecha:</label>
        <input type="date" name="fecha" id="fecha" value="<?= $date ?>" required><br>
        
        <label for="Notas">Notas:</label>
        <textarea name="notas" id="Notas"></textarea><br>
        
        <label for="id_entrada">ID Entrada:</label>
        <select name="id_entrada" id="id_entrada" required>
            <?php foreach ($entradas as $e): ?>
                <option value="<?= $e['id'] ?>"><?= $e['id'] ?></option>
            <?php endforeach; ?>
        </select><br>
        <input type="submit" value="Enviar">
    </form>
    
</body>
</html>