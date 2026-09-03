<?php

declare(strict_types=1);

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index');
    exit;
}

require_once __DIR__ . '/db/database.php';

$errors = [];
$firstName = '';
$lastName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($firstName === '' || $lastName === '') $errors[] = 'Förnamn och efternamn måste fyllas i.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ange en giltig e-postadress.';
    if (strlen($password) < 8) $errors[] = 'Lösenordet måste vara minst 8 tecken.';

    if ($errors === []) {
        try {
            $database = getDatabaseConnection();
            $statement = $database->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (:first_name, :last_name, :email, :password_hash)');
            $statement->execute([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            header('Location: login?registered=1');
            exit;
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $errors[] = 'Det finns redan ett konto med den e-postadressen.';
            } else {
                error_log($exception->getMessage());
                $errors[] = 'Registreringen kunde inte genomföras just nu.';
            }
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
    <title>LifeForum - Skapa konto</title>
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
                <h1>Skapa konto</h1>
                <p>Gå med för att delta i diskussioner och hitta grupper som passar dina intressen.</p>
            </div>

            <?php if ($errors !== []): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= escape($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <label for="first_name">Förnamn</label>
                <input id="first_name" name="first_name" type="text" value="<?= escape($firstName) ?>"
                    placeholder="John" required autocomplete="given-name">

                <label for="last_name">Efternamn</label>
                <input id="last_name" name="last_name" type="text" value="<?= escape($lastName) ?>"
                    placeholder="Andersson" required autocomplete="family-name">

                <label for="email">E-post</label>
                <input id="email" name="email" type="email" value="<?= escape($email) ?>" required
                    placeholder="john.andersson@email.se" autocomplete="email">

                <label for="password">Lösenord</label>
                <input id="password" name="password" type="password" required minlength="8" placeholder="Ditt lösenord"
                    autocomplete="new-password">

                <button type="submit">Skapa konto</button>
            </form>

            <p class="form-footer">Har du redan ett konto? <a href="login">Logga in</a></p>
        </section>
    </main>
</body>

</html>