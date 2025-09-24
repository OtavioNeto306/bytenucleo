<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';

$auth = new Auth($pdo);

// Verificar se usuário está logado e tem permissão
if (!$auth->isLoggedIn() || !$auth->hasPermission('manage_products')) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar se foi enviado um arquivo
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo enviado ou erro no upload']);
    exit;
}

$file = $_FILES['image'];
$product_id = $_POST['product_id'] ?? null;

// Validar tipo de arquivo
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$file_type = mime_content_type($file['tmp_name']);

if (!in_array($file_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de arquivo não permitido. Use apenas JPG, PNG, GIF ou WebP']);
    exit;
}

// Validar tamanho (máximo 5MB)
$max_size = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'Arquivo muito grande. Máximo 5MB']);
    exit;
}

// Criar diretório se não existir
$upload_dir = '../uploads/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Gerar nome único para o arquivo
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Mover arquivo
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao salvar arquivo']);
    exit;
}

// Se foi fornecido um product_id, atualizar no banco
if ($product_id) {
    try {
        $stmt = $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?");
        $relative_path = 'uploads/products/' . $filename;
        $stmt->execute([$relative_path, $product_id]);
        
        echo json_encode([
            'success' => true,
            'image_path' => $relative_path,
            'message' => 'Imagem atualizada com sucesso!'
        ]);
    } catch (Exception $e) {
        // Se der erro no banco, deletar o arquivo
        unlink($filepath);
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar no banco de dados']);
    }
} else {
    // Apenas retornar o caminho da imagem
    echo json_encode([
        'success' => true,
        'image_path' => 'uploads/products/' . $filename,
        'message' => 'Imagem enviada com sucesso!'
    ]);
}
?>
