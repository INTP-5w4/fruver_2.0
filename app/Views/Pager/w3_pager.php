<nav class="paginacion" aria-label="Páginas">
    <?php if ($pager->hasPrevious()) : ?>
        <a href="<?= $pager->getPreviousPage() ?>" class="w3-button w3-border" aria-label="Página anterior">&laquo;</a>
    <?php endif; ?>

    <?php foreach ($pager->links() as $link) : ?>
        <a href="<?= $link['uri'] ?>" class="w3-button <?= $link['active'] ? 'w3-green' : 'w3-border' ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?>>
            <?= $link['title'] ?>
        </a>
    <?php endforeach; ?>

    <?php if ($pager->hasNext()) : ?>
        <a href="<?= $pager->getNextPage() ?>" class="w3-button w3-border" aria-label="Página siguiente">&raquo;</a>
    <?php endif; ?>
</nav>
