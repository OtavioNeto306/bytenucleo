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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$product_id = (int)($input['product_id'] ?? 0);

if (empty($action) || !in_array($action, ['add', 'remove', 'toggle'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do produto inválido']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Verificar se o produto existe
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        exit;
    }

    // Verificar se já está favoritado
    $stmt = $pdo->prepare("SELECT id FROM product_favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $is_favorited = $stmt->fetch() !== false;

    $result = ['success' => true];

    switch ($action) {
        case 'add':
            if (!$is_favorited) {
                $stmt = $pdo->prepare("INSERT INTO product_favorites (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $product_id]);
                $result['favorited'] = true;
                $result['message'] = 'Produto adicionado aos favoritos';
            } else {
                $result['favorited'] = true;
                $result['message'] = 'Produto já está nos favoritos';
            }
            break;

        case 'remove':
            if ($is_favorited) {
                $stmt = $pdo->prepare("DELETE FROM product_favorites WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $result['favorited'] = false;
                $result['message'] = 'Produto removido dos favoritos';
            } else {
                $result['favorited'] = false;
                $result['message'] = 'Produto não estava nos favoritos';
            }
            break;

        case 'toggle':
            if ($is_favorited) {
                $stmt = $pdo->prepare("DELETE FROM product_favorites WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $result['favorited'] = false;
                $result['message'] = 'Produto removido dos favoritos';
            } else {
                $stmt = $pdo->prepare("INSERT INTO product_favorites (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $product_id]);
                $result['favorited'] = true;
                $result['message'] = 'Produto adicionado aos favoritos';
            }
            break;
    }

    echo json_encode($result);

} catch (PDOException $e) {
    error_log("Erro ao gerenciar favorito: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
