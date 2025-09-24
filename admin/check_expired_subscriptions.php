<?php
/**
 * Endpoint para verificar assinaturas expiradas via web
 * Acesso apenas para administradores
 */

require_once '../config/database.php';
require_once '../includes/Auth.php';

$auth = new Auth($pdo);

// Verificar se é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// Incluir o script principal
require_once '../check_expired_subscriptions.php';
?>
