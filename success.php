<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/success_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

saveLog("=== SUCCESS PAGE INICIADA ===");

$auth = new Auth($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Obter dados da transação
$paymentId = $_GET['payment_id'] ?? null;
$preferenceId = $_GET['preference_id'] ?? null;
$status = $_GET['status'] ?? null;
$type = $_GET['type'] ?? null;
$free = $_GET['free'] ?? null;
$orderId = $_GET['order'] ?? null;

saveLog("Payment ID: $paymentId");
saveLog("Preference ID: $preferenceId");
saveLog("Status: $status");
saveLog("Type: $type");
saveLog("Free: $free");
saveLog("Order ID: $orderId");

// Se é um plano gratuito, mostrar mensagem específica
if ($type === 'subscription' && $free === 'true') {
    saveLog("Processando plano gratuito...");
    
    // Obter dados do plano
    $planId = $_GET['plan'] ?? null;
    if ($planId) {
        require_once 'includes/Subscription.php';
        $subscription = new Subscription($pdo);
        $plan = $subscription->getPlanById($planId);
        
        if ($plan) {
            $planName = $plan['name'];
            $planPrice = 'R$ 0,00';
        } else {
            $planName = 'Plano Gratuito';
            $planPrice = 'R$ 0,00';
        }
    } else {
        $planName = 'Plano Gratuito';
        $planPrice = 'R$ 0,00';
    }
    
    // Definir variáveis para exibição
    $isFreePlan = true;
    $successMessage = "Plano ativado com sucesso!";
    $detailsMessage = "Seu plano gratuito foi ativado automaticamente.";
} else {
    $isFreePlan = false;
}

// Se temos dados do pagamento, atualizar status
if ($paymentId && $preferenceId) {
    saveLog("Atualizando status da transação...");
    
    // Se o status estiver vazio, usar 'completed' (pois estamos na página de success)
    $finalStatus = !empty($status) ? $status : 'completed';
    
    // Limitar o tamanho do status para evitar truncamento (máximo 10 caracteres)
    $finalStatus = substr($finalStatus, 0, 10);
    saveLog("Status final a ser aplicado: '$finalStatus' (tamanho: " . strlen($finalStatus) . ")");
    
    // Verificar se é um pagamento de plano ou produto
    $isPlanPayment = false;
    
    // Buscar o pedido para determinar o tipo
    $stmt = $pdo->prepare("SELECT order_type FROM orders WHERE payment_proof_path = ?");
    $stmt->execute([$preferenceId]);
    $orderType = $stmt->fetchColumn();
    
    if ($orderType === 'subscription') {
        $isPlanPayment = true;
        saveLog("Detectado pagamento de plano (subscription)");
    } else {
        saveLog("Detectado pagamento de produto");
    }
    
    if ($isPlanPayment) {
        // Processar pagamento de plano
        saveLog("Processando pagamento de plano...");
        
        // Verificar se o pedido já está aprovado
        $stmt = $pdo->prepare("SELECT payment_status FROM orders WHERE payment_proof_path = ?");
        $stmt->execute([$preferenceId]);
        $currentStatus = $stmt->fetchColumn();
        
        saveLog("Status atual do pedido: $currentStatus");
        
        if ($currentStatus === 'approved') {
            saveLog("Pedido já está aprovado - ativando assinatura...");
            
            // Buscar o pedido para ativar a assinatura
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE payment_proof_path = ? AND order_type = 'subscription'");
            $stmt->execute([$preferenceId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                require_once 'includes/Payment.php';
                $paymentClass = new Payment($pdo);
                
                // Usar método privado via reflexão para ativar a assinatura
                $reflection = new ReflectionClass($paymentClass);
                $method = $reflection->getMethod('activateSubscriptionFromOrder');
                $method->setAccessible(true);
                $result = $method->invoke($paymentClass, $order['id']);
                
                saveLog("Assinatura ativada para pedido {$order['id']}: " . ($result ? 'SUCESSO' : 'FALHA'));
            } else {
                saveLog("❌ Pedido não encontrado para ativação da assinatura");
            }
        } else {
            saveLog("Pedido não está aprovado ainda - status: $currentStatus");
        }
    } else {
        // Processar pagamento de produto (lógica original)
        try {
            $stmt = $pdo->prepare("UPDATE product_purchases SET status = ?, updated_at = NOW() WHERE transaction_id = ?");
            $stmt->execute([$finalStatus, $preferenceId]);
            $rowsAffected = $stmt->rowCount();
            
            saveLog("Status atualizado para: $finalStatus (linhas afetadas: $rowsAffected)");
        } catch (PDOException $e) {
            saveLog("ERRO ao atualizar status: " . $e->getMessage());
            saveLog("Status que causou erro: '$finalStatus' (tamanho: " . strlen($finalStatus) . ")");
            // Continuar sem quebrar a página
        }
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

        <!-- Breadcrumb -->
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Pagamento Aprovado</h6>
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
                    <li class="fw-medium">Pagamento Aprovado</li>
                </ul>
            </div>
        </div>

        <!-- Success Content -->
        <section class="py-80">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="card text-center">
                            <div class="card-body py-5">
                                <div class="mb-4">
                                    <i class="ri-checkbox-circle-line text-success" style="font-size: 4rem;"></i>
                                </div>
                                
                                <?php if ($isFreePlan): ?>
                                    <h3 class="card-title text-success mb-3">Plano Ativado!</h3>
                                    <p class="card-text text-neutral-600 mb-4">
                                        Seu plano gratuito foi ativado com sucesso!
                                    </p>
                                    
                                    <div class="alert alert-success">
                                        <h6 class="mb-2"><?= htmlspecialchars($planName) ?></h6>
                                        <p class="mb-0">
                                            <strong>Valor:</strong> <?= $planPrice ?><br>
                                            <strong>Status:</strong> Ativo
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <h3 class="card-title text-success mb-3">Pagamento Aprovado!</h3>
                                    <p class="card-text text-neutral-600 mb-4">
                                        Seu pagamento foi processado com sucesso. 
                                        Você receberá um email de confirmação em breve.
                                    </p>
                                    
                                    <?php if ($paymentId): ?>
                                    <div class="alert alert-info">
                                        <strong>ID do Pagamento:</strong> <?= htmlspecialchars($paymentId) ?>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <a href="index-membros.php" class="btn btn-primary">
                                        <i class="ri-home-line me-2"></i>Voltar ao Início
                                    </a>
                                    <a href="perfil.php" class="btn btn-outline-primary">
                                        <i class="ri-user-line me-2"></i>Meu Perfil
                                    </a>
                                    <?php if (!$isFreePlan): ?>
                                    <a href="meus-downloads.php" class="btn btn-outline-secondary">
                                        <i class="ri-download-line me-2"></i>Meus Downloads
                                    </a>
                                    <?php endif; ?>
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
        console.log('🎉 SUCCESS.php carregado com sucesso!');
    </script>
</body>
</html>
