<?php
// Log inicial para verificar se o arquivo está sendo executado
// file_put_contents(__DIR__ . '/debug_plano_gratuito.log', "[" . date('Y-m-d H:i:s') . "] ARQUIVO PAGAMENTO-PLANO.PHP CARREGADO!" . PHP_EOL, FILE_APPEND | LOCK_EX);

require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Subscription.php';
require_once 'includes/Payment.php';

$auth = new Auth($pdo);
$subscription = new Subscription($pdo);
$payment = new Payment($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=pagamento-plano.php');
    exit;
}

// Verificar se plano foi selecionado
$planId = $_GET['plan'] ?? null;
if (!$planId) {
    header('Location: planos.php');
    exit;
}

// Obter dados do plano
$plan = $subscription->getPlanById($planId);
if (!$plan) {
    header('Location: planos.php');
    exit;
}

// Verificar se é um plano gratuito
$isFreePlan = (floatval($plan['price']) == 0);

// Processar ativação de plano gratuito via POST
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'activate_free_plan' && $isFreePlan) {
    try {
        // Verificar se o usuário já tem uma assinatura ativa
        $currentSubscription = $auth->getActiveSubscription();
        
        if ($currentSubscription) {
            // Só cancelar se a assinatura estiver ativa
            if ($currentSubscription['status'] === 'active') {
                $subscription->cancelSubscription($_SESSION['user_id']);
            }
        }
        
        // Criar pedido gratuito para histórico
        $orderId = $payment->createOrder(
            $_SESSION['user_id'],
            'subscription',
            0.00, // valor zero
            '0', // método de pagamento gratuito (apenas 1 caractere)
            [
                [
                    'type' => 'subscription_plan',
                    'id' => $plan['id'],
                    'name' => $plan['name'],
                    'price' => 0.00,
                    'quantity' => 1
                ]
            ]
        );
        
        // Marcar pedido como aprovado automaticamente (se a coluna existir)
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'approved', approved_at = NOW() WHERE id = ?");
            $stmt->execute([$orderId]);
        } catch (Exception $e) {
            // Se a coluna status não existir, apenas logar o erro mas continuar
            error_log("Coluna status não existe na tabela orders: " . $e->getMessage());
        }
        
        // Criar nova assinatura gratuita
        $subscriptionId = $subscription->createSubscription(
            $_SESSION['user_id'],
            $plan['id']
        );
        
        // Redirecionar para página de sucesso
        header('Location: success.php?type=subscription&plan=' . $plan['id'] . '&free=true&order=' . $orderId);
        exit;
        
    } catch (Exception $e) {
        $error = 'Erro ao ativar plano gratuito: ' . $e->getMessage();
    }
}

// Processar criação do pedido
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    try {
        $paymentMethod = $_POST['payment_method'];
        
        // Criar pedido
        $orderId = $payment->createOrder(
            $_SESSION['user_id'],
            'subscription',
            $plan['price'],
            $paymentMethod,
            [
                [
                    'type' => 'subscription_plan',
                    'id' => $plan['id'],
                    'name' => $plan['name'],
                    'price' => $plan['price'],
                    'quantity' => 1
                ]
            ]
        );
        
        header('Location: pagamento-comprovante.php?order=' . $orderId);
        exit;
    } catch (Exception $e) {
        $error = 'Erro ao criar pedido: ' . $e->getMessage();
    }
}

// Verificar se Mercado Pago está habilitado
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'mercadopago_enabled'");
$stmt->execute();
$mercadopagoEnabled = $stmt->fetchColumn() === '1';

// Verificar se pagamentos offline estão habilitados
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'offline_payments_enabled'");
$stmt->execute();
$offlinePaymentsEnabled = $stmt->fetchColumn() === '1';

// Obter configurações de pagamento ativas apenas se estiverem habilitadas
$paymentSettings = [];
if ($offlinePaymentsEnabled) {
    $paymentSettings = $payment->getActivePaymentSettings();
}
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
                <h6 class="fw-semibold mb-0">Pagamento do Plano</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="planos.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Planos
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">
                        <a href="planos.php" class="hover-text-primary">
                            <?= htmlspecialchars($planData['name']) ?>
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Pagamento</li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Pagamento do Plano</h1>
                        <p class="text-lg text-secondary-light mb-0">
                            Complete seu pagamento para ativar o plano <?= htmlspecialchars($plan['name']) ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Detalhes do Plano -->
        <section class="py-40">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-40">
                                <div class="row align-items-center mb-32">
                                    <div class="col-md-8">
                                        <h3 class="mb-8"><?= htmlspecialchars($plan['name']) ?></h3>
                                        <p class="text-neutral-600 mb-0"><?= htmlspecialchars($plan['description']) ?></p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="h2 fw-bold text-primary mb-0">
                                            R$ <?= number_format($plan['price'], 2, ',', '.') ?>
                                        </div>
                                        <small class="text-neutral-600">
                                            <?= $plan['duration_days'] ?> dias de acesso
                                        </small>
                                    </div>
                                </div>

                                <!-- Recursos do Plano -->
                                <?php if (!empty($plan['features'])): ?>
                                <div class="mb-32">
                                    <h6 class="mb-16">Recursos Incluídos:</h6>
                                    <ul class="list-unstyled">
                                        <?php foreach ($plan['features'] as $feature): ?>
                                        <li class="d-flex align-items-center mb-8">
                                            <i class="ri-check-line text-success me-12"></i>
                                            <span><?= htmlspecialchars($subscription->getFeatureDisplayName($feature)) ?></span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Métodos de Pagamento -->
        <section class="py-40">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base p-32">
                                <?php if ($isFreePlan): ?>
                                    <h4 class="mb-0">Ativação do Plano Gratuito</h4>
                                <?php else: ?>
                                    <h4 class="mb-0">Escolha o Método de Pagamento</h4>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-40">
                                <?php if ($isFreePlan): ?>
                                    <!-- Interface para Plano Gratuito -->
                                    <div class="text-center mb-32">
                                        <div class="mb-24">
                                            <i class="ri-gift-line text-success" style="font-size: 4rem;"></i>
                                        </div>
                                        <h5 class="text-success mb-16">Plano Gratuito!</h5>
                                        <p class="text-neutral-600 mb-32">
                                            Este plano é totalmente gratuito. Clique no botão abaixo para ativar imediatamente.
                                        </p>
                                        
                                        <?php if (isset($error)): ?>
                                        <div class="alert alert-danger mb-24">
                                            <i class="ri-error-warning-line me-2"></i>
                                            <?= htmlspecialchars($error) ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="activate_free_plan">
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-success btn-lg">
                                                    <i class="ri-check-line me-2"></i>
                                                    Ativar Plano Gratuito
                                                </button>
                                                <a href="planos.php" class="btn btn-outline-secondary">
                                                    <i class="ri-arrow-left-line me-2"></i>
                                                    Voltar aos Planos
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <!-- Interface para Planos Pagos -->
                                    <?php if ($mercadopagoEnabled): ?>
                                    <!-- Mercado Pago -->
                                    <div class="mb-32">
                                        <a href="checkout_plano_mercadopago.php?plan=<?= $plan['id'] ?>" class="btn btn-primary btn-lg w-100 mb-16">
                                            <i class="ri-credit-card-line me-2"></i>
                                            Pagar com Mercado Pago
                                        </a>
                                        <p class="text-neutral-600 text-center small">
                                            <i class="ri-shield-check-line me-1"></i>
                                            Pagamento automático via PIX, cartão de crédito ou débito
                                        </p>
                                    </div>
                                    
                                    <?php if ($offlinePaymentsEnabled): ?>
                                    <hr class="my-32">
                                    <p class="text-center text-neutral-600 mb-24">ou escolha um método offline:</p>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($offlinePaymentsEnabled && empty($paymentSettings)): ?>
                                    <div class="alert alert-warning">
                                        <i class="ri-alert-line me-2"></i>
                                        Nenhum método de pagamento offline configurado. Entre em contato com o administrador.
                                    </div>
                                    <?php elseif ($offlinePaymentsEnabled && !empty($paymentSettings)): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="create_order">
                                        
                                        <?php foreach ($paymentSettings as $setting): ?>
                                        <div class="form-check mb-24">
                                            <input class="form-check-input" type="radio" name="payment_method" 
                                                   id="payment_<?= $setting['id'] ?>" value="<?= $setting['payment_type'] ?>" required>
                                            <label class="form-check-label" for="payment_<?= $setting['id'] ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-16">
                                                        <?php if ($setting['payment_type'] === 'pix'): ?>
                                                            <i class="ri-qr-code-line text-primary" style="font-size: 2rem;"></i>
                                                        <?php elseif ($setting['payment_type'] === 'bank_transfer'): ?>
                                                            <i class="ri-bank-line text-primary" style="font-size: 2rem;"></i>
                                                        <?php elseif ($setting['payment_type'] === 'boleto'): ?>
                                                            <i class="ri-file-text-line text-primary" style="font-size: 2rem;"></i>
                                                        <?php else: ?>
                                                            <i class="ri-credit-card-line text-primary" style="font-size: 2rem;"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-4"><?= htmlspecialchars($setting['title']) ?></h6>
                                                        <p class="text-neutral-600 mb-0"><?= htmlspecialchars($setting['description']) ?></p>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>

                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="ri-arrow-right-line me-2"></i>
                                                Continuar para Pagamento
                                            </button>
                                            <a href="planos.php" class="btn btn-outline-secondary">
                                                <i class="ri-arrow-left-line me-2"></i>
                                                Voltar aos Planos
                                            </a>
                                        </div>
                                    </form>
                                    <?php elseif (!$mercadopagoEnabled && !$offlinePaymentsEnabled): ?>
                                    <div class="alert alert-warning">
                                        <i class="ri-alert-line me-2"></i>
                                        Nenhum método de pagamento configurado. Entre em contato com o administrador.
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php include './partials/footer.php' ?>
    </main>

    <?php include './partials/scripts.php' ?>

</body>
</html>
