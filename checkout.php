<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/checkout_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ===== LOGS DETALHADOS PARA DEBUG =====
saveLog("=== CHECKOUT DEBUG INICIADO ===");
saveLog("Script iniciado - checkout.php");

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

// Obter ID do produto
saveLog("Verificando ID do produto...");
$productId = $_GET['id'] ?? null;
saveLog("GET['id'] recebido: " . ($productId ?? 'NÃO DEFINIDO'));

if (!$productId) {
    saveLog("ID do produto não fornecido - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

saveLog("ID do produto válido: $productId");

// Obter dados do produto
saveLog("Buscando dados do produto com ID: $productId");
$productData = $product->getProductById($productId);

if (!$productData) {
    saveLog("Produto não encontrado - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

saveLog("Produto encontrado: " . $productData['name']);
saveLog("Dados do produto: " . json_encode($productData));

// Verificar se produto está disponível para venda individual
if (!$productData['individual_sale']) {
    saveLog("Produto não disponível para venda individual - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

saveLog("Produto disponível para venda - continuando");

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

saveLog("Dados do usuário encontrados");
saveLog("Nome: " . $userData['name']);
saveLog("Email: " . $userData['email']);
saveLog("Telefone: " . ($userData['phone'] ?? 'N/A'));

// ===== VERIFICAR CONFIGURAÇÕES DE PAGAMENTO =====
saveLog("Verificando configurações de pagamento...");
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('mercadopago_enabled', 'offline_payments_enabled', 'payment_enabled')");
$stmt->execute();
$paymentSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Converter para array associativo
$settingsArray = [];
foreach ($paymentSettings as $setting) {
    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
}

$mercadopagoEnabled = $settingsArray['mercadopago_enabled'] ?? '0';
$offlinePaymentsEnabled = $settingsArray['offline_payments_enabled'] ?? '0';
$paymentEnabled = $settingsArray['payment_enabled'] ?? '0';

saveLog("Mercado Pago habilitado: $mercadopagoEnabled");
saveLog("Pagamentos Offline habilitado: $offlinePaymentsEnabled");
saveLog("Pagamentos habilitado: $paymentEnabled");

// Verificar se pelo menos um método está habilitado
if ($mercadopagoEnabled !== '1' && $offlinePaymentsEnabled !== '1') {
    saveLog("Nenhum método de pagamento habilitado - redirecionando para produtos.php");
    header('Location: produtos.php?error=no_payment_method');
    exit;
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
                <h6 class="fw-semibold mb-0">Checkout</h6>
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
                    <li class="fw-medium">Checkout</li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="section-title text-center">
                            <h2 class="title">Finalizar Compra</h2>
                            <p class="text-neutral-600">Complete sua compra de forma segura</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Checkout Content -->
        <section class="py-80">
            <div class="container">
                <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="ri-error-warning-line me-8"></i>
                    <?php if ($_GET['error'] === 'mercadopago_disabled'): ?>
                        Mercado Pago não está habilitado. Escolha outro método de pagamento.
                    <?php elseif ($_GET['error'] === 'no_payment_method'): ?>
                        Nenhum método de pagamento está habilitado. Entre em contato com o administrador.
                    <?php endif; ?>
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

                                <!-- Botões de Pagamento -->
                                <?php if ($mercadopagoEnabled === '1'): ?>
                                <button type="button" class="btn btn-primary w-100 mt-3" onclick="finalizarCompraMercadoPago()">
                                    <i class="ri-bank-card-line me-2"></i>Pagar com Mercado Pago
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($offlinePaymentsEnabled === '1'): ?>
                                <button type="button" class="btn btn-warning w-100 mt-3" onclick="finalizarCompraOffline()">
                                    <i class="ri-bank-line me-2"></i>Pagar Offline (PIX/Transferência)
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($mercadopagoEnabled === '1'): ?>
                                <p class="text-neutral-600 text-center mt-2 small">
                                    <i class="ri-shield-check-line me-1"></i>
                                    Pagamento automático via Mercado Pago (PIX, Cartão, Débito)
                                </p>
                                <?php endif; ?>
                                
                                <?php if ($offlinePaymentsEnabled === '1'): ?>
                                <p class="text-neutral-600 text-center mt-2 small">
                                    <i class="ri-bank-line me-1"></i>
                                    Pagamento manual via PIX ou Transferência Bancária
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include './partials/scripts.php'; ?>

    <script>
        function finalizarCompraMercadoPago() {
            const productId = <?= $productId ?>;

            // Mostrar loading
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="ri-loader-4-line me-2"></i>Processando...';
            btn.disabled = true;

            // Redirecionar para o Mercado Pago
            window.location.href = `checkout_mercadopago.php?id=${productId}&method=all`;
        }

        function finalizarCompraOffline() {
            const productId = <?= $productId ?>;

            // Mostrar loading
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="ri-loader-4-line me-2"></i>Processando...';
            btn.disabled = true;

            // Redirecionar para o checkout offline
            window.location.href = `checkout_offline.php?id=${productId}`;
        }

        // Log de sucesso
        console.log('🎉 CHECKOUT.php carregado com sucesso!');
        console.log('Mercado Pago habilitado: <?= $mercadopagoEnabled ?>');
        console.log('Pagamentos Offline habilitado: <?= $offlinePaymentsEnabled ?>');
    </script>
</body>
</html>
