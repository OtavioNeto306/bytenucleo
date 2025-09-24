<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Payment.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/pagamento_offline_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ===== LOGS DETALHADOS PARA DEBUG =====
saveLog("=== PAGAMENTO OFFLINE INICIADO ===");
saveLog("Script iniciado - pagamento-offline.php");

$auth = new Auth($pdo);
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

// Obter ID do pedido
$orderId = $_GET['order_id'] ?? null;
saveLog("Order ID: $orderId");

if (!$orderId) {
    saveLog("ID do pedido não fornecido - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

// Obter dados do pedido
saveLog("Buscando dados do pedido...");
$orderData = $payment->getOrder($orderId);

if (!$orderData) {
    saveLog("Pedido não encontrado - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

// Verificar se o pedido pertence ao usuário logado
if ($orderData['user_id'] != $_SESSION['user_id']) {
    saveLog("Pedido não pertence ao usuário - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

saveLog("Pedido encontrado: " . $orderData['order_number']);

// Obter itens do pedido
$orderItems = $payment->getOrderItems($orderId);
saveLog("Itens do pedido: " . count($orderItems));

// Obter configurações de pagamento offline
saveLog("Buscando configurações de pagamento offline...");
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('pix_enabled', 'pix_key', 'pix_key_type', 'bank_transfer_enabled', 'bank_info')");
$stmt->execute();
$offlineSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Converter para array associativo
$settingsArray = [];
foreach ($offlineSettings as $setting) {
    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
}

$pixEnabled = $settingsArray['pix_enabled'] ?? '0';
$pixKey = $settingsArray['pix_key'] ?? '';
$pixKeyType = $settingsArray['pix_key_type'] ?? 'email';
$bankTransferEnabled = $settingsArray['bank_transfer_enabled'] ?? '0';
$bankInfo = $settingsArray['bank_info'] ?? '';

saveLog("PIX habilitado: $pixEnabled");
saveLog("Transferência bancária habilitado: $bankTransferEnabled");

// Processar upload de comprovante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_proof') {
    saveLog("Processando upload de comprovante...");
    
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        try {
            $uploadResult = $payment->uploadPaymentProof($orderId, $_FILES['payment_proof']);
            
            if ($uploadResult) {
                saveLog("Comprovante enviado com sucesso");
                $successMessage = "Comprovante enviado com sucesso! Aguarde a aprovação do pagamento.";
            } else {
                saveLog("Erro ao enviar comprovante");
                $errorMessage = "Erro ao enviar comprovante. Tente novamente.";
            }
        } catch (Exception $e) {
            saveLog("Erro ao processar comprovante: " . $e->getMessage());
            $errorMessage = "Erro ao processar comprovante: " . $e->getMessage();
        }
    } else {
        saveLog("Nenhum arquivo enviado ou erro no upload");
        $errorMessage = "Por favor, selecione um arquivo válido.";
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
                <h6 class="fw-semibold mb-0">Pagamento Offline</h6>
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
                    <li class="fw-medium">Pagamento Offline</li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="section-title text-center">
                            <h2 class="title">Instruções de Pagamento</h2>
                            <p class="text-neutral-600">Pedido #<?= htmlspecialchars($orderData['order_number']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="py-80">
            <div class="container">
                <?php if (isset($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ri-check-line me-8"></i>
                    <?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ri-error-warning-line me-8"></i>
                    <?= htmlspecialchars($errorMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Resumo do Pedido -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Resumo do Pedido</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Pedido:</span>
                                    <span class="fw-bold">#<?= htmlspecialchars($orderData['order_number']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Data:</span>
                                    <span><?= date('d/m/Y H:i', strtotime($orderData['created_at'])) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Status:</span>
                                    <?php 
                                    $paymentStatus = $orderData['payment_status'] ?? 'pending';
                                    if ($paymentStatus === 'approved'): ?>
                                        <span class="badge bg-success">Aprovado</span>
                                    <?php elseif ($paymentStatus === 'rejected'): ?>
                                        <span class="badge bg-danger">Rejeitado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pendente</span>
                                    <?php endif; ?>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total:</span>
                                    <span class="fw-bold text-primary fs-5">R$ <?= number_format($orderData['total_amount'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Itens do Pedido -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title">Itens</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($orderItems as $item): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= htmlspecialchars($item['item_name']) ?></span>
                                    <span class="fw-bold">R$ <?= number_format($item['item_price'], 2, ',', '.') ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Instruções de Pagamento -->
                    <div class="col-lg-8">
                        <!-- PIX -->
                        <?php if ($pixEnabled === '1' && !empty($pixKey)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ri-qr-code-line me-8"></i>
                                    Pagamento via PIX
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Chave PIX (<?= ucfirst($pixKeyType) ?>):</h6>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($pixKey) ?>" readonly id="pixKey">
                                            <button class="btn btn-outline-secondary" type="button" onclick="copyPixKey()">
                                                <i class="ri-file-copy-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Valor:</h6>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control" value="R$ <?= number_format($orderData['total_amount'], 2, ',', '.') ?>" readonly id="pixValue">
                                            <button class="btn btn-outline-secondary" type="button" onclick="copyPixValue()">
                                                <i class="ri-file-copy-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">Instruções:</h6>
                                    <ol class="mb-0">
                                        <li>Abra o aplicativo do seu banco</li>
                                        <li>Escaneie o QR Code ou copie a chave PIX</li>
                                        <li>Digite o valor exato: <strong>R$ <?= number_format($orderData['total_amount'], 2, ',', '.') ?></strong></li>
                                        <li>Realize o pagamento</li>
                                        <li>Envie o comprovante abaixo</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Transferência Bancária -->
                        <?php if ($bankTransferEnabled === '1' && !empty($bankInfo)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ri-bank-line me-8"></i>
                                    Transferência Bancária
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6>Dados Bancários:</h6>
                                    <div class="bg-light p-3 rounded">
                                        <pre class="mb-0"><?= htmlspecialchars($bankInfo) ?></pre>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <h6>Valor:</h6>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="R$ <?= number_format($orderData['total_amount'], 2, ',', '.') ?>" readonly id="bankValue">
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyBankValue()">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">Instruções:</h6>
                                    <ol class="mb-0">
                                        <li>Realize uma transferência para os dados bancários acima</li>
                                        <li>Use o valor exato: <strong>R$ <?= number_format($orderData['total_amount'], 2, ',', '.') ?></strong></li>
                                        <li>No campo "Identificação" ou "Referência", informe: <strong><?= htmlspecialchars($orderData['order_number']) ?></strong></li>
                                        <li>Envie o comprovante abaixo</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Upload de Comprovante -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="ri-upload-line me-8"></i>
                                    Enviar Comprovante
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_proof">
                                    
                                    <div class="mb-3">
                                        <label for="payment_proof" class="form-label">Comprovante de Pagamento</label>
                                        <input type="file" class="form-control" id="payment_proof" name="payment_proof" accept="image/*,.pdf" required>
                                        <div class="form-text">
                                            Formatos aceitos: JPG, PNG, PDF (máximo 5MB)
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ri-upload-line me-8"></i>
                                        Enviar Comprovante
                                    </button>
                                </form>
                                
                                <div class="alert alert-warning mt-3">
                                    <h6 class="alert-heading">
                                        <i class="ri-time-line me-8"></i>
                                        Tempo de Aprovação
                                    </h6>
                                    <p class="mb-0">
                                        Após o envio do comprovante, seu pagamento será analisado em até 24 horas úteis. 
                                        Você receberá uma notificação quando o pagamento for aprovado.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include './partials/scripts.php'; ?>

    <script>
        function copyPixKey() {
            const pixKey = document.getElementById('pixKey');
            pixKey.select();
            pixKey.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Mostrar feedback
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="ri-check-line"></i>';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }

        function copyPixValue() {
            const pixValue = document.getElementById('pixValue');
            pixValue.select();
            pixValue.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Mostrar feedback
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="ri-check-line"></i>';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }

        function copyBankValue() {
            const bankValue = document.getElementById('bankValue');
            bankValue.select();
            bankValue.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Mostrar feedback
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="ri-check-line"></i>';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }

        // Log de sucesso
        console.log('🎉 PAGAMENTO OFFLINE carregado com sucesso!');
        console.log('PIX habilitado: <?= $pixEnabled ?>');
        console.log('Transferência bancária habilitado: <?= $bankTransferEnabled ?>');
    </script>
</body>
</html>
