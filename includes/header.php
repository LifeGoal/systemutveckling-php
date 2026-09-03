<header class="site-header">
    <div class="site-header-inner">
        <a class="site-logo" href="index" aria-label="LifeForums startsida">
            <img src="/assets/img/logo.png" alt="LifeForum logotyp">
        </a>

        <nav class="site-nav" aria-label="Huvudnavigation">
            <a href="index">Hem</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <details class="profile-menu">
                    <summary class="profile-menu-trigger">
                        <?= htmlspecialchars((string) $_SESSION['first_name'], ENT_QUOTES, 'UTF-8') ?>
                        <?= htmlspecialchars((string) $_SESSION['last_name'], ENT_QUOTES, 'UTF-8') ?>
                    </summary>
                    <div class="profile-menu-dropdown">
                        <a href="profile">Profil</a>
                        <a href="logout">Logga ut</a>
                    </div>
                </details>
            <?php else: ?>
                <a href="login">Logga in</a>
                <a class="site-primary-nav" href="register">Skapa konto</a>
            <?php endif; ?>
        </nav>
    </div>
</header>