<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Config.php';
require_once 'includes/Logger.php';

$logger = new Logger('mercadopago.log');

try {
    $logger->info("=== INÍCIO DO PROCESSAMENTO DE PAGAMENTO ===");
    $logger->info("Request Method: " . $_SERVER['REQUEST_METHOD']);
    $logger->info("Request URI: " . $_SERVER['REQUEST_URI']);
    $logger->info("User Agent: " . $_SERVER['HTTP_USER_AGENT']);

    // Verificar se usuário está logado
    if (!isset($_SESSION['user_id'])) {
        $logger->error("Usuário não autenticado");
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
        exit;
    }

    $logger->info("Usuário autenticado: " . $_SESSION['user_id']);

    // Obter dados do POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $logger->info("Dados recebidos: " . $input);

    // Validar dados obrigatórios
    $product_id = $data['product_id'] ?? null;
    $user_id = $data['user_id'] ?? null;
    $amount = $data['amount'] ?? null;
    $payment_method = $data['payment_method'] ?? null;
    $customer_name = $data['customer_name'] ?? '';
    $customer_email = $data['customer_email'] ?? '';
    $customer_phone = $data['customer_phone'] ?? '';
    $customer_cpf = $data['customer_cpf'] ?? '';

    if (!$product_id || !$user_id || !$amount || !$payment_method) {
        $logger->error("Dados obrigatórios ausentes");
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios ausentes']);
        exit;
    }

    $logger->info("Dados validados - product_id: $product_id, user_id: $user_id, amount: $amount, payment_method: $payment_method");

    // Buscar dados do produto
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND individual_sale = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        $logger->error("Produto não encontrado ou não disponível para venda individual");
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
        exit;
    }

    $logger->info("Produto validado: " . json_encode($product));

    // Verificar se usuário já tem acesso
    $stmt = $pdo->prepare("SELECT * FROM product_purchases WHERE user_id = ? AND product_id = ? AND status = 'completed'");
    $stmt->execute([$user_id, $product_id]);
    $existing_purchase = $stmt->fetch();

    if ($existing_purchase) {
        $logger->info("Usuário já possui acesso ao produto");
        echo json_encode(['success' => false, 'message' => 'Você já possui este produto']);
        exit;
    }

    $logger->info("Usuário não possui acesso ao produto - pode comprar");

    // Buscar configurações do Mercado Pago
    $config = new Config($pdo);
    $mp_enabled = $config->get('mercadopago_enabled');
    $mp_public_key = $config->get('mercadopago_public_key');
    $mp_access_token = $config->get('mercadopago_access_token');
    $mp_sandbox = $config->get('mercadopago_sandbox');

    if (!$mp_enabled) {
        $logger->error("Mercado Pago não está habilitado");
        echo json_encode(['success' => false, 'message' => 'Mercado Pago não está habilitado']);
        exit;
    }

    $logger->info("Mercado Pago está habilitado");
    $logger->info("Configurações MP - Public Key: " . substr($mp_public_key, 0, 20) . "...");
    $logger->info("Configurações MP - Access Token: " . substr($mp_access_token, 0, 20) . "...");
    $logger->info("Configurações MP - Sandbox: " . ($mp_sandbox ? 'SIM' : 'NÃO'));

    $mp_base_url = $mp_sandbox ? 'https://api.mercadopago.com' : 'https://api.mercadopago.com';
    $logger->info("URL base MP: $mp_base_url");

    if ($payment_method === 'pix') {
        // Processar PIX
        $pix_data = [
            'transaction_amount' => floatval($amount),
            'description' => $product['name'],
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $customer_email,
                'first_name' => explode(' ', $customer_name)[0],
                'last_name' => count(explode(' ', $customer_name)) > 1 ? implode(' ', array_slice(explode(' ', $customer_name), 1)) : '',
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/[^0-9]/', '', $customer_cpf)
                ]
            ],
            'external_reference' => "PROD_{$product_id}_USER_{$user_id}"
        ];

        $logger->info("Dados do PIX criados: " . json_encode($pix_data));

        // Fazer requisição para criar pagamento PIX
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $mp_base_url . '/v1/payments');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pix_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $mp_access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $logger->info("Fazendo requisição para: " . $mp_base_url . '/v1/payments');

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $logger->info("Resposta HTTP: " . $http_code);
        $logger->info("Resposta completa: " . $response);

        if ($curl_error) {
            $logger->error("Erro cURL: " . $curl_error);
        }

        if ($http_code !== 201) {
            $logger->error("Erro HTTP: " . $http_code . " - Resposta: " . $response);
            echo json_encode(['success' => false, 'message' => 'Erro ao processar pagamento PIX']);
            exit;
        }

        $payment = json_decode($response, true);

        if (!$payment || !isset($payment['id'])) {
            $logger->error("Resposta inválida do Mercado Pago: " . $response);
            echo json_encode(['success' => false, 'message' => 'Resposta inválida do Mercado Pago']);
            exit;
        }

        $logger->info("Pagamento PIX criado com sucesso - ID: " . $payment['id']);

        // Salvar transação no banco
        try {
            $stmt = $pdo->prepare("
                INSERT INTO product_purchases (user_id, product_id, amount, status, transaction_id, payment_method)
                VALUES (?, ?, ?, 'pending', ?, 'pix')
            ");
            $stmt->execute([$user_id, $product_id, $amount, $payment['id']]);
            $logger->info("Transação PIX salva no banco com sucesso");
        } catch (Exception $e) {
            $logger->error("Erro ao salvar transação PIX no banco: " . $e->getMessage());
        }

        // Retornar dados do PIX
        $pix_info = $payment['point_of_interaction']['transaction_data']['qr_code'] ?? '';
        $pix_qr_code = $payment['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';

        echo json_encode([
            'success' => true,
            'payment_id' => $payment['id'],
            'status_url' => getStatusUrl($payment['id'], $product_id),
            'pix_data' => [
                'qr_code' => $pix_info,
                'qr_code_base64' => $pix_qr_code,
                'status' => $payment['status']
            ]
        ]);

    } elseif ($payment_method === 'credit_card') {
        // Processar cartão de crédito
        $card_number = $data['card_number'] ?? '';
        $card_expiration = $data['card_expiration'] ?? '';
        $card_cvv = $data['card_cvv'] ?? '';
        $card_holder_name = $data['card_holder_name'] ?? '';

        // Criar token do cartão
        $card_data = [
            'card_number' => preg_replace('/\s+/', '', $card_number),
            'security_code' => $card_cvv,
            'expiration_month' => explode('/', $card_expiration)[0],
            'expiration_year' => '20' . explode('/', $card_expiration)[1],
            'cardholder' => [
                'name' => $card_holder_name
            ]
        ];

        $logger->info("Dados do cartão criados: " . json_encode(array_merge($card_data, ['security_code' => '***'])));

        // Criar token do cartão
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $mp_base_url . '/v1/card_tokens');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($card_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $mp_access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $logger->info("Fazendo requisição para criar token do cartão");

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $logger->info("Resposta HTTP (token): " . $http_code);
        $logger->info("Resposta completa (token): " . $response);

        if ($http_code !== 201) {
            $logger->error("Erro ao criar token do cartão: " . $http_code . " - Resposta: " . $response);
            echo json_encode(['success' => false, 'message' => 'Erro ao processar dados do cartão']);
            exit;
        }

        $token_data = json_decode($response, true);

        if (!$token_data || !isset($token_data['id'])) {
            $logger->error("Token do cartão inválido: " . $response);
            echo json_encode(['success' => false, 'message' => 'Dados do cartão inválidos']);
            exit;
        }

        $logger->info("Token do cartão criado: " . $token_data['id']);

        // Criar pagamento com cartão
        $payment_data = [
            'transaction_amount' => floatval($amount),
            'token' => $token_data['id'],
            'description' => $product['name'],
            'installments' => 1,
            'payment_method_id' => 'visa', // Será detectado automaticamente
            'payer' => [
                'email' => $customer_email,
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/[^0-9]/', '', $customer_cpf)
                ]
            ],
            'external_reference' => "PROD_{$product_id}_USER_{$user_id}"
        ];

        $logger->info("Dados do pagamento com cartão criados: " . json_encode($payment_data));

        // Fazer requisição para criar pagamento
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $mp_base_url . '/v1/payments');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $mp_access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $logger->info("Fazendo requisição para criar pagamento com cartão");

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $logger->info("Resposta HTTP (pagamento): " . $http_code);
        $logger->info("Resposta completa (pagamento): " . $response);

        if ($http_code !== 201) {
            $logger->error("Erro ao criar pagamento com cartão: " . $http_code . " - Resposta: " . $response);
            echo json_encode(['success' => false, 'message' => 'Erro ao processar pagamento com cartão']);
            exit;
        }

        $payment = json_decode($response, true);

        if (!$payment || !isset($payment['id'])) {
            $logger->error("Resposta inválida do pagamento com cartão: " . $response);
            echo json_encode(['success' => false, 'message' => 'Resposta inválida do Mercado Pago']);
            exit;
        }

        $logger->info("Pagamento com cartão criado com sucesso - ID: " . $payment['id']);

        // Salvar transação no banco
        try {
            $stmt = $pdo->prepare("
                INSERT INTO product_purchases (user_id, product_id, amount, status, transaction_id, payment_method)
                VALUES (?, ?, ?, 'pending', ?, 'credit_card')
            ");
            $stmt->execute([$user_id, $product_id, $amount, $payment['id']]);
            $logger->info("Transação com cartão salva no banco com sucesso");
        } catch (Exception $e) {
            $logger->error("Erro ao salvar transação com cartão no banco: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'payment_id' => $payment['id'],
            'status' => $payment['status']
        ]);
    } else {
        $logger->error("Método de pagamento não suportado: " . $payment_method);
        echo json_encode(['success' => false, 'message' => 'Método de pagamento não suportado']);
    }

    $logger->info("=== PROCESSAMENTO CONCLUÍDO COM SUCESSO ===");

} catch (Exception $e) {
    $logger->error("Erro no processamento de pagamento: " . $e->getMessage());
    $logger->error("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

// Função para gerar URL de status
function getStatusUrl($payment_id, $product_id) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . "://" . $host;
    return $base_url . '/status.php?payment_id=' . $payment_id . '&product_id=' . $product_id;
}
?>
