<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>FRUVER — <?= $this->renderSection('title') ?></title>

    <!-- Todo el estilo es local: el CSP de la app solo permite hojas de estilo propias, w3schools, cdnjs y Google Fonts -->
    <link rel="stylesheet" href="<?= base_url('estilos/auth.css') ?>">

    <?= $this->renderSection('pageStyles') ?>
</head>

<body class="fv-auth">

    <header class="fv-auth-header">
        <a href="<?= base_url('main_page_4') ?>" class="fv-auth-logo">FRUVER</a>
        <a href="<?= base_url('main_page_4') ?>" class="fv-auth-volver">&larr; Volver a la tienda</a>
    </header>

    <main role="main" class="container fv-auth-main">
        <?= $this->renderSection('main') ?>
    </main>

    <footer class="fv-auth-footer">FRUVER 2.0 · Frutas, verduras y hierbas</footer>

<?= $this->renderSection('pageScripts') ?>
</body>
</html>
