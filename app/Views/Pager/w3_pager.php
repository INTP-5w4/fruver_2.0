<div class="w3-bar" style="text-align:center; margin-top:20px;">

    <!-- Botón anterior -->
    <?php if ($pager->hasPrevious()) : ?>
        <a href="<?= $pager->getPreviousPage() ?>" class="w3-button">&laquo;</a>
    <?php endif; ?>

    <!-- Números -->
    <?php foreach ($pager->links() as $link) : ?>
        <a href="<?= $link['uri'] ?>"
           class="w3-button <?= $link['active'] ? 'w3-green' : '' ?>">
            <?= $link['title'] ?>
        </a>
    <?php endforeach; ?>

    <!-- Botón siguiente -->
    <?php if ($pager->hasNext()) : ?>
        <a href="<?= $pager->getNextPage() ?>" class="w3-button">&raquo;</a>
    <?php endif; ?>

</div>