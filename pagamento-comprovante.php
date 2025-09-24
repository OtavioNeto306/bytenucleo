<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Payment.php';

$auth = new Auth($pdo);
$payment = new Payment($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=pagamento-comprovante.php');
    exit;
}

// Verificar se pedido foi fornecido
$orderId = $_GET['order'] ?? null;
if (!$orderId) {
    header('Location: planos.php');
    exit;
}

// Obter dados do pedido
$order = $payment->getOrder($orderId);
if (!$order || $order['user_id'] != $_SESSION['user_id']) {
    header('Location: planos.php');
    exit;
}

// Verificar se pedido expirou
if ($payment->isOrderExpired($orderId)) {
    $error = 'Este pedido expirou. Por favor, crie um novo pedido.';
}

// Obter itens do pedido
$orderItems = $payment->getOrderItems($orderId);

// Obter configuração de pagamento baseada no método usado
$paymentSettings = $payment->getActivePaymentSettings();
$paymentSetting = null;
foreach ($paymentSettings as $setting) {
    if ($setting['payment_type'] === $order['payment_method']) {
        $paymentSetting = $setting;
        break;
    }
}

// Para PIX, ler configurações da tabela settings usando Config
require_once 'includes/Config.php';
$config = new Config($pdo);

$pixConfig = null;
if ($order['payment_method'] === 'pix') {
    $paymentConfigs = $config->getGrouped()['payment'];
    
    if ($paymentConfigs['pix_enabled']) {
        $pixConfig = [
            'title' => 'Pagamento via PIX',
            'description' => 'Pagamento instantâneo via PIX',
            'pix_key' => $paymentConfigs['pix_key'],
            'pix_key_type' => $paymentConfigs['pix_key_type']
        ];
    }
}

// Processar upload de comprovante
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'upload_proof') {
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (in_array($_FILES['payment_proof']['type'], $allowedTypes) && $_FILES['payment_proof']['size'] <= $maxSize) {
            if ($payment->uploadPaymentProof($orderId, $_FILES['payment_proof'])) {
                $success = 'Comprovante enviado com sucesso! Aguarde a aprovação do administrador.';
                // Recarregar dados do pedido
                $order = $payment->getOrder($orderId);
            } else {
                $error = 'Erro ao fazer upload do comprovante. Tente novamente.';
            }
        } else {
            $error = 'Arquivo inválido. Use apenas imagens (JPG, PNG, GIF) ou PDF com máximo 5MB.';
        }
    } else {
        $error = 'Por favor, selecione um arquivo para upload.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include './partials/head.php' ?>

<body>

    <?php include './partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include './partials/navbar.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Dados de Pagamento</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Pedido #<?= htmlspecialchars($order['order_number']) ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Status do Pedido -->
        <section class="py-40">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="ri-error-warning-line me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="ri-check-line me-2"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Status do Pedido -->
                        <div class="card border-0 shadow-sm mb-32">
                            <div class="card-body p-32">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                                                <h5 class="fw-semibold mb-8">Status do Pedido</h5>
                        <p class="text-neutral-600 mb-0">
                            Pedido #<?= htmlspecialchars($order['order_number']) ?> - 
                            <?= ucfirst($order['payment_status']) ?>
                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php if ($order['payment_status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Aguardando Pagamento</span>
                                        <?php elseif ($order['payment_status'] === 'approved'): ?>
                                            <span class="badge bg-success">Aprovado</span>
                                        <?php elseif ($order['payment_status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">Rejeitado</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Cancelado</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalhes do Pedido -->
                        <div class="card border-0 shadow-sm mb-32">
                            <div class="card-header bg-base p-32">
                                <h5 class="fw-semibold mb-0">Detalhes do Pedido</h5>
                            </div>
                            <div class="card-body p-32">
                                <div class="row mb-24">
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-neutral-600">Valor Total:</span><br>
                                        <span class="h4 text-primary fw-bold">R$ <?= number_format($order['total_amount'], 2, ',', '.') ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-neutral-600">Método de Pagamento:</span><br>
                                        <span class="text-capitalize text-neutral-600"><?= str_replace('_', ' ', $order['payment_method']) ?></span>
                                    </div>
                                </div>

                                <div class="row mb-24">
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-neutral-600">Data do Pedido:</span><br>
                                        <span class="text-neutral-600"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <span class="fw-semibold text-neutral-600">Expira em:</span><br>
                                        <span class="text-neutral-600"><?= date('d/m/Y H:i', strtotime($order['expires_at'])) ?></span>
                                    </div>
                                </div>

                                <!-- Itens do Pedido -->
                                <div class="mt-32">
                                    <h6 class="fw-semibold mb-16">Itens do Pedido:</h6>
                                    <?php foreach ($orderItems as $item): ?>
                                    <div class="d-flex justify-content-between align-items-center py-12 border-bottom">
                                        <div>
                                            <span class="fw-semibold text-neutral-600"><?= htmlspecialchars($item['item_name']) ?></span>
                                            <br>
                                            <small class="text-neutral-600">
                                                <?= $item['item_type'] === 'subscription_plan' ? 'Plano de Assinatura' : 'Produto' ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="fw-semibold text-neutral-600">R$ <?= number_format($item['item_price'], 2, ',', '.') ?></span>
                                            <br>
                                            <small class="text-neutral-600">Qtd: <?= $item['quantity'] ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Dados para Pagamento -->
                        <?php if ($order['payment_status'] === 'pending'): ?>
                        <div class="card border-0 shadow-sm mb-32">
                            <div class="card-header bg-base p-32">
                                <h5 class="fw-semibold mb-0">Dados para Pagamento</h5>
                            </div>
                            <div class="card-body p-32">
                                <?php if ($order['payment_method'] === 'pix' && $pixConfig): ?>
                                <div class="text-center mb-32">
                                    <div class="bg-base p-32 rounded">
                                        <i class="ri-qr-code-line text-primary" style="font-size: 4rem;"></i>
                                        <h6 class="fw-semibold mt-16 mb-8"><?= htmlspecialchars($pixConfig['title']) ?></h6>
                                        <p class="text-neutral-600 mb-16">
                                            <?= htmlspecialchars($pixConfig['description']) ?>
                                        </p>
                                        <?php if ($pixConfig['pix_key']): ?>
                                        <div class="bg-base p-16 rounded border">
                                            <span class="fw-semibold text-neutral-600">Chave PIX:</span> <?= htmlspecialchars($pixConfig['pix_key']) ?><br>
                                            <span class="fw-semibold text-neutral-600">Tipo:</span> <?= ucfirst($pixConfig['pix_key_type'] ?? 'Email') ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php elseif ($order['payment_method'] === 'bank_transfer' && $paymentSetting): ?>
                                <div class="bg-base p-32 rounded">
                                    <h6 class="fw-semibold mb-16"><?= htmlspecialchars($paymentSetting['title']) ?></h6>
                                    <p class="text-neutral-600 mb-16"><?= htmlspecialchars($paymentSetting['description']) ?></p>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <?php if ($paymentSetting['bank_name']): ?>
                                            <span class="fw-semibold text-neutral-600">Banco:</span> <?= htmlspecialchars($paymentSetting['bank_name']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($paymentSetting['bank_agency']): ?>
                                            <span class="fw-semibold text-neutral-600">Agência:</span> <?= htmlspecialchars($paymentSetting['bank_agency']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($paymentSetting['bank_account']): ?>
                                            <span class="fw-semibold text-neutral-600">Conta:</span> <?= htmlspecialchars($paymentSetting['bank_account']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <?php if ($paymentSetting['account_holder']): ?>
                                            <span class="fw-semibold text-neutral-600">Titular:</span> <?= htmlspecialchars($paymentSetting['account_holder']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($paymentSetting['account_document']): ?>
                                            <span class="fw-semibold text-neutral-600">CPF/CNPJ:</span> <?= htmlspecialchars($paymentSetting['account_document']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($paymentSetting['bank_account_type']): ?>
                                            <span class="fw-semibold text-neutral-600">Tipo:</span> <?= ucfirst($paymentSetting['bank_account_type']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php elseif ($order['payment_method'] === 'boleto' && $paymentSetting): ?>
                                <div class="bg-base p-32 rounded">
                                    <h6 class="fw-semibold mb-16"><?= htmlspecialchars($paymentSetting['title']) ?></h6>
                                    <p class="text-neutral-600 mb-16"><?= htmlspecialchars($paymentSetting['description']) ?></p>
                                    <?php if ($paymentSetting['boleto_instructions']): ?>
                                    <div class="bg-base p-16 rounded border">
                                        <span class="fw-semibold text-neutral-600">Instruções:</span><br>
                                        <?= nl2br(htmlspecialchars($paymentSetting['boleto_instructions'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php elseif ($order['payment_method'] === 'card' && $paymentSetting): ?>
                                <div class="bg-base p-32 rounded">
                                    <h6 class="fw-semibold mb-16"><?= htmlspecialchars($paymentSetting['title']) ?></h6>
                                    <p class="text-neutral-600 mb-16"><?= htmlspecialchars($paymentSetting['description']) ?></p>
                                    <?php if ($paymentSetting['card_instructions']): ?>
                                    <div class="bg-base p-16 rounded border">
                                        <span class="fw-semibold text-neutral-600">Instruções:</span><br>
                                        <?= nl2br(htmlspecialchars($paymentSetting['card_instructions'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="ri-information-line me-2"></i>
                                    Entre em contato conosco para obter as instruções de pagamento.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Upload de Comprovante -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base p-32">
                                <h5 class="fw-semibold mb-0">Enviar Comprovante</h5>
                            </div>
                            <div class="card-body p-32">
                                <?php if ($order['payment_proof_path']): ?>
                                <div class="alert alert-success mb-24">
                                    <i class="ri-check-line me-2"></i>
                                    Comprovante enviado em <?= date('d/m/Y H:i', strtotime($order['payment_proof_uploaded_at'])) ?>
                                </div>
                                <?php endif; ?>

                                <form method="POST" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_proof">
                                    
                                    <div class="mb-24">
                                        <label for="payment_proof" class="form-label fw-semibold text-neutral-600">Comprovante de Pagamento</label>
                                        <input type="file" class="form-control" id="payment_proof" name="payment_proof" 
                                               accept="image/*,.pdf" required>
                                        <div class="form-text text-neutral-600">
                                            Aceitos: JPG, PNG, GIF, PDF (máximo 5MB)
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ri-upload-line me-2"></i>
                                            Enviar Comprovante
                                        </button>
                                        <a href="index-membros.php" class="btn btn-outline-secondary">
                                            <i class="ri-home-line me-2"></i>
                                            Voltar ao Início
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Pedido já processado -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32 text-center">
                                <?php if ($order['payment_status'] === 'approved'): ?>
                                <div class="text-success mb-16">
                                    <i class="ri-check-circle-line" style="font-size: 4rem;"></i>
                                </div>
                                <h5 class="fw-semibold mb-16">Pagamento Aprovado!</h5>
                                <p class="text-neutral-600 mb-24">
                                    Seu pagamento foi aprovado e seu acesso foi liberado.
                                </p>
                                <?php elseif ($order['payment_status'] === 'rejected'): ?>
                                <div class="text-danger mb-16">
                                    <i class="ri-close-circle-line" style="font-size: 4rem;"></i>
                                </div>
                                <h5 class="fw-semibold mb-16">Pagamento Rejeitado</h5>
                                <p class="text-neutral-600 mb-24">
                                    Seu pagamento foi rejeitado. Entre em contato conosco para mais informações.
                                </p>
                                <?php endif; ?>
                                
                                <a href="index-membros.php" class="btn btn-primary">
                                    <i class="ri-home-line me-2"></i>
                                    Voltar ao Início
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php include './partials/footer.php' ?>
    </main>

    <?php include './partials/scripts.php' ?>

</body>
</html>
