<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Notification.php';

header('Content-Type: application/json');

$auth = new Auth($pdo);
$notification = new Notification($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'unread_count' => 0]);
    exit;
}

$user = $auth->getCurrentUser();

try {
    $unreadCount = $notification->getUnreadCount($user['id']);
    echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'unread_count' => 0, 'error' => $e->getMessage()]);
}
?>
