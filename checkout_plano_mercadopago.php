<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Subscription.php';
require_once 'includes/Payment.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/mercadopago_plano_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ===== LOGS DETALHADOS PARA DEBUG =====
saveLog("=== MERCADO PAGO PLANO CHECKOUT INICIADO ===");
saveLog("Script iniciado - checkout_plano_mercadopago.php");
saveLog("Método HTTP: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
saveLog("URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

$auth = new Auth($pdo);
$subscription = new Subscription($pdo);
$payment = new Payment($pdo);

// Verificar se usuário está logado
saveLog("Verificando se usuário está logado...");
if (!$auth->isLoggedIn()) {
    saveLog("Usuário não logado - redirecionando para login.php");
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

saveLog("Usuário logado com sucesso");
saveLog("SESSION user_id: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO'));

// Obter ID do plano
$planId = $_GET['plan'] ?? null;

saveLog("Plano ID: $planId");

if (!$planId) {
    saveLog("ID do plano não fornecido - redirecionando para planos.php");
    header('Location: planos.php');
    exit;
}

// Obter dados do plano
saveLog("Buscando dados do plano...");
$stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
$stmt->execute([$planId]);
$planData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$planData) {
    saveLog("Plano não encontrado - redirecionando para planos.php");
    header('Location: planos.php');
    exit;
}

saveLog("Plano encontrado: " . $planData['name']);

// Verificar se plano está ativo
if ($planData['status'] !== 'active') {
    saveLog("Plano não está ativo - redirecionando para planos.php");
    header('Location: planos.php');
    exit;
}

// Obter dados do usuário
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData) {
    saveLog("Dados do usuário não encontrados - redirecionando para login.php");
    header('Location: login.php');
    exit;
}

saveLog("Dados do usuário encontrados: " . $userData['name']);

// Buscar configurações do Mercado Pago
saveLog("Buscando configurações do Mercado Pago...");
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('mercadopago_enabled', 'mercadopago_public_key', 'mercadopago_access_token', 'mercadopago_sandbox')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Converter para array associativo simples
$settingsArray = [];
foreach ($settings as $setting) {
    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
}

$mercadopagoEnabled = $settingsArray['mercadopago_enabled'] ?? '0';
$mercadopagoPublicKey = $settingsArray['mercadopago_public_key'] ?? '';
$mercadopagoAccessToken = $settingsArray['mercadopago_access_token'] ?? '';
$mercadopagoSandbox = $settingsArray['mercadopago_sandbox'] ?? '1';

saveLog("Mercado Pago habilitado: $mercadopagoEnabled");
saveLog("Sandbox mode: $mercadopagoSandbox");

if ($mercadopagoEnabled !== '1' || empty($mercadopagoAccessToken)) {
    saveLog("Mercado Pago não habilitado ou token não configurado - redirecionando para pagamento-plano.php");
    header('Location: pagamento-plano.php?plan=' . $planId . '&error=mercadopago_disabled');
    exit;
}

// Criar preferência do Mercado Pago
saveLog("Criando preferência do Mercado Pago...");

$preferenceData = [
    'items' => [
        [
            'title' => $planData['name'],
            'description' => $planData['description'],
            'quantity' => 1,
            'unit_price' => (float)$planData['price'],
            'currency_id' => 'BRL'
        ]
    ],
    'payer' => [
        'name' => $userData['name'],
        'email' => $userData['email']
    ],
    'payment_methods' => [
        'excluded_payment_types' => [],
        'excluded_payment_methods' => [],
        'installments' => 12
    ],
    'back_urls' => [
        'success' => 'https://' . $_SERVER['HTTP_HOST'] . '/success.php',
        'failure' => 'https://' . $_SERVER['HTTP_HOST'] . '/failure.php',
        'pending' => 'https://' . $_SERVER['HTTP_HOST'] . '/pending.php'
    ],
    'auto_return' => 'approved',
    'external_reference' => 'plano_' . $planId . '_' . $userId . '_' . time(),
    'notification_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/webhook.php',
    'statement_descriptor' => 'PLANO ' . strtoupper(substr($planData['name'], 0, 10))
];

saveLog("Dados da preferência: " . json_encode($preferenceData));

// Fazer requisição para o Mercado Pago
$url = 'https://api.mercadopago.com/checkout/preferences';
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $mercadopagoAccessToken
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preferenceData));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

saveLog("Resposta HTTP: $httpCode");
saveLog("Resposta: $response");

if ($curlError) {
    saveLog("Erro cURL: $curlError");
    header('Location: pagamento-plano.php?plan=' . $planId . '&error=mercadopago_error');
    exit;
}

if ($httpCode !== 200 && $httpCode !== 201) {
    saveLog("Erro na API do Mercado Pago - HTTP $httpCode");
    header('Location: pagamento-plano.php?plan=' . $planId . '&error=mercadopago_error');
    exit;
}

$preference = json_decode($response, true);

if (!$preference || !isset($preference['id'])) {
    saveLog("Resposta inválida do Mercado Pago");
    header('Location: pagamento-plano.php?plan=' . $planId . '&error=mercadopago_error');
    exit;
}

saveLog("Preferência criada com sucesso: " . $preference['id']);

// Criar pedido no sistema
saveLog("Criando pedido no sistema...");
try {
    $orderId = $payment->createOrder(
        $userId,
        'subscription',
        $planData['price'],
        'card', // Mercado Pago (usando 'card' como representativo)
        [
            [
                'type' => 'subscription_plan',
                'id' => $planId,
                'name' => $planData['name'],
                'price' => $planData['price'],
                'quantity' => 1
            ]
        ]
    );
    
    saveLog("Pedido criado com sucesso - ID: $orderId");
    
    // Atualizar pedido com transaction_id
    $stmt = $pdo->prepare("UPDATE orders SET payment_proof_path = ? WHERE id = ?");
    $stmt->execute([$preference['id'], $orderId]);
    
    saveLog("Pedido atualizado com transaction_id: " . $preference['id']);
    
} catch (Exception $e) {
    saveLog("Erro ao criar pedido: " . $e->getMessage());
    header('Location: pagamento-plano.php?plan=' . $planId . '&error=order_error');
    exit;
}

// Redirecionar para o Mercado Pago
$checkoutUrl = $preference['init_point'];
saveLog("Redirecionando para: $checkoutUrl");

header('Location: ' . $checkoutUrl);
exit;
?>
