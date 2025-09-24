<?php
// USAR UTC para corresponder ao banco de dados (que salva em UTC)
date_default_timezone_set('UTC');

require_once 'config/database.php';
require_once 'includes/Notification.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/webhook_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

saveLog("=== WEBHOOK RECEBIDO ===");
saveLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
saveLog("Request URI: " . $_SERVER['REQUEST_URI']);

// Instanciar classe de notificações
$notification = new Notification($pdo);

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    saveLog("Método não permitido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    exit;
}

// Obter dados do webhook
$input = file_get_contents('php://input');
$data = json_decode($input, true);

saveLog("Dados do webhook: " . $input);

// ===== NOVA LÓGICA PARA RECONHECER DIFERENTES FORMATOS =====

$payment_id = null;
$webhook_type = null;

// Formato 1: {"action":"payment.updated","data":{"id":"123"},"type":"payment"}
if (isset($data['type']) && $data['type'] === 'payment' && isset($data['data']['id'])) {
    $payment_id = $data['data']['id'];
    $webhook_type = 'payment';
    saveLog("✅ Formato 1 reconhecido - Payment ID: $payment_id");
}

// Formato 2: {"resource":"123","topic":"payment"}
elseif (isset($data['topic']) && $data['topic'] === 'payment' && isset($data['resource'])) {
    $payment_id = $data['resource'];
    $webhook_type = 'payment';
    saveLog("✅ Formato 2 reconhecido - Payment ID: $payment_id");
}

// Formato 3: {"resource":"123","topic":"merchant_order"} (IGNORAR)
elseif (isset($data['topic']) && $data['topic'] === 'merchant_order') {
    saveLog("ℹ️ Webhook de merchant_order ignorado (não é pagamento)");
    http_response_code(200);
    echo "OK - merchant_order ignorado";
    exit;
}

// Se não reconheceu nenhum formato válido
if (!$payment_id || $webhook_type !== 'payment') {
    saveLog("❌ Formato de webhook não reconhecido");
    saveLog("Dados recebidos: " . print_r($data, true));
    http_response_code(400);
    exit;
}

saveLog("🎯 Processando pagamento ID: $payment_id");

// Buscar configurações do Mercado Pago
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('mercadopago_access_token', 'mercadopago_sandbox')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Converter para array associativo simples
$settingsArray = [];
foreach ($settings as $setting) {
    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
}

$mp_access_token = $settingsArray['mercadopago_access_token'] ?? '';
$mp_sandbox = $settingsArray['mercadopago_sandbox'] ?? '1';

if (empty($mp_access_token)) {
    saveLog("❌ Token do Mercado Pago não configurado");
    http_response_code(500);
    exit;
}

// Buscar detalhes do pagamento
$mp_base_url = $mp_sandbox === '1' ? 'https://api.mercadopago.com/sandbox' : 'https://api.mercadopago.com';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $mp_base_url . '/v1/payments/' . $payment_id);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $mp_access_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

saveLog("Resposta da API MP - HTTP: $http_code");
saveLog("Resposta da API MP: $response");

if ($http_code === 200) {
    $payment = json_decode($response, true);
    $status = $payment['status'] ?? 'unknown';
    $external_reference = $payment['external_reference'] ?? '';
    
    saveLog("Status do pagamento: $status");
    saveLog("Referência externa: $external_reference");

    // Extrair informações da referência externa
    if (preg_match('/plano_(\d+)_(\d+)_(\d+)/', $external_reference, $matches)) {
        // É uma compra de plano
        $plan_id = $matches[1];
        $user_id = $matches[2];
        $timestamp = $matches[3];
        
        saveLog("Plano ID: $plan_id, User ID: $user_id, Timestamp: $timestamp");
        
        // Atualizar status no banco de pedidos (orders)
        if ($status === 'approved') {
            saveLog("🔄 Tentando atualizar pedido de plano $payment_id para 'approved'");
            
            // Buscar pedido pelo preference_id (que está no payment_proof_path)
            $preference_id = $payment['order']['id'] ?? null;
            
            if ($preference_id) {
                saveLog("🔄 Tentando atualizar pelo preference_id: $preference_id");
                
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = 'approved', updated_at = NOW() 
                    WHERE payment_proof_path = ? AND user_id = ? AND order_type = 'subscription'
                ");
                $result = $stmt->execute([$preference_id, $user_id]);
                $rowsAffected = $stmt->rowCount();
                
                saveLog("🔄 Resultado da execução (preference_id): " . ($result ? 'SUCESSO' : 'FALHA'));
                saveLog("🔄 Linhas afetadas (preference_id): $rowsAffected");
                
                if ($rowsAffected > 0) {
                    saveLog("✅ Pedido de plano $preference_id marcado como aprovado no banco");
                    
                    // Ativar assinatura automaticamente
                    require_once 'includes/Payment.php';
                    $paymentClass = new Payment($pdo);
                    
                    // Buscar o pedido atualizado
                    $stmt = $pdo->prepare("SELECT id FROM orders WHERE payment_proof_path = ? AND user_id = ? AND order_type = 'subscription'");
                    $stmt->execute([$preference_id, $user_id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($order) {
                        // Usar método privado via reflexão ou criar método público
                        $reflection = new ReflectionClass($paymentClass);
                        $method = $reflection->getMethod('activateSubscriptionFromOrder');
                        $method->setAccessible(true);
                        $method->invoke($paymentClass, $order['id']);
                        
                        saveLog("✅ Assinatura ativada automaticamente para pedido {$order['id']}");
                    }
                } else {
                    saveLog("❌ Nenhuma linha foi atualizada - tentando buscar por external_reference");
                    
                    // Se não encontrou pelo preference_id, buscar pelo external_reference
                    saveLog("🔄 Tentando buscar pelo external_reference: $external_reference");
                    
                    // Extrair timestamp do external_reference
                    if (preg_match('/plano_(\d+)_(\d+)_(\d+)/', $external_reference, $matches)) {
                        $plan_id_ref = $matches[1];
                        $user_id_ref = $matches[2];
                        $timestamp_ref = $matches[3];
                        
                        saveLog("🔄 Buscando por user_id=$user_id_ref, plan_id=$plan_id_ref, timestamp=$timestamp_ref");
                        
                        // Buscar registros criados próximo ao timestamp
                        $original_timezone = date_default_timezone_get();
                        date_default_timezone_set('America/Sao_Paulo');
                        
                        $start_time = date('Y-m-d H:i:s', $timestamp_ref - 300); // 5 minutos antes
                        $end_time = date('Y-m-d H:i:s', $timestamp_ref + 300);   // 5 minutos depois
                        
                        date_default_timezone_set($original_timezone);
                        
                        saveLog("🔄 Buscando registros entre $start_time e $end_time (America/Sao_Paulo)");
                        
                        $stmt = $pdo->prepare("
                            UPDATE orders 
                            SET payment_status = 'approved', updated_at = NOW() 
                            WHERE user_id = ? AND order_type = 'subscription'
                            AND created_at BETWEEN ? AND ?
                            AND payment_status = 'pending'
                            ORDER BY created_at DESC
                            LIMIT 1
                        ");
                        $result = $stmt->execute([$user_id_ref, $start_time, $end_time]);
                        $rowsAffected = $stmt->rowCount();
                        
                        saveLog("🔄 Resultado da execução (external_reference): " . ($result ? 'SUCESSO' : 'FALHA'));
                        saveLog("🔄 Linhas afetadas (external_reference): $rowsAffected");
                        
                        if ($rowsAffected > 0) {
                            saveLog("✅ Pedido de plano atualizado usando external_reference");
                            
                            // Ativar assinatura automaticamente
                            $stmt = $pdo->prepare("
                                SELECT id FROM orders 
                                WHERE user_id = ? AND order_type = 'subscription'
                                AND created_at BETWEEN ? AND ?
                                ORDER BY created_at DESC
                                LIMIT 1
                            ");
                            $stmt->execute([$user_id_ref, $start_time, $end_time]);
                            $order = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($order) {
                                $reflection = new ReflectionClass($paymentClass);
                                $method = $reflection->getMethod('activateSubscriptionFromOrder');
                                $method->setAccessible(true);
                                $method->invoke($paymentClass, $order['id']);
                                
                                saveLog("✅ Assinatura ativada automaticamente para pedido {$order['id']}");
                            }
                        } else {
                            saveLog("❌ Nenhum pedido de plano encontrado pelo external_reference");
                        }
                    }
                }
            } else {
                saveLog("❌ Preference ID não encontrado no webhook");
            }
            
        } elseif ($status === 'rejected' || $status === 'cancelled') {
            $preference_id = $payment['order']['id'] ?? null;
            
            if ($preference_id) {
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = 'rejected', updated_at = NOW() 
                    WHERE payment_proof_path = ? AND user_id = ? AND order_type = 'subscription'
                ");
                $stmt->execute([$preference_id, $user_id]);
                
                saveLog("❌ Pedido de plano $preference_id marcado como rejeitado no banco");
            }
        } else {
            saveLog("ℹ️ Status '$status' não requer atualização no banco para plano");
        }
        
    } elseif (preg_match('/PROD_(\d+)_USER_(\d+)/', $external_reference, $matches)) {
        $product_id = $matches[1];
        $user_id = $matches[2];
        
        saveLog("Product ID: $product_id, User ID: $user_id");

        // Atualizar status no banco usando a estrutura atual
        if ($status === 'approved') {
            saveLog("🔄 Tentando atualizar pagamento $payment_id para 'completed'");
            saveLog("🔄 Parâmetros: transaction_id=$payment_id, user_id=$user_id, product_id=$product_id");
            
            // Buscar pelo preference_id (que está salvo como transaction_id no banco)
            $preference_id = $payment['order']['id'] ?? null;
            
            if ($preference_id) {
                saveLog("🔄 Tentando atualizar pelo preference_id: $preference_id");
                
                $stmt = $pdo->prepare("
                    UPDATE product_purchases 
                    SET status = 'completed', updated_at = NOW() 
                    WHERE transaction_id = ? AND user_id = ? AND product_id = ?
                ");
                $result = $stmt->execute([$preference_id, $user_id, $product_id]);
                $rowsAffected = $stmt->rowCount();
                
                saveLog("🔄 Resultado da execução (preference_id): " . ($result ? 'SUCESSO' : 'FALHA'));
                saveLog("🔄 Linhas afetadas (preference_id): $rowsAffected");
                
                if ($rowsAffected > 0) {
                    saveLog("✅ Pagamento $preference_id marcado como completo no banco");
                } else {
                    saveLog("❌ Nenhuma linha foi atualizada - tentando buscar por external_reference");
                    
                    // Se não encontrou pelo preference_id, buscar pelo external_reference
                    saveLog("🔄 Tentando buscar pelo external_reference: $external_reference");
                    
                    // Extrair timestamp do external_reference
                    if (preg_match('/PROD_(\d+)_USER_(\d+)_(\d+)/', $external_reference, $matches)) {
                        $product_id_ref = $matches[1];
                        $user_id_ref = $matches[2];
                        $timestamp_ref = $matches[3];
                        
                        saveLog("🔄 Buscando por user_id=$user_id_ref, product_id=$product_id_ref, timestamp=$timestamp_ref");
                        
                        // Buscar registros criados próximo ao timestamp
                        $start_time = date('Y-m-d H:i:s', $timestamp_ref - 300); // 5 minutos antes
                        $end_time = date('Y-m-d H:i:s', $timestamp_ref + 300);   // 5 minutos depois
                        
                        saveLog("🔄 Buscando registros entre $start_time e $end_time (America/Sao_Paulo)");
                        
                        $stmt = $pdo->prepare("
                            UPDATE product_purchases 
                            SET status = 'completed', updated_at = NOW() 
                            WHERE user_id = ? AND product_id = ? 
                            AND created_at BETWEEN ? AND ?
                            AND status = 'pending'
                            ORDER BY created_at DESC
                            LIMIT 1
                        ");
                        $result = $stmt->execute([$user_id_ref, $product_id_ref, $start_time, $end_time]);
                        $rowsAffected = $stmt->rowCount();
                        
                        // Se não encontrou com America/Sao_Paulo, tentar com UTC
                        if ($rowsAffected == 0) {
                            date_default_timezone_set('UTC');
                            $start_time_utc = date('Y-m-d H:i:s', $timestamp_ref - 300);
                            $end_time_utc = date('Y-m-d H:i:s', $timestamp_ref + 300);
                            date_default_timezone_set('America/Sao_Paulo'); // Voltar para SP
                            
                            saveLog("🔄 Tentando com UTC: $start_time_utc a $end_time_utc");
                            
                            $result = $stmt->execute([$user_id_ref, $product_id_ref, $start_time_utc, $end_time_utc]);
                            $rowsAffected = $stmt->rowCount();
                        }
                        
                        // Se não encontrou, tentar com UTC (onde os registros podem estar)
                        if ($rowsAffected == 0) {
                            date_default_timezone_set('UTC');
                            $start_time_utc = date('Y-m-d H:i:s', $timestamp_ref - 300);
                            $end_time_utc = date('Y-m-d H:i:s', $timestamp_ref + 300);
                            date_default_timezone_set('America/Sao_Paulo');
                            
                            saveLog("🔄 Tentando com UTC: $start_time_utc a $end_time_utc");
                            
                            $result = $stmt->execute([$user_id_ref, $product_id_ref, $start_time_utc, $end_time_utc]);
                            $rowsAffected = $stmt->rowCount();
                        }
                        
                        saveLog("🔄 Resultado da execução (external_reference): " . ($result ? 'SUCESSO' : 'FALHA'));
                        saveLog("🔄 Linhas afetadas (external_reference): $rowsAffected");
                        
                        if ($rowsAffected > 0) {
                            saveLog("✅ Pagamento atualizado usando external_reference");
                            
                            // Criar notificação para o usuário
                            $stmt = $pdo->prepare("
                                SELECT pp.*, u.name as user_name, p.name as product_name 
                                FROM product_purchases pp
                                JOIN users u ON pp.user_id = u.id
                                JOIN products p ON pp.product_id = p.id
                                WHERE pp.user_id = ? AND pp.product_id = ? 
                                AND pp.created_at BETWEEN ? AND ?
                                ORDER BY pp.created_at DESC
                                LIMIT 1
                            ");
                            $stmt->execute([$user_id_ref, $product_id_ref, $start_time, $end_time]);
                            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($purchase) {
                                $notification->createProductPurchaseApprovedNotification(
                                    $purchase['user_id'],
                                    $purchase['product_name'],
                                    number_format($purchase['amount'], 2, ',', '.')
                                );
                                saveLog("📧 Notificação de compra aprovada criada para usuário {$purchase['user_id']}");
                                
                                // Criar notificação para admins
                                $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
                                $adminStmt->execute();
                                $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                if (!empty($adminIds)) {
                                    $notification->createAdminProductPurchaseNotification(
                                        $adminIds,
                                        $purchase['user_name'],
                                        $purchase['product_name'],
                                        number_format($purchase['amount'], 2, ',', '.')
                                    );
                                    saveLog("📧 Notificação de nova compra criada para " . count($adminIds) . " admins");
                                }
                            }
                        } else {
                            saveLog("❌ Nenhuma linha foi atualizada mesmo com external_reference");
                            
                            // Verificar se o registro existe
                            $checkStmt = $pdo->prepare("SELECT * FROM product_purchases WHERE user_id = ? AND product_id = ? ORDER BY created_at DESC LIMIT 3");
                            $checkStmt->execute([$user_id, $product_id]);
                            $existingRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            saveLog("🔍 Registros encontrados para user_id=$user_id, product_id=$product_id:");
                            foreach ($existingRecords as $record) {
                                saveLog("  - ID: {$record['id']}, Transaction: {$record['transaction_id']}, Status: {$record['status']}, Data: {$record['created_at']}");
                            }
                        }
                    }
                }
            } else {
                saveLog("❌ Preference ID não encontrado na resposta do Mercado Pago");
            }
            
        } elseif ($status === 'rejected' || $status === 'cancelled') {
            $stmt = $pdo->prepare("
                UPDATE product_purchases 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE transaction_id = ? AND user_id = ? AND product_id = ?
            ");
            $stmt->execute([$payment_id, $user_id, $product_id]);
            
            saveLog("❌ Pagamento $payment_id marcado como cancelado no banco");
            
            // Criar notificação para o usuário
            $stmt = $pdo->prepare("
                SELECT pp.*, u.name as user_name, p.name as product_name 
                FROM product_purchases pp
                JOIN users u ON pp.user_id = u.id
                JOIN products p ON pp.product_id = p.id
                WHERE pp.transaction_id = ? AND pp.user_id = ? AND pp.product_id = ?
            ");
            $stmt->execute([$payment_id, $user_id, $product_id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($purchase) {
                $notification->createProductPurchaseCancelledNotification(
                    $purchase['user_id'],
                    $purchase['product_name'],
                    number_format($purchase['amount'], 2, ',', '.')
                );
                saveLog("📧 Notificação de compra cancelada criada para usuário {$purchase['user_id']}");
            }
        } else {
            saveLog("ℹ️ Status '$status' não requer atualização no banco");
        }
    } else {
        saveLog("⚠️ Referência externa inválida: $external_reference");
    }
} else {
    saveLog("❌ Erro ao buscar detalhes do pagamento: HTTP $http_code");
}

// Responder com sucesso
http_response_code(200);
echo "OK";
?>

require_once 'includes/Notification.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/webhook_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

saveLog("=== WEBHOOK RECEBIDO ===");
saveLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
saveLog("Request URI: " . $_SERVER['REQUEST_URI']);

// Instanciar classe de notificações
$notification = new Notification($pdo);

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    saveLog("Método não permitido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    exit;
}

// Obter dados do webhook
$input = file_get_contents('php://input');
$data = json_decode($input, true);

saveLog("Dados do webhook: " . $input);

// ===== NOVA LÓGICA PARA RECONHECER DIFERENTES FORMATOS =====

$payment_id = null;
$webhook_type = null;

// Formato 1: {"action":"payment.updated","data":{"id":"123"},"type":"payment"}
if (isset($data['type']) && $data['type'] === 'payment' && isset($data['data']['id'])) {
    $payment_id = $data['data']['id'];
    $webhook_type = 'payment';
    saveLog("✅ Formato 1 reconhecido - Payment ID: $payment_id");
}

// Formato 2: {"resource":"123","topic":"payment"}
elseif (isset($data['topic']) && $data['topic'] === 'payment' && isset($data['resource'])) {
    $payment_id = $data['resource'];
    $webhook_type = 'payment';
    saveLog("✅ Formato 2 reconhecido - Payment ID: $payment_id");
}

// Formato 3: {"resource":"123","topic":"merchant_order"} (IGNORAR)
elseif (isset($data['topic']) && $data['topic'] === 'merchant_order') {
    saveLog("ℹ️ Webhook de merchant_order ignorado (não é pagamento)");
    http_response_code(200);
    echo "OK - merchant_order ignorado";
    exit;
}

// Se não reconheceu nenhum formato válido
if (!$payment_id || $webhook_type !== 'payment') {
    saveLog("❌ Formato de webhook não reconhecido");
    saveLog("Dados recebidos: " . print_r($data, true));
    http_response_code(400);
    exit;
}

saveLog("🎯 Processando pagamento ID: $payment_id");

// Buscar configurações do Mercado Pago
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('mercadopago_access_token', 'mercadopago_sandbox')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Converter para array associativo simples
$settingsArray = [];
foreach ($settings as $setting) {
    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
}

$mp_access_token = $settingsArray['mercadopago_access_token'] ?? '';
$mp_sandbox = $settingsArray['mercadopago_sandbox'] ?? '1';

if (empty($mp_access_token)) {
    saveLog("❌ Token do Mercado Pago não configurado");
    http_response_code(500);
    exit;
}

// Buscar detalhes do pagamento
$mp_base_url = $mp_sandbox === '1' ? 'https://api.mercadopago.com/sandbox' : 'https://api.mercadopago.com';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $mp_base_url . '/v1/payments/' . $payment_id);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $mp_access_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

saveLog("Resposta da API MP - HTTP: $http_code");
saveLog("Resposta da API MP: $response");

if ($http_code === 200) {
    $payment = json_decode($response, true);
    $status = $payment['status'] ?? 'unknown';
    $external_reference = $payment['external_reference'] ?? '';
    
    saveLog("Status do pagamento: $status");
    saveLog("Referência externa: $external_reference");

    // Extrair informações da referência externa
    if (preg_match('/plano_(\d+)_(\d+)_(\d+)/', $external_reference, $matches)) {
        // É uma compra de plano
        $plan_id = $matches[1];
        $user_id = $matches[2];
        $timestamp = $matches[3];
        
        saveLog("Plano ID: $plan_id, User ID: $user_id, Timestamp: $timestamp");
        
        // Atualizar status no banco de pedidos (orders)
        if ($status === 'approved') {
            saveLog("🔄 Tentando atualizar pedido de plano $payment_id para 'approved'");
            
            // Buscar pedido pelo preference_id (que está no payment_proof_path)
            $preference_id = $payment['order']['id'] ?? null;
            
            if ($preference_id) {
                saveLog("🔄 Tentando atualizar pelo preference_id: $preference_id");
                
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = 'approved', updated_at = NOW() 
                    WHERE payment_proof_path = ? AND user_id = ? AND order_type = 'subscription'
                ");
                $result = $stmt->execute([$preference_id, $user_id]);
                $rowsAffected = $stmt->rowCount();
                
                saveLog("🔄 Resultado da execução (preference_id): " . ($result ? 'SUCESSO' : 'FALHA'));
                saveLog("🔄 Linhas afetadas (preference_id): $rowsAffected");
                
                if ($rowsAffected > 0) {
                    saveLog("✅ Pedido de plano $preference_id marcado como aprovado no banco");
                    
                    // Ativar assinatura automaticamente
                    require_once 'includes/Payment.php';
                    $paymentClass = new Payment($pdo);
                    
                    // Buscar o pedido atualizado
                    $stmt = $pdo->prepare("SELECT id FROM orders WHERE payment_proof_path = ? AND user_id = ? AND order_type = 'subscription'");
                    $stmt->execute([$preference_id, $user_id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($order) {
                        // Usar método privado via reflexão ou criar método público
                        $reflection = new ReflectionClass($paymentClass);
                        $method = $reflection->getMethod('activateSubscriptionFromOrder');
                        $method->setAccessible(true);
                        $method->invoke($paymentClass, $order['id']);
                        
                        saveLog("✅ Assinatura ativada automaticamente para pedido {$order['id']}");
                    }
                } else {
                    saveLog("❌ Nenhuma linha foi atualizada - tentando buscar por external_reference");
                    
                    // Se não encontrou pelo preference_id, buscar pelo external_reference
                    saveLog("🔄 Tentando buscar pelo external_reference: $external_reference");
                    
                    // Extrair timestamp do external_reference
                    if (preg_match('/plano_(\d+)_(\d+)_(\d+)/', $external_reference, $matches)) {
                        $plan_id_ref = $matches[1];
                        $user_id_ref = $matches[2];
                        $timestamp_ref = $matches[3];
                        
                        saveLog("🔄 Buscando por user_id=$user_id_ref, plan_id=$plan_id_ref, timestamp=$timestamp_ref");
                        
                        // Buscar registros criados próximo ao timestamp
                        $original_timezone = date_default_timezone_get();
                        date_default_timezone_set('America/Sao_Paulo');
                        
                        $start_time = date('Y-m-d H:i:s', $timestamp_ref - 300); // 5 minutos antes
                        $end_time = date('Y-m-d H:i:s', $timestamp_ref + 300);   // 5 minutos depois
                        
                        date_default_timezone_set($original_timezone);
                        
                        saveLog("🔄 Buscando registros entre $start_time e $end_time (America/Sao_Paulo)");
                        
                        $stmt = $pdo->prepare("
                            UPDATE orders 
                            SET payment_status = 'approved', updated_at = NOW() 
                            WHERE user_id = ? AND order_type = 'subscription'
                            AND created_at BETWEEN ? AND ?
                            AND payment_status = 'pending'
                            ORDER BY created_at DESC
                            LIMIT 1
                        ");
                        $result = $stmt->execute([$user_id_ref, $start_time, $end_time]);
                        $rowsAffected = $stmt->rowCount();
                        
                        saveLog("🔄 Resultado da execução (external_reference): " . ($result ? 'SUCESSO' : 'FALHA'));
                        saveLog("🔄 Linhas afetadas (external_reference): $rowsAffected");
                        
                        if ($rowsAffected > 0) {
                            saveLog("✅ Pedido de plano atualizado usando external_reference");
                            
                            // Ativar assinatura automaticamente
                            $stmt = $pdo->prepare("
                                SELECT id FROM orders 
                                WHERE user_id = ? AND order_type = 'subscription'
                                AND created_at BETWEEN ? AND ?
                                ORDER BY created_at DESC
                                LIMIT 1
                            ");
                            $stmt->execute([$user_id_ref, $start_time, $end_time]);
                            $order = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($order) {
                                $reflection = new ReflectionClass($paymentClass);
                                $method = $reflection->getMethod('activateSubscriptionFromOrder');
                                $method->setAccessible(true);
                                $method->invoke($paymentClass, $order['id']);
                                
                                saveLog("✅ Assinatura ativada automaticamente para pedido {$order['id']}");
                            }
                        } else {
                            saveLog("❌ Nenhum pedido de plano encontrado pelo external_reference");
                        }
                    }
                }
            } else {
                saveLog("❌ Preference ID não encontrado no webhook");
            }
            
        } elseif ($status === 'rejected' || $status === 'cancelled') {
            $preference_id = $payment['order']['id'] ?? null;
            
            if ($preference_id) {
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = 'rejected', updated_at = NOW() 
                    WHERE payment_proof_path = ? AND user_id = ? AND order_type = 'subscription'
                ");
                $stmt->execute([$preference_id, $user_id]);
                
                saveLog("❌ Pedido de plano $preference_id marcado como rejeitado no banco");
            }
        } else {
            saveLog("ℹ️ Status '$status' não requer atualização no banco para plano");
        }
        
    } elseif (preg_match('/PROD_(\d+)_USER_(\d+)/', $external_reference, $matches)) {
        $product_id = $matches[1];
        $user_id = $matches[2];
        
        saveLog("Product ID: $product_id, User ID: $user_id");

        // Atualizar status no banco usando a estrutura atual
        if ($status === 'approved') {
            saveLog("🔄 Tentando atualizar pagamento $payment_id para 'completed'");
            saveLog("🔄 Parâmetros: transaction_id=$payment_id, user_id=$user_id, product_id=$product_id");
            
            // Buscar pelo preference_id (que está salvo como transaction_id no banco)
            $preference_id = $payment['order']['id'] ?? null;
            
            if ($preference_id) {
                saveLog("🔄 Tentando atualizar pelo preference_id: $preference_id");
                
                $stmt = $pdo->prepare("
                    UPDATE product_purchases 
                    SET status = 'completed', updated_at = NOW() 
                    WHERE transaction_id = ? AND user_id = ? AND product_id = ?
                ");
                $result = $stmt->execute([$preference_id, $user_id, $product_id]);
                $rowsAffected = $stmt->rowCount();
                
                saveLog("🔄 Resultado da execução (preference_id): " . ($result ? 'SUCESSO' : 'FALHA'));
                saveLog("🔄 Linhas afetadas (preference_id): $rowsAffected");
                
                if ($rowsAffected > 0) {
                    saveLog("✅ Pagamento $preference_id marcado como completo no banco");
                } else {
                    saveLog("❌ Nenhuma linha foi atualizada - tentando buscar por external_reference");
                    
                    // Se não encontrou pelo preference_id, buscar pelo external_reference
                    saveLog("🔄 Tentando buscar pelo external_reference: $external_reference");
                    
                    // Extrair timestamp do external_reference
                    if (preg_match('/PROD_(\d+)_USER_(\d+)_(\d+)/', $external_reference, $matches)) {
                        $product_id_ref = $matches[1];
                        $user_id_ref = $matches[2];
                        $timestamp_ref = $matches[3];
                        
                        saveLog("🔄 Buscando por user_id=$user_id_ref, product_id=$product_id_ref, timestamp=$timestamp_ref");
                        
                        // Buscar registros criados próximo ao timestamp
                        $start_time = date('Y-m-d H:i:s', $timestamp_ref - 300); // 5 minutos antes
                        $end_time = date('Y-m-d H:i:s', $timestamp_ref + 300);   // 5 minutos depois
                        
                        saveLog("🔄 Buscando registros entre $start_time e $end_time (America/Sao_Paulo)");
                        
                        $stmt = $pdo->prepare("
                            UPDATE product_purchases 
                            SET status = 'completed', updated_at = NOW() 
                            WHERE user_id = ? AND product_id = ? 
                            AND created_at BETWEEN ? AND ?
                            AND status = 'pending'
                            ORDER BY created_at DESC
                            LIMIT 1
                        ");
                        $result = $stmt->execute([$user_id_ref, $product_id_ref, $start_time, $end_time]);
                        $rowsAffected = $stmt->rowCount();
                        
                        // Se não encontrou com America/Sao_Paulo, tentar com UTC
                        if ($rowsAffected == 0) {
                            date_default_timezone_set('UTC');
                            $start_time_utc = date('Y-m-d H:i:s', $timestamp_ref - 300);
                            $end_time_utc = date('Y-m-d H:i:s', $timestamp_ref + 300);
                            date_default_timezone_set('America/Sao_Paulo'); // Voltar para SP
                            
                            saveLog("🔄 Tentando com UTC: $start_time_utc a $end_time_utc");
                            
                            $result = $stmt->execute([$user_id_ref, $product_id_ref, $start_time_utc, $end_time_utc]);
                            $rowsAffected = $stmt->rowCount();
                        }
                        
                        // Se não encontrou, tentar com UTC (onde os registros podem estar)
                        if ($rowsAffected == 0) {
                            date_default_timezone_set('UTC');
                            $start_time_utc = date('Y-m-d H:i:s', $timestamp_ref - 300);
                            $end_time_utc = date('Y-m-d H:i:s', $timestamp_ref + 300);
                            date_default_timezone_set('America/Sao_Paulo');
                            
                            saveLog("🔄 Tentando com UTC: $start_time_utc a $end_time_utc");
                            
                            $result = $stmt->execute([$user_id_ref, $product_id_ref, $start_time_utc, $end_time_utc]);
                            $rowsAffected = $stmt->rowCount();
                        }
                        
                        saveLog("🔄 Resultado da execução (external_reference): " . ($result ? 'SUCESSO' : 'FALHA'));
                        saveLog("🔄 Linhas afetadas (external_reference): $rowsAffected");
                        
                        if ($rowsAffected > 0) {
                            saveLog("✅ Pagamento atualizado usando external_reference");
                            
                            // Criar notificação para o usuário
                            $stmt = $pdo->prepare("
                                SELECT pp.*, u.name as user_name, p.name as product_name 
                                FROM product_purchases pp
                                JOIN users u ON pp.user_id = u.id
                                JOIN products p ON pp.product_id = p.id
                                WHERE pp.user_id = ? AND pp.product_id = ? 
                                AND pp.created_at BETWEEN ? AND ?
                                ORDER BY pp.created_at DESC
                                LIMIT 1
                            ");
                            $stmt->execute([$user_id_ref, $product_id_ref, $start_time, $end_time]);
                            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($purchase) {
                                $notification->createProductPurchaseApprovedNotification(
                                    $purchase['user_id'],
                                    $purchase['product_name'],
                                    number_format($purchase['amount'], 2, ',', '.')
                                );
                                saveLog("📧 Notificação de compra aprovada criada para usuário {$purchase['user_id']}");
                                
                                // Criar notificação para admins
                                $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
                                $adminStmt->execute();
                                $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                if (!empty($adminIds)) {
                                    $notification->createAdminProductPurchaseNotification(
                                        $adminIds,
                                        $purchase['user_name'],
                                        $purchase['product_name'],
                                        number_format($purchase['amount'], 2, ',', '.')
                                    );
                                    saveLog("📧 Notificação de nova compra criada para " . count($adminIds) . " admins");
                                }
                            }
                        } else {
                            saveLog("❌ Nenhuma linha foi atualizada mesmo com external_reference");
                            
                            // Verificar se o registro existe
                            $checkStmt = $pdo->prepare("SELECT * FROM product_purchases WHERE user_id = ? AND product_id = ? ORDER BY created_at DESC LIMIT 3");
                            $checkStmt->execute([$user_id, $product_id]);
                            $existingRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            saveLog("🔍 Registros encontrados para user_id=$user_id, product_id=$product_id:");
                            foreach ($existingRecords as $record) {
                                saveLog("  - ID: {$record['id']}, Transaction: {$record['transaction_id']}, Status: {$record['status']}, Data: {$record['created_at']}");
                            }
                        }
                    }
                }
            } else {
                saveLog("❌ Preference ID não encontrado na resposta do Mercado Pago");
            }
            
        } elseif ($status === 'rejected' || $status === 'cancelled') {
            $stmt = $pdo->prepare("
                UPDATE product_purchases 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE transaction_id = ? AND user_id = ? AND product_id = ?
            ");
            $stmt->execute([$payment_id, $user_id, $product_id]);
            
            saveLog("❌ Pagamento $payment_id marcado como cancelado no banco");
            
            // Criar notificação para o usuário
            $stmt = $pdo->prepare("
                SELECT pp.*, u.name as user_name, p.name as product_name 
                FROM product_purchases pp
                JOIN users u ON pp.user_id = u.id
                JOIN products p ON pp.product_id = p.id
                WHERE pp.transaction_id = ? AND pp.user_id = ? AND pp.product_id = ?
            ");
            $stmt->execute([$payment_id, $user_id, $product_id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($purchase) {
                $notification->createProductPurchaseCancelledNotification(
                    $purchase['user_id'],
                    $purchase['product_name'],
                    number_format($purchase['amount'], 2, ',', '.')
                );
                saveLog("📧 Notificação de compra cancelada criada para usuário {$purchase['user_id']}");
            }
        } else {
            saveLog("ℹ️ Status '$status' não requer atualização no banco");
        }
    } else {
        saveLog("⚠️ Referência externa inválida: $external_reference");
    }
} else {
    saveLog("❌ Erro ao buscar detalhes do pagamento: HTTP $http_code");
}

// Responder com sucesso
http_response_code(200);
echo "OK";
?>
