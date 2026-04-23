<?php // includes/footer.php
?>
</main>

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>
<script>
(function() {
    var bp = '<?= BASE_PATH ?>';
    window.apiBase = bp ? bp + '/api' : '/api';
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?php if (isset($extraScripts)): ?>
<?php foreach((array)$extraScripts as $src): ?>
<script src="<?= preg_match('#^https?://#', $src) ? $src : url($src) ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
