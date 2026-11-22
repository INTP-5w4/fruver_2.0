<?php
/**
 * Auditoría de clientes y productos (Controllers\Auditoria). Solo consulta.
 *
 * @var list<array<string,mixed>> $movimientos
 * @var bool                      $instalada
 * @var string|null               $tabla
 * @var int|null                  $idRegistro
 * @var string                    $buscar
 */
use App\Models\Modelo_auditoria;

$hayFiltros = $tabla !== null || $idRegistro !== null || $buscar !== '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="lista-wrapper">

<div class="contenedor-boton">
    <div>
        <span class="pagina-sub">Clientes y productos · solo consulta</span>
        <h1>Auditoría</h1>
    </div>
</div>

<p class="w3-small w3-text-grey" style="max-width:760px;">
    Cada alta, modificación o eliminación de un cliente o un producto queda registrada aquí con la cuenta que la hizo.
    Los registros no se pueden editar ni borrar. De los clientes se anota qué datos cambiaron, no sus valores,
    para no duplicar datos personales.
</p>

<?php if (! $instalada): ?>
    <div class="w3-panel w3-pale-yellow w3-border">
        <p>La auditoría aún no está instalada. Ejecuta <code>php spark migrate</code>
           (o <code>database_cambios/auditoria_cambios.sql</code>).</p>
    </div>
<?php else: ?>

<form method="get" action="<?= base_url('auditoria') ?>" class="buscador-lista" role="search">
    <div>
        <label for="aud-tabla" class="w3-small"><b>Catálogo</b></label>
        <select id="aud-tabla" name="tabla" class="w3-select w3-border" style="max-width:180px;">
            <option value="">Todos</option>
            <?php foreach (Modelo_auditoria::TABLAS as $clave => $texto): ?>
                <option value="<?= esc($clave, 'attr') ?>"<?= $tabla === $clave ? ' selected' : '' ?>><?= esc($texto) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="aud-id" class="w3-small"><b>Número</b></label>
        <input type="number" id="aud-id" name="id" min="1" step="1" value="<?= $idRegistro !== null ? (int) $idRegistro : '' ?>"
               class="w3-input w3-border" style="max-width:110px;" data-sin-reglas>

        <label for="aud-buscar" class="w3-small"><b>Texto</b></label>
        <input type="search" id="aud-buscar" name="buscar" value="<?= esc($buscar, 'attr') ?>" maxlength="100"
               placeholder="Cuenta o detalle…" class="w3-input w3-border">

        <button type="submit" class="w3-button w3-green"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a href="<?= base_url('auditoria') ?>" class="w3-button w3-border">✕ Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="tabla-wrapper">
<table>
    <caption class="w3-left-align w3-small w3-text-grey" style="caption-side:top;padding:6px 0;">
        Movimientos del más reciente al más antiguo
    </caption>
    <thead>
        <tr>
            <th scope="col">Fecha</th>
            <th scope="col">Catálogo</th>
            <th scope="col">Número</th>
            <th scope="col">Acción</th>
            <th scope="col">Detalle</th>
            <th scope="col">Cuenta</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($movimientos === []): ?>
            <tr><td colspan="6" class="w3-center w3-text-grey">No hay movimientos<?= $hayFiltros ? ' con estos filtros' : '' ?>.</td></tr>
        <?php endif; ?>
        <?php foreach ($movimientos as $m): ?>
            <tr>
                <td><time datetime="<?= esc(date('c', strtotime((string) $m['fecha'])), 'attr') ?>"><?= esc(date('d/m/Y H:i', strtotime((string) $m['fecha']))) ?></time></td>
                <td><?= esc(Modelo_auditoria::TABLAS[$m['tabla']] ?? $m['tabla']) ?></td>
                <td>
                    <a href="<?= base_url('auditoria') . '?' . esc(http_build_query(['tabla' => $m['tabla'], 'id' => (int) $m['id_registro']]), 'attr') ?>"
                       title="Ver solo este registro">#<?= (int) $m['id_registro'] ?></a>
                </td>
                <td><?= esc(Modelo_auditoria::ACCIONES[$m['accion']] ?? $m['accion']) ?></td>
                <td><?= esc((string) ($m['detalle'] ?? '')) ?></td>
                <td><?= esc((string) $m['usuario']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?= $pager !== null ? $pager->links('default', 'w3_pager') : '' ?>
</div>

<?php endif; ?>
</div>

<?php include 'Footer.php'; ?>
</body>
</html>
