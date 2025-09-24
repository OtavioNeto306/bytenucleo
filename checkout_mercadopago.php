<?php

// Define o fuso horário para São Paulo
date_default_timezone_set('America/Sao_Paulo');

require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/mercadopago_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ===== LOGS DETALHADOS PARA DEBUG =====
saveLog("=== MERCADO PAGO CHECKOUT INICIADO ===");
saveLog("Script iniciado - checkout_mercadopago.php");
saveLog("Método HTTP: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
saveLog("URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

$auth = new Auth($pdo);
$product = new Product($pdo);

// Verificar se usuário está logado
saveLog("Verificando se usuário está logado...");
if (!$auth->isLoggedIn()) {
    saveLog("Usuário não logado - redirecionando para login.php");
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

saveLog("Usuário logado com sucesso");
saveLog("SESSION user_id: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO'));

// Obter ID do produto e método de pagamento
$productId = $_GET['id'] ?? null;
$paymentMethod = $_GET['method'] ?? 'all';

saveLog("Produto ID: $productId");
saveLog("Método de pagamento: $paymentMethod");

if (!$productId) {
    saveLog("ID do produto não fornecido - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

// Obter dados do produto
saveLog("Buscando dados do produto...");
$productData = $product->getProductById($productId);

if (!$productData) {
    saveLog("Produto não encontrado - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

saveLog("Produto encontrado: " . $productData['name']);

// Verificar se produto está disponível para venda individual
if (!$productData['individual_sale']) {
    saveLog("Produto não disponível para venda individual - redirecionando para produtos.php");
    header('Location: produtos.php');
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
    saveLog("Mercado Pago não configurado - redirecionando para checkout.php");
    header('Location: checkout.php?id=' . $productId . '&error=mercadopago_disabled');
    exit;
}

// Criar preferência no Mercado Pago
saveLog("Criando preferência no Mercado Pago...");

$preferenceData = [
    'items' => [
        [
            'id' => $productId,
            'title' => $productData['name'],
            'description' => $productData['short_description'],
            'quantity' => 1,
            'unit_price' => floatval($productData['individual_price']),
            'currency_id' => 'BRL'
        ]
    ],
    'payer' => [
        'name' => $userData['name'],
        'email' => $userData['email']
    ],
    'payment_methods' => [
        // Não excluir nenhum método - deixar o cliente escolher no Mercado Pago
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
    'external_reference' => "PROD_{$productId}_USER_{$userId}_" . time(),
    'notification_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/webhook.php',
    'statement_descriptor' => 'PLWMEMBERS'
];

saveLog("Dados da preferência: " . json_encode($preferenceData));

// Fazer requisição para o Mercado Pago
$url = $mercadopagoSandbox === '1' 
    ? 'https://api.mercadopago.com/sandbox/checkout/preferences'
    : 'https://api.mercadopago.com/checkout/preferences';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preferenceData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $mercadopagoAccessToken,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

saveLog("Resposta do Mercado Pago - HTTP Code: $httpCode");
saveLog("Resposta: $response");
if ($error) {
    saveLog("Erro cURL: $error");
}

if ($httpCode !== 201) {
    saveLog("Erro ao criar preferência - redirecionando para failure.php");
    header('Location: failure.php?error=preference_creation_failed');
    exit;
}

$preference = json_decode($response, true);

if (!$preference || !isset($preference['id'])) {
    saveLog("Resposta inválida do Mercado Pago - redirecionando para failure.php");
    header('Location: failure.php?error=invalid_response');
    exit;
}

saveLog("Preferência criada com sucesso - ID: " . $preference['id']);

// Salvar dados da transação no banco
saveLog("Salvando dados da transação no banco...");
$stmt = $pdo->prepare("INSERT INTO product_purchases (user_id, product_id, transaction_id, amount, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
$stmt->execute([
    $userId,
    $productId,
    $preference['id'], // Salvar preference_id como transaction_id
    $productData['individual_price'],
    'all' // Sempre 'all' pois o cliente escolhe no Mercado Pago
]);

$purchaseId = $pdo->lastInsertId();
saveLog("Transação salva no banco - ID: $purchaseId");

// Redirecionar para o Mercado Pago
$checkoutUrl = $mercadopagoSandbox === '1' 
    ? $preference['sandbox_init_point']
    : $preference['init_point'];

saveLog("Redirecionando para: $checkoutUrl");
header('Location: ' . $checkoutUrl);
exit;
?>
