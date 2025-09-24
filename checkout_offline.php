<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/Payment.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/checkout_offline_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ===== LOGS DETALHADOS PARA DEBUG =====
saveLog("=== CHECKOUT OFFLINE INICIADO ===");
saveLog("Script iniciado - checkout_offline.php");

$auth = new Auth($pdo);
$product = new Product($pdo);
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

// Obter ID do produto
$productId = $_GET['id'] ?? null;
saveLog("Produto ID: $productId");

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

// Verificar se pagamentos offline estão habilitados
saveLog("Verificando configurações de pagamento offline...");
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('offline_payments_enabled', 'pix_enabled', 'pix_key', 'pix_key_type', 'bank_transfer_enabled', 'bank_info')");
$stmt->execute();
$offlineSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Converter para array associativo
$settingsArray = [];
foreach ($offlineSettings as $setting) {
    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
}

$offlineEnabled = $settingsArray['offline_payments_enabled'] ?? '0';
$pixEnabled = $settingsArray['pix_enabled'] ?? '0';
$pixKey = $settingsArray['pix_key'] ?? '';
$pixKeyType = $settingsArray['pix_key_type'] ?? 'email';
$bankTransferEnabled = $settingsArray['bank_transfer_enabled'] ?? '0';
$bankInfo = $settingsArray['bank_info'] ?? '';

saveLog("Pagamentos offline habilitado: $offlineEnabled");
saveLog("PIX habilitado: $pixEnabled");
saveLog("Transferência bancária habilitado: $bankTransferEnabled");

if ($offlineEnabled !== '1') {
    saveLog("Pagamentos offline não habilitados - redirecionando para checkout.php");
    header('Location: checkout.php?id=' . $productId);
    exit;
}

// Processar formulário de pagamento offline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_offline_order') {
    saveLog("Processando pedido offline...");
    
    try {
        saveLog("Criando pedido offline com dados:");
        saveLog("User ID: $userId");
        saveLog("Order Type: product");
        saveLog("Total Amount: " . $productData['individual_price']);
        saveLog("Payment Method: pix");
        
        // Criar pedido offline
        $orderId = $payment->createOrder(
            $userId,
            'product',
            $productData['individual_price'],
            'pix',
            [
                [
                    'type' => 'product',
                    'id' => $productId,
                    'name' => $productData['name'],
                    'price' => $productData['individual_price'],
                    'quantity' => 1
                ]
            ]
        );
        
        saveLog("Pedido offline criado com sucesso - ID: $orderId");
        
        // Criar registro em product_purchases (igual ao Mercado Pago)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO product_purchases (user_id, product_id, transaction_id, amount, payment_method, status, created_at) 
                VALUES (?, ?, ?, ?, 'pix', 'pending', NOW())
            ");
            $stmt->execute([
                $userId,
                $productId,
                $orderId, // Usar orderId como transaction_id
                $productData['individual_price']
            ]);
            saveLog("Registro em product_purchases criado com sucesso");
        } catch (Exception $e) {
            saveLog("Erro ao criar registro em product_purchases: " . $e->getMessage());
        }
        
        // Redirecionar para página de instruções de pagamento
        header('Location: pagamento-offline.php?order_id=' . $orderId);
        exit;
        
    } catch (Exception $e) {
        saveLog("Erro ao criar pedido offline: " . $e->getMessage());
        $errorMessage = "Erro ao processar pedido. Tente novamente.";
    }
}

saveLog("Renderizando HTML");
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include './partials/head.php' ?>

<body>

    <?php include './partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include './partials/navbar.php' ?>

        <!-- Breadcrumb -->
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Checkout Offline</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="produtos.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Produtos
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">
                        <a href="produto.php?id=<?= $productId ?>" class="hover-text-primary">
                            <?= htmlspecialchars($productData['name']) ?>
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Checkout Offline</li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="section-title text-center">
                            <h2 class="title">Pagamento Offline</h2>
                            <p class="text-neutral-600">Complete sua compra via PIX ou Transferência Bancária</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Checkout Content -->
        <section class="py-80">
            <div class="container">
                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ri-error-warning-line me-8"></i>
                    <?= htmlspecialchars($errorMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Produto Info -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Detalhes do Produto</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <img src="<?= htmlspecialchars($productData['image_path']) ?>" 
                                             alt="<?= htmlspecialchars($productData['name']) ?>" 
                                             class="img-fluid rounded">
                                    </div>
                                    <div class="col-md-9">
                                        <h6 class="mb-2"><?= htmlspecialchars($productData['name']) ?></h6>
                                        <p class="text-neutral-600 mb-2"><?= htmlspecialchars($productData['short_description']) ?></p>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary me-2"><?= htmlspecialchars($productData['category_name']) ?></span>
                                            <span class="text-success fw-bold">R$ <?= number_format($productData['individual_price'], 2, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dados do Usuário -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title">Seus Dados</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Nome</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($userData['name']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" readonly>
                                    </div>
                                </div>
                                <?php if ($userData['phone']): ?>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Telefone</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($userData['phone']) ?>" readonly>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Resumo e Pagamento -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Resumo da Compra</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Produto:</span>
                                    <span><?= htmlspecialchars($productData['name']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Preço:</span>
                                    <span class="fw-bold">R$ <?= number_format($productData['individual_price'], 2, ',', '.') ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="fw-bold">Total:</span>
                                    <span class="fw-bold text-primary fs-5">R$ <?= number_format($productData['individual_price'], 2, ',', '.') ?></span>
                                </div>

                                <!-- Informações de Pagamento -->
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">
                                        <i class="ri-information-line me-8"></i>
                                        Instruções de Pagamento
                                    </h6>
                                    <p class="mb-0">
                                        Após confirmar o pedido, você receberá as instruções completas para realizar o pagamento via PIX ou Transferência Bancária.
                                    </p>
                                </div>

                                <!-- Botão Finalizar -->
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="create_offline_order">
                                    <button type="submit" class="btn btn-warning w-100 mt-3">
                                        <i class="ri-bank-line me-2"></i>Confirmar Pedido
                                    </button>
                                </form>
                                
                                <p class="text-neutral-600 text-center mt-2 small">
                                    <i class="ri-bank-line me-1"></i>
                                    Pagamento manual via PIX ou Transferência Bancária
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include './partials/scripts.php'; ?>

    <script>
        // Log de sucesso
        console.log('🎉 CHECKOUT OFFLINE carregado com sucesso!');
        console.log('PIX habilitado: <?= $pixEnabled ?>');
        console.log('Transferência bancária habilitado: <?= $bankTransferEnabled ?>');
    </script>
</body>
</html>
