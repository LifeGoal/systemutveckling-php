<?php

declare(strict_types=1);

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index');
    exit;
}

require_once __DIR__ . '/db/database.php';

$error = '';
$email = strtolower(trim((string) ($_POST['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'E-postadress eller lösenord är fel.';
    } else {
        $database = getDatabaseConnection();
        $statement = $database->prepare('SELECT id, first_name, last_name, password_hash FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user === false || !password_verify($password, $user['password_hash'])) {
            $error = 'E-postadress eller lösenord är fel.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];

            header('Location: index');
            exit;
        }
    }
}

function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!doctype html>
<html lang="sv">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LifeForum - Logga in</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <main class="auth-page">
        <!-- Visste inte om man fick använda ikon-libraries för denna uppgiften så använde jag en enkel HTML-kod istället -->
        <a class="back-link" href="index"><p>&#8592;</p> Tillbaka till LifeForum</a>
        <section class="auth-card">
            <img src="/assets/img/logo.png" accesskey="" alt="Logotyp för LifeForum" class="auth-logo">
            <div class="auth-description">
                <h1>Välkommen</h1>
                <p>Logga in för att fortsätta till dina grupper och diskussioner.</p>
            </div>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success" role="status">
                    <p>Varmt välkommen!</p>
                    <p>Kontot har skapat. Du kan nu logga in.</p>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <p>Ett fel uppstod vid inloggning:</p>
                    <p><?= escape($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <label for="email">E-post</label>
                <input id="email" name="email" type="email" placeholder="din@email.se" value="<?= escape($email) ?>" required autocomplete="email">

                <label for="password">Lösenord</label>
                <input id="password" name="password" type="password" placeholder="Ditt lösenord" required autocomplete="current-password">

                <button type="submit">Logga in</button>
            </form>

            <p class="form-footer">Har du inget konto? <a href="register">Skapa konto</a></p>
        </section>
    </main>
</body>

</html>