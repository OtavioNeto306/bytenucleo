<?php
header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/Payment.php';

$auth = new Auth($pdo);
$payment = new Payment($pdo);

// Verificar se é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$status = $input['status'] ?? null;

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'ID e status são obrigatórios']);
    exit;
}

try {
    $result = $payment->updateProductPurchaseStatus($id, $status);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
