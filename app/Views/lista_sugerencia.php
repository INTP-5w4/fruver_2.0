<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/sugerencias.css') ?>">
    <title>Sugerencias recibidas</title>
</head>
<body>
    <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>

<?php
    // Clase CSS según la valoración (mapa fijo: ningún texto del usuario se usa como clase).
    $claseBadge = [
        'Muy importante'    => 'sug-badge--muy',
        'Importante'        => 'sug-badge--imp',
        'No muy importante' => 'sug-badge--poco',
    ];
    $hayFiltros = ($buscar ?? '') !== '' || ($valoracion ?? '') !== '' || ($orden ?? 'recientes') !== 'recientes';
?>

<div class="lista-wrapper">

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

<div class="contenedor-boton sug-barra" style="padding-top: 20px;">
    <h2 class="sug-titulo">Sugerencias recibidas</h2>
    <a href="<?= base_url('crea_sugerencia') ?>" class="btn-agregar btn-enlace">
        <i class="fa-regular fa-lightbulb"></i> + Enviar sugerencia
    </a>
</div>

<!-- Resumen: cada etiqueta filtra la lista por esa valoración -->
<div class="sug-resumen">
    <a href="<?= base_url('lista_sugerencia') ?>"
       class="sug-pildora <?= ($valoracion ?? '') === '' ? 'sug-pildora--activa' : '' ?>">
        Todas <strong><?= (int) $conteos['total'] ?></strong>
    </a>
    <?php foreach ($valoraciones as $v): ?>
        <a href="<?= base_url('lista_sugerencia') . '?' . esc(http_build_query(['valoracion' => $v]), 'url') ?>"
           class="sug-pildora <?= $claseBadge[$v] ?? '' ?> <?= ($valoracion ?? '') === $v ? 'sug-pildora--activa' : '' ?>">
            <?= esc($v) ?> <strong><?= (int) $conteos[$v] ?></strong>
        </a>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= base_url('lista_sugerencia') ?>" class="w3-margin-bottom">
    <div class="sug-filtros">
        <input
            type="text"
            name="buscar"
            value="<?= esc($buscar ?? '') ?>"
            maxlength="100"
            placeholder="Buscar por título, descripción, usuario o rol..."
            class="w3-input w3-border"
            style="max-width:350px;"
        >

        <select name="valoracion" class="w3-select w3-border" style="max-width:200px;" aria-label="Filtrar por valoración">
            <option value="">Todas las valoraciones</option>
            <?php foreach ($valoraciones as $v): ?>
                <option value="<?= esc($v) ?>" <?= ($valoracion ?? '') === $v ? 'selected' : '' ?>><?= esc($v) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="orden" class="w3-select w3-border" style="max-width:200px;" aria-label="Ordenar por">
            <option value="recientes"   <?= ($orden ?? '') === 'recientes' ? 'selected' : '' ?>>Más recientes primero</option>
            <option value="importancia" <?= ($orden ?? '') === 'importancia' ? 'selected' : '' ?>>Más importantes primero</option>
        </select>

        <button type="submit" class="w3-button w3-green">
            <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
        <?php if ($hayFiltros): ?>
            <a href="<?= base_url('lista_sugerencia') ?>" class="w3-button w3-red">✕ Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="tabla-wrapper">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Valoración</th>
                <th>Título</th>
                <th>Descripción</th>
                <th>Usuario</th>
                <th>Rol</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sugerencias)): ?>
                <tr>
                    <td colspan="7" class="sug-vacio">
                        <?= $hayFiltros ? 'Ninguna sugerencia coincide con la búsqueda.' : 'Todavía no se ha recibido ninguna sugerencia.' ?>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($sugerencias as $s): ?>
                <?php $larga = mb_strlen($s['descripcion']) > 120; ?>
                <tr>
                    <td><?= esc($s['id']) ?></td>
                    <td><?= esc($s['fecha_creacion']) ?></td>
                    <td>
                        <span class="sug-badge <?= $claseBadge[$s['valoracion']] ?? 'sug-badge--poco' ?>">
                            <?= esc($s['valoracion']) ?>
                        </span>
                    </td>
                    <td class="sug-celda-titulo"><?= esc($s['titulo']) ?></td>
                    <td class="sug-celda-desc">
                        <?php if ($larga): ?>
                            <?= esc(mb_substr($s['descripcion'], 0, 120)) ?>…
                            <details>
                                <summary>Ver completa</summary>
                                <div class="sug-texto"><?= nl2br(esc($s['descripcion'])) ?></div>
                            </details>
                        <?php else: ?>
                            <?= nl2br(esc($s['descripcion'])) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($s['usuario']) ?></td>
                    <td><?= esc($s['rol']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $pager->links('default', 'w3_pager') ?>

</div>

<?php include 'Footer.php'; ?>

</body>
</html>
