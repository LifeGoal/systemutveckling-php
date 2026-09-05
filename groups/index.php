<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../db/database.php';

$userId = requireLogin();
$groupId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$groupId = $groupId === false || $groupId === null ? 0 : $groupId;
$database = getDatabaseConnection();
$error = '';
$success = '';
$publishedReplyId = 0;

if ($groupId < 1) {
    http_response_code(404);
    exit('Gruppen kunde inte hittas.');
}

$groupStatement = $database->prepare('SELECT g.id, g.title, g.description, g.image_path, g.created_at, u.first_name AS creator_first_name, u.last_name AS creator_last_name, gm.role AS member_role FROM `groups` AS g INNER JOIN users AS u ON u.id = g.created_by LEFT JOIN group_members AS gm ON gm.group_id = g.id AND gm.user_id = :user_id WHERE g.id = :group_id');
$groupStatement->execute(['user_id' => $userId, 'group_id' => $groupId]);
$group = $groupStatement->fetch();

if ($group === false) {
    http_response_code(404);
    exit('Gruppen kunde inte hittas.');
}

$isMember = $group['member_role'] !== null;
$isAdmin = $group['member_role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'apply') {
            if ($isMember) {
                $error = 'Du är redan medlem i gruppen.';
            } else {
                $applicationStatement = $database->prepare('SELECT status FROM group_applications WHERE group_id = :group_id AND user_id = :user_id');
                $applicationStatement->execute(['group_id' => $groupId, 'user_id' => $userId]);
                $application = $applicationStatement->fetch();

                if ($application !== false && $application['status'] === 'pending') {
                    $error = 'Du har redan ansökt om medlemskap.';
                } else {
                    $statement = $database->prepare('INSERT INTO group_applications (group_id, user_id, status, reviewed_by, reviewed_at) VALUES (:group_id, :user_id, \'pending\', NULL, NULL) ON DUPLICATE KEY UPDATE status = \'pending\', reviewed_by = NULL, reviewed_at = NULL');
                    $statement->execute(['group_id' => $groupId, 'user_id' => $userId]);
                    $success = 'Din ansökan har skickats.';
                }
            }
        } elseif ($action === 'create_discussion') {
            if (!$isMember) {
                $error = 'Du måste vara medlem för att starta en diskussion.';
            } else {
                $subject = trim((string) ($_POST['subject'] ?? ''));
                $content = trim((string) ($_POST['content'] ?? ''));
                if ($subject === '' || strlen($subject) > 200 || $content === '' || strlen($content) > 10000) {
                    $error = 'Ämnet måste vara 1-200 tecken och inlägget 1-10000 tecken.';
                } else {
                    $database->beginTransaction();
                    $statement = $database->prepare('INSERT INTO discussions (group_id, created_by, subject) VALUES (:group_id, :user_id, :subject)');
                    $statement->execute(['group_id' => $groupId, 'user_id' => $userId, 'subject' => $subject]);
                    $discussionId = (int) $database->lastInsertId();
                    $statement = $database->prepare('INSERT INTO posts (discussion_id, user_id, content) VALUES (:discussion_id, :user_id, :content)');
                    $statement->execute(['discussion_id' => $discussionId, 'user_id' => $userId, 'content' => $content]);
                    $database->commit();
                    $success = 'Diskussionen har skapats.';
                }
            }
        } elseif ($action === 'reply') {
            if (!$isMember) {
                $error = 'Du måste vara medlem för att svara.';
            } else {
                $discussionId = filter_var($_POST['discussion_id'] ?? null, FILTER_VALIDATE_INT);
                $content = trim((string) ($_POST['content'] ?? ''));
                $discussionId = $discussionId === false || $discussionId === null ? 0 : $discussionId;
                $statement = $database->prepare('INSERT INTO posts (discussion_id, user_id, content) SELECT d.id, :user_id, :content FROM discussions AS d WHERE d.id = :discussion_id AND d.group_id = :group_id');
                if ($discussionId < 1 || $content === '' || strlen($content) > 10000) {
                    $error = 'Svaret måste vara mellan 1 och 10000 tecken.';
                } else {
                    $statement->execute([
                        'user_id' => $userId,
                        'content' => $content,
                        'discussion_id' => $discussionId,
                        'group_id' => $groupId,
                    ]);
                    if ($statement->rowCount() === 1) {
                        $statement = $database->prepare('UPDATE discussions SET updated_at = CURRENT_TIMESTAMP WHERE id = :discussion_id AND group_id = :group_id');
                        $statement->execute(['discussion_id' => $discussionId, 'group_id' => $groupId]);
                        $publishedReplyId = (int) $database->lastInsertId();
                    } else {
                        $error = 'Diskussionen kunde inte hittas.';
                    }
                }
            }
        } elseif ($action === 'approve' && $isAdmin) {
            $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
            $applicationId = $applicationId === false || $applicationId === null ? 0 : $applicationId;
            $database->beginTransaction();
            $statement = $database->prepare('SELECT user_id FROM group_applications WHERE id = :application_id AND group_id = :group_id AND status = \'pending\' FOR UPDATE');
            $statement->execute(['application_id' => $applicationId, 'group_id' => $groupId]);
            $application = $statement->fetch();
            if ($application === false) {
                $database->rollBack();
                $error = 'Ansökan kunde inte hittas.';
            } else {
                $statement = $database->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (:group_id, :user_id, \'member\')');
                $statement->execute(['group_id' => $groupId, 'user_id' => $application['user_id']]);
                $statement = $database->prepare('UPDATE group_applications SET status = \'approved\', reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :application_id');
                $statement->execute(['reviewed_by' => $userId, 'application_id' => $applicationId]);
                $database->commit();
                $success = 'Ansökan har godkänts.';
            }
        } elseif ($action === 'approve') {
            $error = 'Du saknar behörighet för detta.';
        }
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        error_log($exception->getMessage());
        $error = 'Åtgärden kunde inte genomföras just nu.';
    }
}

$memberCountStatement = $database->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = :group_id');
$memberCountStatement->execute(['group_id' => $groupId]);
$memberCount = (int) $memberCountStatement->fetchColumn();

$application = null;
if (!$isMember) {
    $statement = $database->prepare('SELECT status FROM group_applications WHERE group_id = :group_id AND user_id = :user_id');
    $statement->execute(['group_id' => $groupId, 'user_id' => $userId]);
    $application = $statement->fetchColumn() ?: null;
}

$discussions = [];
if ($isMember) {
    $statement = $database->prepare('SELECT d.id, d.subject, d.created_at, u.first_name, u.last_name, (SELECT COUNT(*) FROM posts AS p2 WHERE p2.discussion_id = d.id) AS post_count FROM discussions AS d INNER JOIN users AS u ON u.id = d.created_by WHERE d.group_id = :group_id ORDER BY d.updated_at DESC, d.id DESC');
    $statement->execute(['group_id' => $groupId]);
    $discussions = $statement->fetchAll();
    foreach ($discussions as &$discussion) {
        $postStatement = $database->prepare('SELECT p.id, p.content, p.created_at, u.first_name, u.last_name FROM posts AS p INNER JOIN users AS u ON u.id = p.user_id WHERE p.discussion_id = :discussion_id ORDER BY p.created_at ASC');
        $postStatement->execute(['discussion_id' => $discussion['id']]);
        $discussion['posts'] = $postStatement->fetchAll();
    }
    unset($discussion);
}

$applications = [];
if ($isAdmin) {
    $statement = $database->prepare('SELECT a.id, a.created_at, u.first_name, u.last_name, u.email FROM group_applications AS a INNER JOIN users AS u ON u.id = a.user_id WHERE a.group_id = :group_id AND a.status = \'pending\' ORDER BY a.created_at ASC');
    $statement->execute(['group_id' => $groupId]);
    $applications = $statement->fetchAll();
}

$pageTitle = (string) 'LifeForum - ' . $group['title'];
?>

<!DOCTYPE html>
<html lang="sv">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Gruppdiskussioner på LifeForum.">
    <title><?= escape($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/base/index.css">
    <link rel="stylesheet" href="/assets/css/layout/header.css">
    <link rel="stylesheet" href="/assets/css/layout/footer.css">
    <link rel="stylesheet" href="/assets/css/groups/index.css">
</head>

<body>
    <?php require __DIR__ . '/../includes/header.php'; ?>
    <main class="group-page">
        <a class="back-link" href="/index">&larr; Alla grupper</a>
        <header class="group-hero">
            <?php if ($group['image_path'] !== null): ?><img src="/<?= escape((string) $group['image_path']) ?>" alt=""><?php endif; ?>
            <div class="group-hero-content">
                <h1><?= escape((string) $group['title']) ?></h1>
                <div class="group-description" data-expandable-description>
                    <p class="group-description-text"><?= escape((string) $group['description']) ?></p>
                    <button class="read-more-button" type="button" data-description-toggle aria-expanded="false">Läs mer...</button>
                </div>
                <p class="group-meta"><?= $memberCount ?> medlemmar</p>
                <p class="group-meta">Skapad av <?= escape((string) $group['creator_first_name'] . ' ' . $group['creator_last_name']) ?></p>
            </div>
        </header>
        <?php if ($error !== ''): ?><p class="notification error" role="alert"><?= escape($error) ?></p><?php endif; ?>
        <?php if ($success !== ''): ?><p class="notification success" role="status"><?= escape($success) ?></p><?php endif; ?>
        <?php if (!$isMember): ?>
            <section class="access-panel">
                <h2>Gå med i gruppen</h2>
                <p>Som medlem kan du läsa och delta i gruppens diskussioner.</p>
                <?php if ($application === 'pending'): ?>
                    <p class="notification">Din ansökan väntar på godkännande.</p>
                <?php else: ?>
                    <form method="post"><input type="hidden" name="action" value="apply"><input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>"><button type="submit">Ansök om medlemskap</button></form>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <div class="group-columns">
                <section class="discussion-section">
                    <h2>Diskussioner</h2>
                    <form class="discussion-form" method="post">
                        <h3>Starta en diskussion</h3>
                        <input type="hidden" name="action" value="create_discussion">
                        <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">

                        <div class="discussion-form-group">
                            <label for="subject">Rubrik</label>
                            <input id="subject" name="subject" maxlength="200" required>
                        </div>

                        <div class="discussion-form-group">
                            <label for="content">Innehåll</label>
                            <textarea id="content" name="content" maxlength="10000" rows="4" required></textarea>
                        </div>

                        <button type="submit">Publicera</button>
                    </form>
                    <?php if ($discussions === []): ?>
                        <p class="empty-state">Det finns inga diskussioner ännu.</p><?php endif; ?>
                    <?php foreach ($discussions as $discussion): ?>
                        <article class="discussion">
                            <div class="discussion-header">
                                <h3><?= escape((string) $discussion['subject']) ?></h3>
                                <p class="discussion-meta">Startad av <?= escape((string) $discussion['first_name'] . ' ' . $discussion['last_name']) ?></p>
                                <p class="discussion-meta"><strong><?= (int) $discussion['post_count'] ?></strong> inlägg totalt</p>
                                <?php $openingPost = $discussion['posts'][0] ?? null; ?>
                                <?php if ($openingPost !== null): ?>
                                    <div class="opening-post">
                                        <div class="post-byline">
                                            <strong><?= escape((string) $openingPost['first_name'] . ' ' . $openingPost['last_name']) ?></strong>
                                            <span>#1</span>
                                            <time datetime="<?= escape((string) $openingPost['created_at']) ?>"><?= escape((string) $openingPost['created_at']) ?></time>
                                        </div>
                                        <p><?= nl2br(escape((string) $openingPost['content'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php foreach (array_slice($discussion['posts'], 1) as $postNumber => $post): ?>
                                <div class="post reply-post">
                                    <div class="post-byline">
                                        <strong><?= escape((string) $post['first_name'] . ' ' . $post['last_name']) ?></strong>
                                        <span>#<?= $postNumber + 2 ?></span>
                                        <time datetime="<?= escape((string) $post['created_at']) ?>"><?= escape((string) $post['created_at']) ?></time>
                                    </div>
                                    <p><?= nl2br(escape((string) $post['content'])) ?></p>
                                </div><?php endforeach; ?>
                            <form class="reply-form" method="post">
                                <input type="hidden" name="action" value="reply">
                                <input type="hidden" name="discussion_id" value="<?= (int) $discussion['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                                <label for="reply-<?= (int) $discussion['id'] ?>">Svara</label>
                                <textarea id="reply-<?= (int) $discussion['id'] ?>" name="content" maxlength="10000" rows="3" required></textarea>
                                <button type="submit">Svara</button>
                            </form>
                        </article><?php endforeach; ?>
                </section>
                <?php if ($isAdmin): ?>
                    <aside class="admin-panel">
                        <h2>Ansökningar</h2>
                        <div class="admin-panel-content">
                            <?php if ($applications === []): ?>
                                <p>Inga väntande ansökningar.</p>
                            <?php else: ?>
                                <?php foreach ($applications as $pending): ?>
                                    <div class="application">
                                        <strong><?= escape((string) $pending['first_name'] . ' ' . $pending['last_name']) ?></strong>
                                        <small><?= escape((string) $pending['email']) ?></small>
                                        <form method="post">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="application_id" value="<?= (int) $pending['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                                            <button type="submit">Godkänn</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </aside>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>