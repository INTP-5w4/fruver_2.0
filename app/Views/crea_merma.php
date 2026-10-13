<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea merma</title>
</head>
<body>
    <form action="<?= base_url('guarda_merma')?>" method="post">
<?= csrf_field() ?>
        <label for="cantidad">Cantidad:</label><br>
        <input type="number" name="cant" id="cantidad" required><br>
        
        <label for="fecha">Fecha:</label><br>
        <input type="date" name="fecha" id="fecha" value="<?= esc($date) ?>" required><br>
        
        <label for="Notas">Notas:</label><br>
        <textarea name="notas" id="Notas"></textarea><br>
        
        <label for="">Entrada:</label><br>
        <select name="id_entrada" id="id_entrada">
        <option value="">-- Selecciona una entrada --</option>
        <?php foreach ($entradas as $entrada): ?>
            <option value="<?= esc($entrada['id']) ?>">
                #<?= esc($entrada['id']) ?> — <?= esc($entrada['nombre_producto']) ?> (<?= esc($entrada['fecha']) ?>)
            </option>
        <?php endforeach; ?>
        </select><br>

        
        <label for="">Unidad</label><br>
        <input type="text" id="u_venta" name="u_venta" readonly placeholder="Unidad de venta"><br>

        <input type="submit" value="Enviar">
    </form>

<!--============================================================-->
<!-- Datos para JS -->
<script id="entradas-data" type="application/json">
    <?= json_seguro($entradas) ?>
</script>
<!--============================================================-->
    
<script src="<?= base_url('js/merma.js') ?>"></script>
</body>
</html>