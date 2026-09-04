<?php
require_once __DIR__ . '/includes/security.php';
$pageTitle = 'LifeForum - Hem';
$pageDesc = 'En plats för gemensamma intressen och diskussioner.';
$groups = [];
$groupError = '';
$groupTitle = '';
$groupDescription = '';

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/db/database.php';
    $userId = (int) $_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $groupTitle = trim((string) ($_POST['group_title'] ?? ''));
        $groupDescription = trim((string) ($_POST['group_description'] ?? ''));
        $uploadedImagePath = null;

        if ($groupTitle === '' || strlen($groupTitle) > 150) {
            $groupError = 'Titeln måste vara mellan 1 och 150 tecken.';
        } elseif ($groupDescription === '' || strlen($groupDescription) > 2000) {
            $groupError = 'Beskrivningen måste vara mellan 1 och 2000 tecken.';
        } elseif (isset($_FILES['group_image']) && $_FILES['group_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $image = $_FILES['group_image'];
            $allowedImageTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if ($image['error'] !== UPLOAD_ERR_OK || $image['size'] > 5 * 1024 * 1024) {
                $groupError = 'Bilden måste vara mindre än 5 MB.';
            } else {
                $imageInfo = getimagesize($image['tmp_name']);
                $imageType = $imageInfo['mime'] ?? '';

                if ($imageInfo === false || !isset($allowedImageTypes[$imageType])) {
                    $groupError = 'Bilden måste vara en JPEG-, PNG- eller WebP-bild.';
                } else {
                    $imageFileName = bin2hex(random_bytes(16)) . '.' . $allowedImageTypes[$imageType];
                    $imageTargetDirectory = 'uploads/groups';
                    $imageDirectory = __DIR__ . '/' . $imageTargetDirectory;
                    $imageTarget = "{$imageDirectory}/{$imageFileName}";

                    if (!is_dir($imageDirectory) || !move_uploaded_file($image['tmp_name'], $imageTarget)) {
                        $groupError = 'Bilden kunde inte laddas upp just nu.';
                    } else {
                        $uploadedImagePath = "{$imageTargetDirectory}/{$imageFileName}";
                    }
                }
            }
        }

        if ($groupError === '') {
            try {
                $database = getDatabaseConnection();
                $database->beginTransaction();

                $statement = $database->prepare('INSERT INTO `groups` (title, description, image_path, created_by) VALUES (:title, :description, :image_path, :created_by)');
                $statement->execute([
                    'title' => $groupTitle,
                    'description' => $groupDescription,
                    'image_path' => $uploadedImagePath,
                    'created_by' => $userId,
                ]);
                $groupId = (int) $database->lastInsertId();

                $statement = $database->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (:group_id, :user_id, 'admin')");
                $statement->execute(['group_id' => $groupId, 'user_id' => $userId]);
                $database->commit();

                header('Location: /index');
                exit;
            } catch (Throwable $exception) {
                if (isset($database) && $database->inTransaction()) {
                    $database->rollBack();
                }
                if ($uploadedImagePath !== null) {
                    unlink(__DIR__ . '/' . $uploadedImagePath);
                }
                error_log($exception->getMessage());
                $groupError = 'Gruppen kunde inte skapas just nu.';
            }
        }
    }

    $database = getDatabaseConnection();
    $statement = $database->prepare('SELECT g.id, g.title, g.description, g.image_path, gm.user_id IS NOT NULL AS is_member FROM `groups` AS g LEFT JOIN group_members AS gm ON gm.group_id = g.id AND gm.user_id = :user_id ORDER BY is_member DESC, g.title');
    $statement->execute(['user_id' => $userId]);
    $groups = $statement->fetchAll();
}
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
    <link rel="stylesheet" href="assets/css/frontpage/index.css">
    <link rel="stylesheet" href="assets/css/auth/index.css">
</head>

<body>
    <?php require __DIR__ . '/includes/header.php'; ?>

    <main class="frontpage<?= isset($_SESSION['user_id']) ? '' : ' frontpage-guest' ?>">
        <?php if (isset($_SESSION['user_id'])): ?>
            <section class="groups-overview" aria-labelledby="groups-heading">
                <div class="groups-heading-row">
                    <div class="groups-heading-row-desc">
                        <h1 id="groups-heading">Grupper</h1>
                        <p>Hittar du ingen grupp för dig? Skapa en ny!</p>
                    </div>
                    <button class="open-group-modal" type="button" data-modal-open="group-modal">Skapa grupp</button>
                </div>
                <?php if ($groupError !== ''): ?>
                    <p class="group-error" role="alert"><?= escape($groupError) ?></p>
                <?php endif; ?>
                <?php if ($groups === []): ?>
                    <p>Det finns inga grupper ännu.</p>
                <?php else: ?>
                    <div class="group-list">
                        <?php foreach ($groups as $group): ?>
                            <a class="group-card-link" href="/groups?id=<?= (int) $group['id'] ?>" aria-label="Gå till gruppen <?= escape((string) $group['title']) ?>">
                                <article class="group-item">
                                    <?php if ($group['image_path'] !== null): ?>
                                        <img src="/<?= escape((string) $group['image_path']) ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <img src="/assets/img/group-placeholder.png" alt="" loading="lazy" class="placeholder">
                                    <?php endif; ?>
                                    <div class="group-item-content">
                                        <h2><?= escape((string) $group['title']) ?></h2>
                                        <?php if ((bool) $group['is_member']): ?>
                                            <span class="success">Medlem</span>
                                        <?php else: ?>
                                            <span class="error">Ej medlem</span>
                                        <?php endif; ?>
                                        <?php if ($group['description'] !== null): ?>
                                            <p class="group-description"><?= escape((string) $group['description']) ?></p>
                                        <?php else: ?>
                                            <p class="group-description">Denna grupp har ingen beskrivning.</p>
                                        <?php endif; ?>
                                        <p class="group-instructions">Klicka på gruppen för att gå till gruppens sida.</p>
                                    </div>
                                </article>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <dialog class="group-modal" id="group-modal" aria-labelledby="group-modal-title" <?= $groupError !== '' ? 'open' : '' ?>>
                <form class="group-modal-form" method="post" enctype="multipart/form-data">
                    <div class="group-modal-header">
                        <h2 id="group-modal-title">Skapa grupp</h2>
                        <button class="close-group-modal" type="button" data-modal-close="group-modal"
                            aria-label="Stäng">&times;</button>
                    </div>
                    <label for="group_title">Titel</label>
                    <input id="group_title" name="group_title" type="text" maxlength="50" value="<?= escape($groupTitle) ?>"
                        required>
                    <label for="group_description">Beskrivning</label>
                    <textarea id="group_description" name="group_description" maxlength="2000" rows="5"
                        required><?= escape($groupDescription) ?></textarea>
                    <label for="group_image">Bild (valfritt, max 5 MB)</label>
                    <input id="group_image" name="group_image" type="file" accept="image/jpeg,image/png,image/webp">
                    <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                    <div class="group-modal-actions">
                        <button class="cancel-group-modal" type="button" data-modal-close="group-modal">Avbryt</button>
                        <button type="submit">Skapa grupp</button>
                    </div>
                </form>
            </dialog>
        <?php else: ?>
            <section class="welcome-panel" aria-labelledby="welcome-heading">
                <div class="welcome-content">
                    <h1 id="welcome-heading">Hitta din gemenskap.</h1>
                    <p class="welcome-text">Upptäck grupper, dela dina intressen och delta i diskussioner med andra.</p>
                    <div class="home-actions">
                        <a class="home-action-secondary" href="/login/">Logga in</a>
                        <a class="home-action-primary" href="/register/">Skapa konto</a>
                    </div>
                </div>
                <img src="/assets/img/banner.png" alt="" loading="lazy">
            </section>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>

</html>