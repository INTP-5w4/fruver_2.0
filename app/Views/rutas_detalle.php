<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= view('partials/mapa_config', ['almacenMapa' => $almacenMapa]) ?>
    <script src="<?= base_url('js/ruta_mapa.js') ?>" defer></script>
    <title>Ruta #<?= (int) $ruta['id'] ?></title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="lista-wrapper">
    <?php foreach (['error' => 'w3-red', 'mensaje' => 'w3-green', 'aviso' => 'w3-pale-yellow w3-border'] as $clave => $clase): ?>
        <?php if (session()->getFlashdata($clave)): ?>
            <div class="w3-panel <?= $clase ?>"><p><?= esc(session()->getFlashdata($clave)) ?></p></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <p><a href="<?= base_url('rutas?fecha=' . rawurlencode($ruta['fecha'])) ?>">&larr; Rutas del <?= esc(date('d/m/Y', strtotime($ruta['fecha']))) ?></a></p>

    <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; align-items:center;">
        <h2 style="margin:0;">Ruta #<?= (int) $ruta['id'] ?> · <?= esc($ruta['repartidor']) ?></h2>
        <?php if ($ruta['estado'] === 'asignada'): ?>
            <form method="post" action="<?= base_url('rutas/' . (int) $ruta['id'] . '/cancelar') ?>" onsubmit="return confirm('¿Cancelar esta ruta? Los pedidos quedan asignados al repartidor sin ruta.');">
                <?= csrf_field() ?>
                <button type="submit" class="w3-button w3-border w3-text-red"><i class="fa-solid fa-ban"></i> Cancelar ruta</button>
            </form>
        <?php endif; ?>
    </div>

    <p>
        <b>Estado:</b> <?= esc(str_replace('_', ' ', $ruta['estado'])) ?> ·
        <b>Sale de:</b> <?= esc($ruta['almacen']) ?> ·
        <b>Distancia:</b> <?= esc(rutas_km($ruta['distancia_m'])) ?> ·
        <b>Tiempo manejando:</b> <?= esc(rutas_duracion($ruta['duracion_s'])) ?> ·
        <b>Tipo:</b> <?= esc(str_replace('_', ' ', $ruta['estrategia'])) ?><?= $ruta['proveedor'] === 'local' ? ' (aproximada, sin servicio de mapas)' : '' ?>
        <?= $ruta['regresa_almacen'] ? '· regresa al almacén' : '' ?>
    </p>

    <div class="rutas-layout">
        <section class="rutas-panel">
            <h3>Paradas</h3>
            <ol class="rutas-lista" style="max-height:none;">
                <?php foreach ($paradas as $i => $p): ?>
                    <li id="parada-<?= (int) $p['id'] ?>">
                        <span class="num"><?= $i + 1 ?></span>
                        <div class="detalle">
                            <b>#<?= (int) $p['id_pedido'] ?> · <?= esc($p['cliente']) ?></b>
                            <span class="geo-tag <?= $p['estado'] === 'entregada' ? 'geo-tag--ok' : ($p['estado'] === 'fallida' ? 'geo-tag--no' : 'geo-tag--aprox') ?>"><?= esc(str_replace('_', ' ', $p['estado'])) ?></span>
                            <br><?= esc(\App\Models\Modelo_ruta::textoDireccion($p)) ?>
                            <?php if ($p['referencias']): ?><br><small><?= esc($p['referencias']) ?></small><?php endif; ?>
                            <br><small>
                                Llega ≈ <?= esc(rutas_hora($p['eta'])) ?>
                                <?php if ($p['ventana_fin']): ?> (antes de <?= esc(substr($p['ventana_fin'], 0, 5)) ?>)<?php endif; ?>
                                <?php if ($p['fecha_entrega'] && $p['estado'] === 'entregada'): ?> · entregado <?= esc(rutas_hora($p['fecha_entrega'])) ?><?php endif; ?>
                                · $<?= esc(number_format((float) $p['total_pedido'], 2)) ?>
                            </small>
                            <?php if ($p['incidencia']): ?><br><small class="w3-text-red"><?= esc($p['incidencia']) ?></small><?php endif; ?>
                            <div class="acciones">
                                <?php if ($p['aviso_wa']): ?>
                                    <a class="w3-button w3-small w3-border" href="<?= esc($p['aviso_wa'], 'attr') ?>" target="_blank" rel="noopener">
                                        <i class="fa-brands fa-whatsapp"></i> Avisar hora al cliente
                                    </a>
                                <?php endif; ?>
                                <?php if ($p['evidencia']): ?>
                                    <a class="w3-button w3-small w3-border" href="<?= base_url('evidencia_entrega/' . (int) $p['id']) ?>" target="_blank" rel="noopener">
                                        <i class="fa-solid fa-camera"></i> Evidencia
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <section>
            <div class="rutas-mapa" id="mapaRuta"></div>
            <p class="geo-estado" data-mapa-estado hidden></p>
        </section>
    </div>

    <script type="application/json" id="ruta-datos"><?= json_encode([
        'almacen'   => ['lat' => $ruta['almacen_lat'], 'lng' => $ruta['almacen_lng']],
        'regresa'   => (bool) $ruta['regresa_almacen'],
        'polyline'  => $ruta['polyline'],
        'proveedor' => $ruta['proveedor'],
        'paradas'   => array_map(static fn ($p, $i) => [
            'id' => (int) $p['id'], 'n' => $i + 1, 'lat' => $p['lat'], 'lng' => $p['lng'],
            'titulo' => '#' . $p['id_pedido'] . ' ' . $p['cliente'], 'estado' => $p['estado'],
        ], $paradas, array_keys($paradas)),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

    <?php include 'Footer.php'; ?>
</div>
</body>
</html>
