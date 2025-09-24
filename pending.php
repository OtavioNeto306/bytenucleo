<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';

// Função para salvar logs
function saveLog($message) {
    $logFile = 'logs/checkout.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

saveLog("=== PENDING PAGE INICIADA ===");

$auth = new Auth($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    saveLog("Usuário não logado - redirecionando para login");
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
saveLog("Usuário logado: $userId");

// Obter dados da URL
$payment_id = $_GET['payment_id'] ?? null;
$external_reference = $_GET['external_reference'] ?? null;
$collection_id = $_GET['collection_id'] ?? null;
$collection_status = $_GET['collection_status'] ?? null;
$status = $_GET['status'] ?? null;
$payment_type = $_GET['payment_type'] ?? null;

saveLog("Dados recebidos da URL:");
saveLog("  - payment_id: $payment_id");
saveLog("  - external_reference: $external_reference");
saveLog("  - collection_id: $collection_id");
saveLog("  - collection_status: $collection_status");
saveLog("  - status: $status");
saveLog("  - payment_type: $payment_type");

// Verificar se temos dados suficientes para buscar o pagamento
$paymentStatus = 'pending';
$paymentData = null;

if ($payment_id || $external_reference) {
    saveLog("Buscando dados do pagamento no banco...");
    
    // Buscar o pagamento no banco
    if ($payment_id) {
        // Primeiro, tentar buscar em product_purchases (produtos)
        $stmt = $pdo->prepare("SELECT * FROM product_purchases WHERE transaction_id = ? AND user_id = ?");
        $stmt->execute([$payment_id, $userId]);
        $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se não encontrou, tentar buscar em orders (planos)
        if (!$paymentData) {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE payment_proof_path = ? AND user_id = ?");
            $stmt->execute([$payment_id, $userId]);
            $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($paymentData) {
                $paymentData['payment_source'] = 'subscription'; // Marcar como plano
            }
        } else {
            $paymentData['payment_source'] = 'product'; // Marcar como produto
        }
        
        saveLog("Buscando por payment_id: $payment_id");
    }
    
    if (!$paymentData && $external_reference) {
        // Verificar se é um plano
        if (preg_match('/plano_(\d+)_(\d+)_(\d+)/', $external_reference, $matches)) {
            $plan_id_ref = $matches[1];
            $user_id_ref = $matches[2];
            $timestamp_ref = $matches[3];
            
            saveLog("Buscando plano por external_reference: user_id=$user_id_ref, plan_id=$plan_id_ref");
            
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND order_type = 'subscription' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user_id_ref]);
            $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($paymentData) {
                $paymentData['payment_source'] = 'subscription';
            }
        }
        // Verificar se é um produto
        elseif (preg_match('/PROD_(\d+)_USER_(\d+)_(\d+)/', $external_reference, $matches)) {
            $product_id_ref = $matches[1];
            $user_id_ref = $matches[2];
            $timestamp_ref = $matches[3];
            
            saveLog("Buscando produto por external_reference: user_id=$user_id_ref, product_id=$product_id_ref");
            
            $stmt = $pdo->prepare("SELECT * FROM product_purchases WHERE user_id = ? AND product_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user_id_ref, $product_id_ref]);
            $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($paymentData) {
                $paymentData['payment_source'] = 'product';
            }
        }
    }
    
    if ($paymentData) {
        // Determinar status baseado no tipo de pagamento
        if (isset($paymentData['payment_source']) && $paymentData['payment_source'] === 'subscription') {
            $paymentStatus = $paymentData['payment_status']; // Para planos
        } else {
            $paymentStatus = $paymentData['status']; // Para produtos
        }
        
        saveLog("Pagamento encontrado no banco - Status: $paymentStatus");
        saveLog("Dados do pagamento: " . json_encode($paymentData));
        
        // Se o pagamento foi aprovado, redirecionar para success
        if ($paymentStatus === 'completed' || $paymentStatus === 'approved') {
            saveLog("Pagamento aprovado - redirecionando para success.php");
            if (isset($paymentData['payment_source']) && $paymentData['payment_source'] === 'subscription') {
                header("Location: success.php?payment_id=$payment_id&preference_id=" . $paymentData['payment_proof_path']);
            } else {
                header("Location: success.php?payment_id=$payment_id&preference_id=" . $paymentData['transaction_id']);
            }
            exit;
        }
        
        // Se o pagamento foi cancelado, redirecionar para failure
        if ($paymentStatus === 'cancelled' || $paymentStatus === 'rejected') {
            saveLog("Pagamento cancelado - redirecionando para failure.php");
            if (isset($paymentData['payment_source']) && $paymentData['payment_source'] === 'subscription') {
                header("Location: failure.php?payment_id=$payment_id&preference_id=" . $paymentData['payment_proof_path']);
            } else {
                header("Location: failure.php?payment_id=$payment_id&preference_id=" . $paymentData['transaction_id']);
            }
            exit;
        }
    } else {
        saveLog("Pagamento não encontrado no banco");
    }
}

// Buscar dados do produto se temos external_reference
$productData = null;
if ($external_reference && preg_match('/PROD_(\d+)_USER_(\d+)_(\d+)/', $external_reference, $matches)) {
    $productId = $matches[1];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $productData = $stmt->fetch(PDO::FETCH_ASSOC);
    saveLog("Dados do produto: " . json_encode($productData));
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
                        <h1 class="display-4 fw-bold mb-24">Pagamento em Processamento</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Seu pagamento está sendo analisado
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Resultado -->
        <section class="py-80">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-40 text-center">
                                <div class="mb-32">
                                    <i class="ri-time-line text-warning" style="font-size: 4rem;"></i>
                                </div>
                                <h3 class="mb-24">Pagamento Pendente</h3>
                                <p class="text-lg mb-32">
                                    Seu pagamento está sendo processado e será analisado em breve. 
                                    Você receberá uma confirmação por e-mail assim que for aprovado.
                                </p>
                                
                                <?php if ($paymentData): ?>
                                <div class="alert alert-light mb-32">
                                    <h6 class="alert-heading">Detalhes do Pagamento</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>ID do Pagamento:</strong><br>
                                            <code><?= htmlspecialchars($paymentData['transaction_id']) ?></code>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Valor:</strong><br>
                                            R$ <?= number_format($paymentData['amount'], 2, ',', '.') ?>
                                        </div>
                                    </div>
                                    <?php if ($productData): ?>
                                    <div class="row mt-16">
                                        <div class="col-12">
                                            <strong>Produto:</strong><br>
                                            <?= htmlspecialchars($productData['name']) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="alert alert-info mb-32">
                                    <h6 class="alert-heading">O que acontece agora?</h6>
                                    <ul class="mb-0 text-start">
                                        <li>Seu pagamento será analisado pelo processador</li>
                                        <li>Você receberá uma confirmação por e-mail</li>
                                        <li>O acesso ao produto será liberado automaticamente</li>
                                        <li>Você pode acompanhar o status no seu histórico</li>
                                    </ul>
                                </div>
                                
                                <div class="alert alert-warning mb-32">
                                    <div class="d-flex align-items-center">
                                        <i class="ri-refresh-line me-8"></i>
                                        <div>
                                            <div>Esta página será atualizada automaticamente quando o status do pagamento mudar.</div>
                                            <small class="text-muted" id="checking-time">Iniciando verificação...</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-40">
                                    <a href="produtos.php" class="btn btn-primary me-16">
                                        <i class="ri-arrow-left-line me-8"></i>
                                        Voltar aos Produtos
                                    </a>
                                    <a href="historico-pagamentos.php" class="btn btn-outline-secondary">
                                        <i class="ri-history-line me-8"></i>
                                        Ver Histórico
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include './partials/scripts.php' ?>

    <script>
    // Verificação automática do status do pagamento
    let checkCount = 0;
    const maxChecks = 30; // Verificar por 5 minutos (30 x 10 segundos)
    
    function checkPaymentStatus() {
        checkCount++;
        
        if (checkCount > maxChecks) {
            console.log('Parou de verificar - máximo de tentativas atingido');
            return;
        }
        
        console.log(`Verificando status do pagamento... (tentativa ${checkCount}/${maxChecks})`);
        
        // Fazer requisição para verificar o status
        fetch('check_payment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                payment_id: '<?= $payment_id ?>',
                external_reference: '<?= $external_reference ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Resposta do status:', data);
            
            if (data.status === 'completed') {
                // Pagamento aprovado - redirecionar para success
                window.location.href = `success.php?payment_id=<?= $payment_id ?>&preference_id=<?= $paymentData['transaction_id'] ?? '' ?>`;
            } else if (data.status === 'cancelled') {
                // Pagamento cancelado - redirecionar para failure
                window.location.href = `failure.php?payment_id=<?= $payment_id ?>&preference_id=<?= $paymentData['transaction_id'] ?? '' ?>`;
            } else if (data.status === 'pending') {
                // Ainda pendente - continuar verificando
                setTimeout(checkPaymentStatus, 10000); // Verificar novamente em 10 segundos
            }
        })
        .catch(error => {
            console.error('Erro ao verificar status:', error);
            // Em caso de erro, continuar verificando
            setTimeout(checkPaymentStatus, 10000);
        });
    }
    
    // Iniciar verificação após 5 segundos
    setTimeout(checkPaymentStatus, 5000);
    
    // Mostrar contador de verificação
    let secondsElapsed = 0;
    setInterval(() => {
        secondsElapsed += 1;
        const minutes = Math.floor(secondsElapsed / 60);
        const seconds = secondsElapsed % 60;
        const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // Atualizar texto se existir elemento
        const timeElement = document.getElementById('checking-time');
        if (timeElement) {
            timeElement.textContent = `Verificando há ${timeString}...`;
        }
    }, 1000);
    </script>

</body>
</html>

