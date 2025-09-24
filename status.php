<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Incluir arquivos necessários
require_once 'config/database.php';
require_once 'includes/Config.php';
require_once 'includes/Logger.php';
require_once 'includes/Auth.php';
require_once 'includes/SiteConfig.php';

$logger = new Logger('mercadopago.log');

$payment_id = $_GET['payment_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;

if (!$payment_id || !$product_id) {
    header('Location: produtos.php');
    exit;
}

// Buscar configurações do Mercado Pago
$config = new Config($pdo);
$mp_access_token = $config->get('mercadopago_access_token');
$mp_sandbox = $config->get('mercadopago_sandbox');

$mp_base_url = $mp_sandbox ? 'https://api.mercadopago.com' : 'https://api.mercadopago.com';

// Verificar status do pagamento
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $mp_base_url . '/v1/payments/' . $payment_id);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $mp_access_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$logger->info("Verificando status do pagamento $payment_id - HTTP: $http_code");

if ($http_code === 200) {
    $payment = json_decode($response, true);
    $status = $payment['status'] ?? 'unknown';
    
    $logger->info("Status do pagamento $payment_id: $status");
    
    if ($status === 'approved') {
        // Atualizar status no banco
        $stmt = $pdo->prepare("
            UPDATE product_purchases 
            SET status = 'completed', updated_at = NOW() 
            WHERE transaction_id = ? AND user_id = ?
        ");
        $stmt->execute([$payment_id, $_SESSION['user_id']]);
        
        $logger->info("Pagamento $payment_id marcado como completo no banco");
        
        // Redirecionar para sucesso
        header('Location: ' . getSuccessUrl($payment_id, $product_id));
        exit;
    } elseif ($status === 'pending') {
        // Pagamento ainda pendente
        $message = 'Pagamento ainda está sendo processado. Aguarde alguns instantes.';
    } elseif ($status === 'rejected') {
        // Pagamento rejeitado
        $message = 'Pagamento foi rejeitado. Tente novamente.';
    } else {
        $message = 'Status do pagamento: ' . $status;
    }
} else {
    $logger->error("Erro ao verificar status do pagamento $payment_id: $http_code - $response");
    $message = 'Erro ao verificar status do pagamento.';
}

// Buscar dados do produto
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

// Inicializar classes necessárias para os partials
$auth = new Auth($pdo);
$siteConfig = new SiteConfig($pdo);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include './partials/head.php' ?>

<body>

    <?php include './partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include './partials/navbar.php' ?>

        <!-- Content -->
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body p-5 text-center">
                            <div class="mb-4">
                                <i class="ri-time-line text-warning" style="font-size: 4rem;"></i>
                            </div>
                            
                            <h3 class="h4 mb-3">Status do Pagamento</h3>
                            <p class="text-muted mb-4">
                                <?= htmlspecialchars($message) ?>
                            </p>
                            
                            <div class="d-grid gap-3">
                                <button type="button" class="btn btn-primary" onclick="checkStatus()">
                                    <i class="ri-refresh-line me-2"></i>
                                    Verificar Novamente
                                </button>
                                
                                <a href="produto.php?id=<?= $product_id ?>" class="btn btn-outline-secondary">
                                    <i class="ri-arrow-left-line me-2"></i>
                                    Voltar ao Produto
                                </a>
                            </div>
                            
                            <div class="mt-4">
                                <small class="text-muted">
                                    <i class="ri-information-line me-1"></i>
                                    O pagamento PIX pode levar alguns minutos para ser confirmado.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include './partials/scripts.php' ?>
    
    <script>
        function checkStatus() {
            window.location.reload();
        }
        
        // Verificar automaticamente a cada 10 segundos
        setTimeout(function() {
            window.location.reload();
        }, 10000);
    </script>
</body>
</html>

<?php
// Função para gerar URL de sucesso
function getSuccessUrl($payment_id, $product_id) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . "://" . $host;
    return $base_url . '/success_mercadopago.php?payment_id=' . $payment_id . '&product_id=' . $product_id;
}
?>
