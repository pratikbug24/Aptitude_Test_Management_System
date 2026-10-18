<?php
/**
 * Shared page head + navigation.
 *
 * Expected variables before include:
 *   $pageTitle (string)  Browser/document title.
 *   $pageHeading (string, optional) Heading shown in the page header band.
 *   $pageLead (string, optional) Supporting sentence under the heading.
 *   $activeNav (string, optional) Key of the navigation item to highlight.
 *   $navItems (array, optional) Navigation entries: label => href.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pageTitle   = $pageTitle ?? APP_NAME;
$pageHeading = $pageHeading ?? '';
$pageLead    = $pageLead ?? '';
$activeNav   = $activeNav ?? '';
$navItems    = $navItems ?? [];
$bodyClass   = $bodyClass ?? '';
$flashes     = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(APP_TAGLINE) ?>">
    <title><?= e($pageTitle) ?> &middot; <?= e(APP_SHORT_NAME) ?></title>
    <link rel="shortcut icon" type="image/png" href="<?= e(app_url('./assets/image/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="shell site-header__inner">
        <a class="brand" href="<?= e(app_url(is_admin() ? 'admin/dashboard.php' : 'index.php')) ?>">
            <span class="brand__mark" aria-hidden="true"><img src="./assets/image/logo.png" alt=""></span>
            <span class="brand__text">
                <span class="brand__name"><?= e(APP_NAME) ?></span>
                <span class="brand__tag"><?= e(APP_TAGLINE) ?></span>
            </span>
        </a>

        <?php if ($navItems): ?>
            <input class="nav-toggle" type="checkbox" id="nav-toggle" hidden>
            <label class="nav-toggle__label" for="nav-toggle" aria-label="Toggle navigation">
                <span></span><span></span><span></span>
            </label>
            <nav class="site-nav" aria-label="Main">
                <ul>
                    <?php foreach ($navItems as $label => $href): ?>
                        <li>
                            <a href="<?= e($href) ?>"<?= $activeNav === $label ? ' class="is-active" aria-current="page"' : '' ?>>
                                <?= e($label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</header>

<?php if ($flashes): ?>
    <div class="shell flash-stack" role="status" aria-live="polite">
        <?php foreach ($flashes as $flash): ?>
            <div class="flash flash--<?= e($flash['type']) ?>">
                <span class="flash__dot" aria-hidden="true"></span>
                <?= e($flash['message']) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($pageHeading !== ''): ?>
    <section class="page-band">
        <div class="shell">
            <h1 class="page-band__title"><?= e($pageHeading) ?></h1>
            <?php if ($pageLead !== ''): ?>
                <p class="page-band__lead"><?= e($pageLead) ?></p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<main id="main" class="site-main">
    <div class="shell">
