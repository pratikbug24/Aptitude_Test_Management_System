<?php
/**
 * Shared page footer. Closes the markup opened by includes/header.php.
 */

declare(strict_types=1);
?>
    </div>
</main>

<footer class="site-footer">
    <div class="shell site-footer__inner">
        <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
        <p class="site-footer__meta">Built with PHP &amp; MySQL &middot; <?= e(APP_TAGLINE) ?></p>
    </div>
</footer>

<script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
