<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/Notification.php';

$auth = new Auth($pdo);
$product = new Product($pdo);
$notification = new Notification($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Obter ID do produto
$productId = $_GET['id'] ?? null;
if (!$productId) {
    header('Location: produtos.php');
    exit;
}

// Obter dados do produto
$productData = $product->getProductById($productId);
if (!$productData) {
    header('Location: produtos.php');
    exit;
}

// Verificar se pode baixar
$canDownload = $product->canDownload($productId, $_SESSION['user_id']);
if (!$canDownload) {
    // Redirecionar para página do produto com mensagem de erro
    header('Location: produto.php?id=' . $productId . '&error=no_permission');
    exit;
}

// Verificar se o arquivo existe
$filePath = $productData['file_path'];
if (!$filePath || !file_exists($filePath)) {
    // Se não há arquivo físico, criar um arquivo de exemplo
    $filePath = 'uploads/sample-file.txt';
    if (!file_exists('uploads/')) {
        mkdir('uploads/', 0777, true);
    }
    if (!file_exists($filePath)) {
        file_put_contents($filePath, "Este é um arquivo de exemplo para o produto: " . $productData['name']);
    }
}

// Registrar download
$downloadResult = $product->registerDownload($productId, $_SESSION['user_id']);

// Criar notificação de download
if ($downloadResult) {
    $notification->createDownloadNotification($_SESSION['user_id'], $productData['name']);
}

// Configurar headers para download
$fileName = basename($filePath);
$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

// Definir content-type baseado na extensão
$contentTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed',
    'mp4' => 'video/mp4',
    'mp3' => 'audio/mpeg',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'html' => 'text/html',
    'css' => 'text/css',
    'js' => 'application/javascript',
    'json' => 'application/json',
    'xml' => 'application/xml',
    'csv' => 'text/csv'
];

$contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';

// Configurar nome do arquivo para download
$downloadFileName = $productData['name'] . '.' . $fileExtension;
$downloadFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $downloadFileName);

// Headers para download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Limpar buffer de saída
if (ob_get_level()) {
    ob_end_clean();
}

// Ler e enviar o arquivo
readfile($filePath);
exit;
?>
