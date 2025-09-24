<?php
require_once '../../config/database.php';
require_once '../../includes/Auth.php';

$auth = new Auth($pdo);

// Verificar se é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$userId = $_GET['id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário não fornecido']);
    exit;
}

try {
    // Buscar dados do usuário
    $stmt = $pdo->prepare("
        SELECT u.*, 
               sp.name as plan_name,
               us.status as subscription_status,
               us.end_date as subscription_end_date
        FROM users u
        LEFT JOIN subscription_plans sp ON u.current_plan_id = sp.id
        LEFT JOIN user_subscriptions us ON u.id = us.user_id 
            AND us.status = 'active' 
            AND us.end_date > NOW()
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>
