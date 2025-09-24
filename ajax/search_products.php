<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Product.php';

$auth = new Auth($pdo);
$product = new Product($pdo);

// Verificar se está logado
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['products' => []]);
    exit;
}

try {
    // Usar o mesmo método que produtos.php usa
    $products = $product->searchProducts($query);
    
    // Limitar a 10 resultados para o dropdown
    $products = array_slice($products, 0, 10);
    
    // Formatar os dados
    $formattedProducts = [];
    foreach ($products as $product) {
        // Determinar a imagem correta
        $imageUrl = '';
        if (!empty($product['image_path'])) {
            $imageUrl = $product['image_path'];
        } elseif (!empty($product['image'])) {
            $imageUrl = $product['image'];
        } else {
            // Usar uma imagem placeholder padrão que existe
            $imageUrl = 'assets/images/product/product-img1.png';
        }
        
        $formattedProducts[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => substr($product['short_description'] ?? $product['description'] ?? '', 0, 100) . (strlen($product['short_description'] ?? $product['description'] ?? '') > 100 ? '...' : ''),
            'price' => $product['price'] ? 'R$ ' . number_format($product['price'], 2, ',', '.') : 'Gratuito',
            'image' => $imageUrl,
            'url' => '../produto.php?id=' . $product['id']
        ];
    }
    
    echo json_encode(['products' => $formattedProducts]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
?>
