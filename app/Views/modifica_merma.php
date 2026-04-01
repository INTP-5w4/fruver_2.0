<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica merma</title>
</head>
<body>
    <form action="<?= base_url('modifica_merma') ?>" method="post">
        <input type="hidden" name="id" value="<?= $mermas['id'] ?>">
        
        <label for="cantidad">Cantidad:</label><br>
        <input type="number" name="cantidad" id="cantidad" value="<?= $mermas['cantidad'] ?>" required><br>
        
        <label for="fecha">Fecha:</label>
        <input type="date" name="fecha" id="fecha" value="<?= $mermas['fecha'] ?>" required><br>
        
        <label for="notas">Notas:</label>
        <textarea name="notas" id="notas"><?= $mermas['notas'] ?></textarea><br>
        
        <label for="id_entrada">ID Entrada:</label>
        <select name="id_entrada" id="id_entrada" required>
            <?php foreach ($entradas as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $e['id'] == $mermas['id_entrada'] ? 'selected' : '' ?>><?= $e['id'] ?></option>
            <?php endforeach; ?>
        </select><br>
        
        <input type="submit" value="Actualizar">
    </form>
    
</body>
</html>