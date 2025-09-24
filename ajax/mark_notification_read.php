<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Notification.php';

header('Content-Type: application/json');

$auth = new Auth($pdo);
$notification = new Notification($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

$user = $auth->getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationId = (int)($_POST['notification_id'] ?? 0);
    
    if ($notificationId > 0) {
        if ($notification->markAsRead($notificationId, $user['id'])) {
            echo json_encode(['success' => true, 'message' => 'Notificação marcada como lida']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao marcar notificação como lida']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID da notificação inválido']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>
