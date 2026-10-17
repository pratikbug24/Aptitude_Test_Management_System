<?php
/**
 * Minimalist standalone layout for the sign-in / sign-up screens.
 *
 * Expected variables:
 *   $pageTitle (string)
 *   $panelTitle (string)
 *   $panelSubtitle (string, optional)
 *   $panelIcon (string, optional) Short glyph rendered in the panel badge.
 *   $panelBody (callable) Renders the form markup.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pageTitle     = $pageTitle ?? APP_NAME;
$panelTitle    = $panelTitle ?? '';
$panelSubtitle = $panelSubtitle ?? '';
$panelIcon     = $panelIcon ?? APP_SHORT_NAME[0];
$flashes       = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &middot; <?= e(APP_SHORT_NAME) ?></title>
    <link rel="shortcut icon" type="image/png" href="<?= e(app_url('assets/img/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="auth-body">
<a class="skip-link" href="#main">Skip to content</a>

<div class="auth">
    <aside class="auth__aside">
        <a class="brand brand--light" href="<?= e(app_url('index.php')) ?>">
            <span class="brand__mark" aria-hidden="true"><?= e(APP_SHORT_NAME[0]) ?></span>
            <span class="brand__text">
                <span class="brand__name"><?= e(APP_NAME) ?></span>
                <span class="brand__tag"><?= e(APP_TAGLINE) ?></span>
            </span>
        </a>
        <div class="auth__pitch">
            <h2>Assess aptitude with confidence</h2>
            <ul class="auth__points">
                <li>Timed, topic-wise practice quizzes</li>
                <li>Instant scoring with a detailed breakdown</li>
                <li>Live leaderboard and attempt history</li>
            </ul>
        </div>
        <p class="auth__foot">Secure sign-in &middot; Paperless &middot; Instant results</p>
    </aside>

    <main id="main" class="auth__panel">
        <div class="card card--auth">
            <div class="card__head">
                <span class="card__icon" aria-hidden="true"><?= e($panelIcon) ?></span>
                <h1 class="card__title"><?= e($panelTitle) ?></h1>
                <?php if ($panelSubtitle !== ''): ?>
                    <p class="card__subtitle"><?= e($panelSubtitle) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($flashes): ?>
                <div class="flash-stack">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="flash flash--<?= e($flash['type']) ?>">
                            <span class="flash__dot" aria-hidden="true"></span>
                            <?= e($flash['message']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php $panelBody(); ?>
        </div>
    </main>
</div>

<script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
