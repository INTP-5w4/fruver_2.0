<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <?= view('partials/mapa_config', ['almacenMapa' => $almacenMapa]) ?>
    <script src="<?= base_url('js/ruta_mapa.js') ?>" defer></script>
    <title>Mi ruta</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php
$pendientes = array_filter($paradas, static fn ($p) => in_array($p['estado'], ['asignada', 'en_ruta'], true));
$porSalir   = array_filter($paradas, static fn ($p) => $p['estado'] === 'asignada');
$entregadas = array_filter($paradas, static fn ($p) => $p['estado'] === 'entregada');
$activa     = in_array($ruta['estado'], ['asignada', 'en_curso'], true);
$volverRuta = (int) $ruta['id'];
?>
<div class="mi-ruta" style="padding-top:70px;">
    <?php foreach (['error' => 'w3-red', 'mensaje' => 'w3-green', 'aviso' => 'w3-pale-yellow w3-border'] as $clave => $clase): ?>
        <?php if (session()->getFlashdata($clave)): ?>
            <div class="w3-panel <?= $clase ?> w3-animate-opacity"><p><?= esc(session()->getFlashdata($clave)) ?></p></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="resumen">
        <div>
            <h2 style="margin:0;">Mi ruta · <?= esc(date('d/m', strtotime($ruta['fecha']))) ?></h2>
            <small>
                <?= count($entregadas) ?> de <?= count($paradas) ?> entregadas ·
                <?= esc(rutas_km($ruta['distancia_m'])) ?> · <?= esc(rutas_duracion($ruta['duracion_s'])) ?> manejando
                <?= $ruta['proveedor'] === 'local' ? '· ruta aproximada' : '' ?>
            </small>
        </div>
        <a href="<?= base_url('repartidor/entregas') ?>" class="w3-button w3-border w3-small"><i class="fa-solid fa-list"></i> Mis entregas</a>
    </div>

    <?php if ($activa): ?>
        <div class="acciones" style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
            <?php if ($porSalir !== []): ?>
                <form method="post" action="<?= base_url('repartidor/ruta/' . (int) $ruta['id'] . '/iniciar') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="w3-button w3-blue"><i class="fa-solid fa-truck-fast"></i> Salir a ruta (<?= count($porSalir) ?>)</button>
                </form>
            <?php endif; ?>
            <?php if (count($pendientes) >= 2): ?>
                <form method="post" action="<?= base_url('repartidor/ruta/' . (int) $ruta['id'] . '/reoptimizar') ?>" data-gps="requerido"
                      onsubmit="return this.__gpsListo || confirm('¿Reordenar las paradas pendientes desde donde estás ahora?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="gps_lat"><input type="hidden" name="gps_lng">
                    <button type="submit" class="w3-button w3-border"><i class="fa-solid fa-location-crosshairs"></i> Reoptimizar desde aquí</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mapa" id="mapaRuta"></div>
    <p class="geo-estado" data-mapa-estado hidden></p>

    <?php foreach ($paradas as $i => $p): ?>
        <article class="parada estado-<?= esc($p['estado'], 'attr') ?> <?= (int) $p['id'] === $siguiente ? 'siguiente' : '' ?>" id="parada-<?= (int) $p['id'] ?>">
            <div class="cab">
                <strong><?= $i + 1 ?>. <?= esc($p['cliente']) ?></strong>
                <span class="w3-tag w3-round <?= match ($p['estado']) {
                    'asignada'  => 'w3-blue',
                    'en_ruta'   => 'w3-orange',
                    'entregada' => 'w3-green',
                    'fallida'   => 'w3-red',
                    default     => 'w3-grey',
                } ?>"><?= esc(str_replace('_', ' ', $p['estado'])) ?></span>
            </div>

            <p class="dir">
                <i class="fa-solid fa-location-dot"></i> <?= esc(\App\Models\Modelo_ruta::textoDireccion($p) ?: 'Sin dirección registrada') ?>
                <?php if ($p['referencias']): ?><br><span class="ref"><i class="fa-solid fa-circle-info"></i> <?= esc($p['referencias']) ?></span><?php endif; ?>
            </p>
            <p style="margin:4px 0;">
                <i class="fa-regular fa-clock"></i> Llega ≈ <b><?= esc(rutas_hora($p['eta'])) ?></b>
                <?php if ($p['ventana_fin']): ?> · <span class="w3-text-red">antes de <?= esc(substr($p['ventana_fin'], 0, 5)) ?></span><?php endif; ?>
                · Cobrar <b>$<?= esc(number_format((float) $p['total_pedido'], 2)) ?></b>
                · Pedido #<?= (int) $p['id_pedido'] ?>
            </p>

            <div class="acciones">
                <?php if ($p['navegar'] && in_array($p['estado'], ['asignada', 'en_ruta'], true)): ?>
                    <a class="w3-button btn-navegar" href="<?= esc($p['navegar'], 'attr') ?>" target="_blank" rel="noopener">
                        <i class="fa-solid fa-diamond-turn-right"></i> Navegar
                    </a>
                <?php endif; ?>
                <?php if ($p['telefono_cliente']): ?>
                    <a class="w3-button w3-border" href="tel:<?= esc($p['telefono_cliente'], 'attr') ?>"><i class="fa-solid fa-phone"></i> Llamar</a>
                <?php endif; ?>
            </div>

            <?php if ($p['estado'] === 'en_ruta'): ?>
                <details <?= (int) $p['id'] === $siguiente ? 'open' : '' ?>>
                    <summary><i class="fa-solid fa-circle-check"></i> Registrar entrega</summary>
                    <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $p['id'] . '/entregar') ?>" enctype="multipart/form-data" data-gps class="w3-margin-top">
                        <?= csrf_field() ?>
                        <input type="hidden" name="volver_ruta" value="<?= $volverRuta ?>">
                        <input type="hidden" name="gps_lat"><input type="hidden" name="gps_lng">
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <input type="number" name="monto" min="0" step="0.01" placeholder="Cobrado (<?= esc(number_format((float) $p['total_pedido'], 2), 'attr') ?>)" class="w3-input w3-border" style="max-width:180px;">
                            <select name="metodo" class="w3-select w3-border" style="max-width:170px;">
                                <option value="">Método de cobro</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option>
                            </select>
                        </div>
                        <label class="w3-small w3-margin-top" style="display:block;">Foto de evidencia (opcional)
                            <input type="file" name="evidencia" accept="image/jpeg,image/png,image/webp" capture="environment" class="w3-input">
                        </label>
                        <input type="text" name="notas" maxlength="500" placeholder="Notas (opcional)" class="w3-input w3-border w3-margin-top">
                        <button type="submit" class="w3-button w3-green w3-margin-top"><i class="fa-solid fa-circle-check"></i> Entregado</button>
                    </form>

                    <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $p['id'] . '/fallar') ?>" class="w3-margin-top">
                        <?= csrf_field() ?>
                        <input type="hidden" name="volver_ruta" value="<?= $volverRuta ?>">
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <input type="text" name="incidencia" maxlength="500" required list="motivos-falla"
                                   placeholder="¿Qué pasó?" class="w3-input w3-border" style="flex:1; min-width:200px;">
                            <button type="submit" class="w3-button w3-red"><i class="fa-solid fa-circle-xmark"></i> No se pudo</button>
                        </div>
                    </form>
                </details>
            <?php elseif ($p['estado'] === 'entregada'): ?>
                <p class="w3-small">Entregado a las <?= esc(rutas_hora($p['fecha_entrega'])) ?>
                    <?php if ($p['monto_cobrado'] !== null): ?> · cobrado $<?= esc(number_format((float) $p['monto_cobrado'], 2)) ?> (<?= esc($p['metodo_cobro']) ?>)<?php endif; ?>
                </p>
            <?php elseif ($p['estado'] === 'fallida'): ?>
                <p class="w3-small w3-text-red"><?= esc($p['incidencia']) ?></p>
            <?php elseif ($p['estado'] === 'asignada'): ?>
                <p class="w3-small w3-text-grey">Pulsa «Salir a ruta» para empezar.</p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?php if ($regreso && $pendientes === [] && $paradas !== []): ?>
        <a class="w3-button btn-navegar w3-block" href="<?= esc($regreso, 'attr') ?>" target="_blank" rel="noopener">
            <i class="fa-solid fa-warehouse"></i> Regresar al almacén
        </a>
    <?php endif; ?>

    <datalist id="motivos-falla">
        <option value="Cliente ausente">
        <option value="Dirección incorrecta o no encontrada">
        <option value="Cliente rechazó el pedido">
        <option value="No pagó">
        <option value="Acceso cerrado">
    </datalist>

    <script type="application/json" id="ruta-datos"><?= json_encode([
        'almacen'   => ['lat' => $ruta['almacen_lat'], 'lng' => $ruta['almacen_lng']],
        'regresa'   => (bool) $ruta['regresa_almacen'],
        'polyline'  => $ruta['polyline'],
        'proveedor' => $ruta['proveedor'],
        'paradas'   => array_map(static fn ($p, $i) => [
            'id' => (int) $p['id'], 'n' => $i + 1, 'lat' => $p['lat'], 'lng' => $p['lng'],
            'titulo' => $p['cliente'], 'estado' => $p['estado'],
        ], $paradas, array_keys($paradas)),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</div>
</body>
</html>
