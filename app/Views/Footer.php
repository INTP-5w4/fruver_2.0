<footer class="footer">
    <div class="footer_container">
        <div class="footer_brand">
            <span class="footer_logo">FRUVER</span>
            <span class="footer_tagline">Sistema de gestión de inventario</span>
        </div>
        <div class="footer_copy">
            &copy; <?= date('Y') ?> Fruver. Todos los derechos reservados.
        </div>
    </div>
</footer>

<script>
    setTimeout(() => {
        const flash = document.querySelector('.flash-container');
        if (flash) {
            flash.style.transition = 'opacity 0.5s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.style.display = 'none', 500);
        }
    }, 3000);
</script>
