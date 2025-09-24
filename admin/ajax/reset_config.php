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
    $group = $_POST['group'] ?? '';
    
    if (empty($group)) {
        throw new Exception('Grupo não especificado');
    }

    // Valores padrão por grupo
    $defaultValues = [
        'general' => [
            'site_name' => 'Área de Membros',
            'site_description' => 'Sua área de membros com conteúdo exclusivo',
            'footer_text' => '© 2024 Área de Membros. Todos os direitos reservados.'
        ],
        'branding' => [
                    'site_logo_light' => '../assets/images/logo.png',
        'site_logo_dark' => '../assets/images/logo-light.png',
        'site_logo_icon' => '../assets/images/logo-icon.png',
        'site_favicon' => '../assets/images/favicon.png'
        ],
        'contact' => [
            'contact_email' => 'contato@areademembros.com',
            'contact_phone' => '+55 (11) 99999-9999'
        ],
        'social' => [
            'social_facebook' => 'https://facebook.com/',
            'social_twitter' => 'https://twitter.com/',
            'social_instagram' => 'https://instagram.com/',
            'social_linkedin' => 'https://linkedin.com/'
        ],
        'system' => [
            'maintenance_mode' => '0',
            'maintenance_message' => 'Site em manutenção. Volte em breve!'
        ]
    ];

    // Verificar se o grupo existe
    if (!isset($defaultValues[$group])) {
        throw new Exception('Grupo de configurações inválido');
    }

    // Resetar configurações do grupo
    foreach ($defaultValues[$group] as $key => $value) {
        $siteConfig->set($key, $value);
    }

    $response = [
        'success' => true,
        'message' => 'Configurações do grupo "' . ucfirst($group) . '" restauradas com sucesso!'
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
