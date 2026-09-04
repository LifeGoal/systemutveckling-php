<?php
require_once __DIR__ . '/includes/security.php';
$pageTitle = 'LifeForum - Hem';
$pageDesc = 'En plats för gemensamma intressen och diskussioner.';
?>
<!doctype html>
<html lang="sv">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/base/index.css">
    <link rel="stylesheet" href="assets/css/layout/header.css">
    <link rel="stylesheet" href="assets/css/layout/footer.css">
    <link rel="stylesheet" href="assets/css/pages/index.css">
    <link rel="stylesheet" href="assets/css/auth/index.css">
</head>

<body>
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main>
        <img src="/assets/img/logo.png" accesskey="" alt="Logotyp för LifeForum" class="logo">
        <?php if (isset($_SESSION['user_id'])): ?>
            <p>Välkommen, <?= htmlspecialchars((string) $_SESSION['first_name'], ENT_QUOTES, 'UTF-8') ?>.</p>
            <form method="post" action="/logout/">
                <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                <button type="submit">Logga ut</button>
            </form>
        <?php else: ?>
            <p>En plats för gemensamma intressen och diskussioner.</p>
            <a href="/login/">Logga in</a>
            <a href="/register/">Skapa konto</a>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>

</html>