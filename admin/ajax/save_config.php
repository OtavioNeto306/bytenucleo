<?php
require_once '../../config/database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/SiteConfig.php';

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

$siteConfig = new SiteConfig($pdo);
$response = ['success' => false, 'message' => ''];

try {
    // Obter todas as configurações do grupo atual
    $group = $_POST['group'] ?? 'general';
    $allSettings = $siteConfig->getByGroup($group);
    
    // Processar cada configuração do grupo
    foreach ($allSettings as $setting) {
        $key = $setting['setting_key'];
        $type = $setting['setting_type'];
        
        // Para checkboxes, se não estiver no POST, significa que está desmarcado
        if ($type === 'checkbox') {
            $value = isset($_POST[$key]) ? '1' : '0';
        } else {
            $value = $_POST[$key] ?? $setting['setting_value'];
        }
        
        // Validar valor baseado no tipo
        $validatedValue = validateSettingValue($value, $type);
        
        if ($validatedValue !== false) {
            $siteConfig->set($key, $validatedValue);
        } else {
            throw new Exception("Valor inválido para a configuração: {$setting['setting_label']}");
        }
    }
    
    $response = [
        'success' => true, 
        'message' => 'Configurações salvas com sucesso!'
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

/**
 * Validar valor baseado no tipo da configuração
 */
function validateSettingValue($value, $type) {
    $value = trim($value);
    
    switch ($type) {
        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : false;
            
        case 'url':
            return filter_var($value, FILTER_VALIDATE_URL) ? $value : false;
            
        case 'image':
            // Validar se é um caminho válido de imagem
            if (empty($value)) return $value;
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'ico', 'svg'];
            $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
            return in_array($extension, $allowedExtensions) ? $value : false;
            
        case 'textarea':
        case 'text':
        default:
            return $value;
    }
}
?>
