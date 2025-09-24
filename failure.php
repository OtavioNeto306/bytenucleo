<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/failure_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

saveLog("=== FAILURE PAGE INICIADA ===");

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
$error = $_GET['error'] ?? null;

saveLog("Payment ID: $paymentId");
saveLog("Preference ID: $preferenceId");
saveLog("Status original: '$status' (tamanho: " . strlen($status) . ")");
saveLog("Error: $error");

// Se temos dados do pagamento, atualizar status
if ($paymentId && $preferenceId) {
    saveLog("Atualizando status da transação para falha...");
    
    // Se o status estiver vazio, usar 'cancelled' (pois estamos na página de failure)
    $finalStatus = !empty($status) ? $status : 'cancelled';
    
    // Limitar o tamanho do status para evitar truncamento (máximo 10 caracteres)
    $finalStatus = substr($finalStatus, 0, 10);
    saveLog("Status final a ser aplicado: '$finalStatus' (tamanho: " . strlen($finalStatus) . ")");
    
    // Usar a estrutura atual da tabela
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
                <h6 class="fw-semibold mb-0">Pagamento Recusado</h6>
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
                    <li class="fw-medium">Pagamento Recusado</li>
                </ul>
            </div>
        </div>

        <!-- Failure Content -->
        <section class="py-80">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="card text-center">
                            <div class="card-body py-5">
                                <div class="mb-4">
                                    <i class="ri-close-circle-line text-danger" style="font-size: 4rem;"></i>
                                </div>
                                <h3 class="card-title text-danger mb-3">Pagamento Não Aprovado</h3>
                                <p class="card-text text-muted mb-4">
                                    Ocorreu um problema com seu pagamento. 
                                    Verifique os dados e tente novamente.
                                </p>
                                
                                <?php if ($error): ?>
                                <div class="alert alert-warning">
                                    <strong>Erro:</strong> <?= htmlspecialchars($error) ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($paymentId): ?>
                                <div class="alert alert-info">
                                    <strong>ID do Pagamento:</strong> <?= htmlspecialchars($paymentId) ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <a href="produtos.php" class="btn btn-primary">
                                        <i class="ri-store-line me-2"></i>Ver Produtos
                                    </a>
                                    <a href="index-membros.php" class="btn btn-outline-secondary">
                                        <i class="ri-home-line me-2"></i>Voltar ao Início
                                    </a>
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
        console.log('⚠️ FAILURE.php carregado com sucesso!');
    </script>
</body>
</html>

