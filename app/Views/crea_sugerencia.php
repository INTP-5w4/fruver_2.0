<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/sugerencias.css') ?>">
    <title>Buzón de sugerencias</title>
</head>
<body>
    <?php include 'Header.php'; ?>

    <?php
        $errores = session()->getFlashdata('errores') ?? [];
        $viejo   = session()->getFlashdata('viejo') ?? []; // valores ya saneados por el controlador
    ?>

    <div class="flash-container">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="w3-panel w3-red w3-animate-opacity">
                <p><?= esc(session()->getFlashdata('error')) ?></p>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('mensaje')): ?>
            <div class="w3-panel w3-green w3-animate-opacity">
                <p><?= esc(session()->getFlashdata('mensaje')) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <main class="sugerencia-wrapper">
        <section class="sugerencia-card">
            <h1>Buzón de sugerencias</h1>
            <p class="sugerencia-intro">
                ¿Tienes una idea para mejorar FRUVER? Cuéntanosla. Tu usuario y tu rol se registran
                automáticamente junto con tu sugerencia.
            </p>

            <form action="<?= base_url('guarda_sugerencia') ?>" method="post" autocomplete="off">
                <?= csrf_field() ?>

                <label for="titulo"><b>Título*</b></label>
                <input type="text" id="titulo" name="titulo"
                       class="w3-input w3-border"
                       maxlength="100" minlength="3" required
                       placeholder="Ej: Agregar filtro por fecha en Mermas"
                       value="<?= esc($viejo['titulo'] ?? '') ?>">
                <?php if (isset($errores['titulo'])): ?>
                    <p class="campo-error"><?= esc($errores['titulo']) ?></p>
                <?php endif; ?>

                <label for="descripcion"><b>Descripción*</b></label>
                <textarea id="descripcion" name="descripcion"
                          class="w3-input w3-border"
                          rows="6" maxlength="2000" minlength="10" required
                          placeholder="Explica con detalle qué mejorarías y por qué."><?= esc($viejo['descripcion'] ?? '') ?></textarea>
                <small class="contador"><span id="contador-desc">0</span> / 2000</small>
                <?php if (isset($errores['descripcion'])): ?>
                    <p class="campo-error"><?= esc($errores['descripcion']) ?></p>
                <?php endif; ?>

                <label for="valoracion"><b>Valoración*</b></label>
                <select id="valoracion" name="valoracion" class="w3-select w3-border" required>
                    <option value="">-- Selecciona qué tan importante es --</option>
                    <?php foreach ($valoraciones as $valoracion): ?>
                        <option value="<?= esc($valoracion) ?>"
                            <?= ($viejo['valoracion'] ?? '') === $valoracion ? 'selected' : '' ?>>
                            <?= esc($valoracion) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errores['valoracion'])): ?>
                    <p class="campo-error"><?= esc($errores['valoracion']) ?></p>
                <?php endif; ?>

                <div class="sugerencia-acciones">
                    <a href="<?= base_url('/') ?>" class="w3-button w3-light-grey">Volver</a>
                    <button type="submit" class="w3-button w3-green">Enviar sugerencia</button>
                </div>
            </form>
        </section>
    </main>

    <?php include 'Footer.php'; ?>

    <script>
        // Contador de caracteres de la descripción (solo mejora de usabilidad).
        (function () {
            const area = document.getElementById('descripcion');
            const cuenta = document.getElementById('contador-desc');
            if (!area || !cuenta) return;
            const actualiza = () => { cuenta.textContent = area.value.length; };
            area.addEventListener('input', actualiza);
            actualiza();
        })();
    </script>
</body>
</html>
