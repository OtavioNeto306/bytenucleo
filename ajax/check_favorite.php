<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';

header('Content-Type: application/json');

// Verificar se usuário está logado
$auth = new Auth($pdo);
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

$product_id = (int)($_GET['product_id'] ?? 0);

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do produto inválido']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT id FROM product_favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $is_favorited = $stmt->fetch() !== false;

    echo json_encode([
        'success' => true,
        'favorited' => $is_favorited
    ]);

} catch (PDOException $e) {
    error_log("Erro ao verificar favorito: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
