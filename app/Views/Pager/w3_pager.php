<?php
/**
 * Pager de panel (w3.css).
 *
 * Muestra como máximo 5 números de página: la actual, 2 anteriores y 2 posteriores
 * (setSurroundCount(2)). Para moverse más allá de esa ventana se usan las flechas
 * « / », que avanzan o retroceden una página y recentran la ventana. Así, aunque haya
 * 100 páginas, la barra nunca lista las 100.
 */
$pager->setSurroundCount(2);
?>
<nav class="paginacion" aria-label="Páginas">
    <?php if ($pager->hasPreviousPage()) : ?>
        <a href="<?= $pager->getPreviousPage() ?>" class="w3-button w3-border" aria-label="Página anterior" rel="prev">&laquo;</a>
    <?php endif; ?>

    <?php foreach ($pager->links() as $link) : ?>
        <a href="<?= $link['uri'] ?>" class="w3-button <?= $link['active'] ? 'w3-green' : 'w3-border' ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?>>
            <?= $link['title'] ?>
        </a>
    <?php endforeach; ?>

    <?php if ($pager->hasNextPage()) : ?>
        <a href="<?= $pager->getNextPage() ?>" class="w3-button w3-border" aria-label="Página siguiente" rel="next">&raquo;</a>
    <?php endif; ?>
</nav>
