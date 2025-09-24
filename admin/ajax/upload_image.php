<?php
require_once '../../config/database.php';
require_once '../../includes/Auth.php';

// Verificar se usuário está logado e tem permissão
$auth = new Auth($pdo);
if (!$auth->isLoggedIn() || !$auth->hasPermission('manage_system')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$response = ['success' => false, 'message' => '', 'file_path' => ''];

try {
    // Verificar se foi enviado um arquivo
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Nenhum arquivo foi enviado ou ocorreu um erro no upload');
    }

    $file = $_FILES['image'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    $fileType = $file['type'];

    // Validar tipo de arquivo
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml'];
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Tipo de arquivo não permitido. Use apenas: JPG, PNG, GIF, SVG');
    }

    // Validar tamanho (2MB máximo)
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($fileSize > $maxSize) {
        throw new Exception('Arquivo muito grande. Tamanho máximo: 2MB');
    }

    // Obter extensão
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('Extensão de arquivo não permitida');
    }

    // Criar nome único para o arquivo
    $customName = $_POST['filename'] ?? '';
    if (!empty($customName)) {
        // Remover extensão se fornecida
        $customName = pathinfo($customName, PATHINFO_FILENAME);
        $newFileName = $customName . '.' . $extension;
    } else {
        $newFileName = uniqid() . '.' . $extension;
    }

    // Criar diretório de upload se não existir
    $uploadDir = '../../uploads/config/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Erro ao criar diretório de upload');
        }
    }

    // Caminho completo do arquivo
    $filePath = $uploadDir . $newFileName;

    // Mover arquivo
    if (!move_uploaded_file($fileTmp, $filePath)) {
        throw new Exception('Erro ao salvar arquivo');
    }

    // Retornar caminho relativo para o banco
    $relativePath = '../uploads/config/' . $newFileName;

    $response = [
        'success' => true,
        'message' => 'Imagem enviada com sucesso!',
        'file_path' => $relativePath,
        'file_name' => $newFileName
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Retornar resposta em JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
