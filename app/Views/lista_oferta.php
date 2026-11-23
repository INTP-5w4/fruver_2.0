<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= base_url('estilos/ofertas.css') ?>">
    <title>Ofertas · Fruver</title>
</head>
<body>
    <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>

<?php
// ── Ayudas de presentación (solo lectura, sin tocar la base) ──────────────────
$nombreCat = static fn (string $c): string => ucfirst($c);

$estadoOferta = static function (array $o) use ($hoy): array {
    if ((int) $o['activa'] !== 1) {
        return ['Inactiva', 'off'];
    }
    if (! empty($o['fecha_inicio']) && $o['fecha_inicio'] > $hoy) {
        return ['Programada', 'prog'];
    }
    if (! empty($o['fecha_fin']) && $o['fecha_fin'] < $hoy) {
        return ['Vencida', 'ven'];
    }

    return ['Vigente', 'ok'];
};

$descuentoTexto = static function (array $o): string {
    if ($o['tipo'] === 'porcentaje') {
        return '-' . rtrim(rtrim(number_format((float) $o['valor'], 2, '.', ''), '0'), '.') . '%';
    }

    return '$' . number_format((float) $o['valor'], 2) . ' fijo';
};

$vigenciaTexto = static function (array $o): string {
    $i = ! empty($o['fecha_inicio']) ? $o['fecha_inicio'] : null;
    $f = ! empty($o['fecha_fin']) ? $o['fecha_fin'] : null;
    if ($i === null && $f === null) {
        return 'Siempre';
    }
    if ($i !== null && $f !== null) {
        return $i . ' → ' . $f;
    }

    return $i !== null ? ('Desde ' . $i) : ('Hasta ' . $f);
};
?>

<div class="lista-wrapper">
<div class="flash-container">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="w3-panel w3-red w3-animate-opacity"><p><?= esc(session()->getFlashdata('error')) ?></p></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('mensaje')): ?>
        <div class="w3-panel w3-green w3-animate-opacity"><p><?= esc(session()->getFlashdata('mensaje')) ?></p></div>
    <?php endif; ?>
</div>

<div class="contenedor-boton">
    <h1>Ofertas</h1>
    <button type="button" class="btn-agregar" data-nueva-oferta>
        <i class="fa-solid fa-tags" aria-hidden="true"></i> Nueva oferta
    </button>
</div>

<p class="oferta-ayuda">
    Baja el precio de ciertos productos o de una categoría completa. El precio con descuento se ve
    en la tienda y se cobra en el pedido del cliente.
</p>

<form method="get" action="<?= base_url('ofertas') ?>" class="w3-margin-bottom">
    <div style="display:flex; gap:8px;">
        <input type="text" name="buscar" aria-label="Buscar oferta" value="<?= esc($buscar ?? '') ?>"
               placeholder="Buscar por nombre o categoría..." class="w3-input w3-border" style="max-width:350px;">
        <button type="submit" class="w3-button w3-green"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
        <?php if (! empty($buscar)): ?>
            <a href="<?= base_url('ofertas') ?>" class="w3-button w3-red"><i class="fa-solid fa-eraser" aria-hidden="true"></i> Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="tabla-wrapper">
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Descuento</th>
                <th>Aplica a</th>
                <th>Vigencia</th>
                <th>Estado</th>
                <th>Editar</th>
                <th>Activar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($ofertas === []): ?>
                <tr><td colspan="8" class="celda-completa">Aún no hay ofertas. Crea la primera con «Nueva oferta».</td></tr>
            <?php endif; ?>
            <?php foreach ($ofertas as $o): ?>
                <?php
                [$etiqueta, $clase] = $estadoOferta($o);
                $idsProd            = $productosPorOferta[(int) $o['id']] ?? [];
                $aplica             = $o['alcance'] === 'categoria'
                    ? ('Categoría: ' . $nombreCat((string) $o['categoria']))
                    : ((int) $o['productos_n'] . ' producto' . ((int) $o['productos_n'] === 1 ? '' : 's'));
                ?>
                <tr>
                    <td><?= esc($o['nombre']) ?></td>
                    <td><span class="oferta-descuento"><?= esc($descuentoTexto($o)) ?></span></td>
                    <td><?= esc($aplica) ?></td>
                    <td><?= esc($vigenciaTexto($o)) ?></td>
                    <td><span class="est-oferta est-oferta--<?= $clase ?>"><?= $etiqueta ?></span></td>
                    <td>
                        <button type="button" class="btn-icono" data-editar-oferta aria-label="Editar oferta" title="Editar"
                                data-id="<?= (int) $o['id'] ?>"
                                data-nombre="<?= esc($o['nombre'], 'attr') ?>"
                                data-tipo="<?= esc($o['tipo'], 'attr') ?>"
                                data-valor="<?= esc((string) (float) $o['valor'], 'attr') ?>"
                                data-alcance="<?= esc($o['alcance'], 'attr') ?>"
                                data-categoria="<?= esc((string) ($o['categoria'] ?? ''), 'attr') ?>"
                                data-inicio="<?= esc((string) ($o['fecha_inicio'] ?? ''), 'attr') ?>"
                                data-fin="<?= esc((string) ($o['fecha_fin'] ?? ''), 'attr') ?>"
                                data-activa="<?= (int) $o['activa'] ?>"
                                data-productos="<?= esc(json_encode($idsProd), 'attr') ?>">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        </button>
                    </td>
                    <td>
                        <form method="post" action="<?= base_url('ofertas/' . (int) $o['id'] . '/activar') ?>" style="display:inline; margin:0;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-icono" aria-label="<?= (int) $o['activa'] === 1 ? 'Desactivar' : 'Activar' ?>"
                                    title="<?= (int) $o['activa'] === 1 ? 'Desactivar' : 'Activar' ?>">
                                <i class="fa-solid <?= (int) $o['activa'] === 1 ? 'fa-toggle-on' : 'fa-toggle-off' ?>" aria-hidden="true"></i>
                            </button>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="<?= base_url('ofertas/' . (int) $o['id'] . '/borrar') ?>" style="display:inline; margin:0;"
                              onsubmit="return confirm('¿Eliminar esta oferta?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-icono peligro" aria-label="Eliminar oferta" title="Eliminar">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<!-- ── Ventana: crear / editar oferta ─────────────────────────────────── -->
<div id="modalOferta" class="w3-modal oferta-modal" role="dialog" aria-modal="true" aria-labelledby="oferta-titulo" style="display:none;">
    <div class="w3-modal-content">
        <header class="modal-header">
            <h3 id="oferta-titulo">Nueva oferta</h3>
            <button type="button" class="modal-cerrar" data-cerrar-oferta aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>

        <form method="post" action="<?= base_url('ofertas/guardar') ?>" class="modal-form" id="form-oferta">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="of-id" value="">

            <label class="of-campo">
                <span>Nombre de la oferta</span>
                <input type="text" name="nombre" id="of-nombre" maxlength="100" required class="w3-input w3-border"
                       placeholder="Ej. Frutas de temporada">
            </label>

            <fieldset class="of-grupo">
                <legend>Tipo de descuento</legend>
                <label class="of-radio"><input type="radio" name="tipo" value="porcentaje" checked> Porcentaje (%)</label>
                <label class="of-radio" data-solo-productos><input type="radio" name="tipo" value="precio"> Precio fijo ($)</label>
                <div class="of-valor">
                    <span class="of-valor-signo" id="of-signo">%</span>
                    <input type="number" name="valor" id="of-valor" step="0.01" min="0.01" required class="w3-input w3-border"
                           aria-label="Valor del descuento" placeholder="Ej. 20">
                    <small id="of-valor-nota" class="of-nota">De 0 a 100.</small>
                </div>
            </fieldset>

            <fieldset class="of-grupo">
                <legend>¿A qué aplica?</legend>
                <label class="of-radio"><input type="radio" name="alcance" value="productos" checked> Productos que yo elija</label>
                <label class="of-radio"><input type="radio" name="alcance" value="categoria"> Una categoría completa</label>

                <!-- Por categoría -->
                <div class="of-bloque" data-bloque="categoria" hidden>
                    <label class="of-campo">
                        <span>Categoría</span>
                        <select name="categoria" id="of-categoria" class="w3-select w3-border">
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= esc($c, 'attr') ?>"><?= esc($nombreCat($c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <small class="of-nota">El precio fijo no aplica a una categoría; usa porcentaje.</small>
                </div>

                <!-- Por productos -->
                <div class="of-bloque" data-bloque="productos">
                    <div class="of-picker-barra">
                        <input type="text" id="of-buscar-prod" class="w3-input w3-border" placeholder="Buscar producto..."
                               aria-label="Buscar producto">
                        <span class="of-conteo"><span id="of-sel-n">0</span> elegidos</span>
                    </div>
                    <div class="of-picker" id="of-picker">
                        <?php foreach ($productos as $pr): ?>
                            <label class="of-item" data-nombre="<?= esc(mb_strtolower($pr['nombre'] . ' ' . ($pr['categoria'] ?? '')), 'attr') ?>">
                                <input type="checkbox" name="productos[]" value="<?= (int) $pr['id'] ?>">
                                <span class="of-item-nombre"><?= esc($pr['nombre']) ?></span>
                                <span class="of-item-cat"><?= esc($nombreCat((string) ($pr['categoria'] ?? ''))) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <?php if ($productos === []): ?>
                            <p class="of-nota">No hay productos todavía.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </fieldset>

            <fieldset class="of-grupo">
                <legend>Vigencia (opcional)</legend>
                <div class="of-fechas">
                    <label class="of-campo"><span>Inicio</span>
                        <input type="date" name="fecha_inicio" id="of-inicio" class="w3-input w3-border"></label>
                    <label class="of-campo"><span>Fin</span>
                        <input type="date" name="fecha_fin" id="of-fin" class="w3-input w3-border"></label>
                </div>
                <label class="of-activa"><input type="checkbox" name="activa" id="of-activa" value="1" checked> Oferta activa</label>
            </fieldset>

            <div class="of-acciones">
                <button type="button" class="btn btn-secundario" data-cerrar-oferta><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancelar</button>
                <button type="submit" class="btn btn-primario"><i class="fa-solid fa-check" aria-hidden="true"></i> Guardar oferta</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= base_url('js/ofertas.js') ?>" defer></script>
</body>
</html>
