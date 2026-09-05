<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../db/database.php';

$userId = requireLogin();
$token = trim((string) ($_GET['token'] ?? ''));

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit('Inbjudningslänken kunde inte hittas.');
}

$database = getDatabaseConnection();
$tokenHash = hash('sha256', $token);

try {
    $database->beginTransaction();
    $statement = $database->prepare('SELECT id, group_id, created_by FROM group_invitations WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
    $statement->execute(['token_hash' => $tokenHash]);
    $invitation = $statement->fetch();

    if ($invitation === false) {
        $database->rollBack();
        http_response_code(410);
        exit('Inbjudningslänken har gått ut eller redan använts.');
    }

    $memberStatement = $database->prepare('SELECT 1 FROM group_members WHERE group_id = :group_id AND user_id = :user_id');
    $memberStatement->execute(['group_id' => $invitation['group_id'], 'user_id' => $userId]);
    $isAlreadyMember = $memberStatement->fetchColumn() !== false;

    if (!$isAlreadyMember) {
        $memberStatement = $database->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (:group_id, :user_id, \'member\')');
        $memberStatement->execute(['group_id' => $invitation['group_id'], 'user_id' => $userId]);

        $applicationStatement = $database->prepare('UPDATE group_applications SET status = \'approved\', reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE group_id = :group_id AND user_id = :user_id AND status = \'pending\'');
        $applicationStatement->execute(['reviewed_by' => $invitation['created_by'], 'group_id' => $invitation['group_id'], 'user_id' => $userId]);
    }

    $usedStatement = $database->prepare('UPDATE group_invitations SET used_at = NOW(), used_by = :used_by WHERE id = :invitation_id');
    $usedStatement->execute(['used_by' => $userId, 'invitation_id' => $invitation['id']]);
    $database->commit();

    $result = $isAlreadyMember ? 'already-member' : 'joined';
    header('Location: /groups?id=' . (int) $invitation['group_id'] . '&invite=' . $result);
    exit;
} catch (Throwable $exception) {
    if ($database->inTransaction()) $database->rollBack();
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Inbjudan kunde inte användas just nu.');
}
