<?php
session_start();
$pageTitle = 'LifeForum';
$pageDesc = 'En plats för gemensamma intressen och diskussioner.';
?>
<!doctype html>
<html lang="sv">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <main>
        <img src="/assets/img/logo.png" accesskey="" alt="Logotyp för LifeForum" class="logo">
        <?php if (isset($_SESSION['user_id'])): ?>
            <p>Välkommen, <?= htmlspecialchars((string) $_SESSION['first_name'], ENT_QUOTES, 'UTF-8') ?>.</p>
            <a href="logout">Logga ut</a>
        <?php else: ?>
            <p>En plats för gemensamma intressen och diskussioner.</p>
            <a href="login">Logga in</a>
            <a href="register">Skapa konto</a>
        <?php endif; ?>
    </main>
    <script src="assets/js/app.js" defer></script>
</body>

</html>