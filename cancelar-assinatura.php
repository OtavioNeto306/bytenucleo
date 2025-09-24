<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Subscription.php';
require_once 'includes/Logger.php';

$logger = new Logger('cancelamento.log');

// Log de teste para verificar se o arquivo está sendo carregado
$logger->info("=== PÁGINA CARREGADA ===");
$logger->info("Arquivo: " . __FILE__);
$logger->info("Timestamp: " . date('Y-m-d H:i:s'));
$logger->info("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));

$auth = new Auth($pdo);
$subscription = new Subscription($pdo);

// Verificar se usuário está logado
$logger->info("Verificando se usuário está logado...");
if (!$auth->isLoggedIn()) {
    $logger->warning("Usuário não está logado - redirecionando para login");
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user_id'];
$logger->info("Usuário logado - ID: " . $userId);

$currentSubscription = $auth->getActiveSubscription();
$logger->info("Assinatura atual: " . ($currentSubscription ? 'Encontrada' : 'Não encontrada'));

// Se não tem assinatura ativa, redirecionar
if (!$currentSubscription) {
    $logger->warning("Usuário não tem assinatura ativa - redirecionando para perfil");
    header('Location: perfil.php?error=no_subscription');
    exit;
}

$successMessage = '';
$errorMessage = '';

// Processar cancelamento
$logger->info("Verificando se formulário foi submetido...");
$logger->debug("Método HTTP: " . $_SERVER['REQUEST_METHOD']);
$logger->debug("POST data: " . print_r($_POST, true));

if (isset($_POST['confirm_cancel'])) {
    $logger->info("=== INÍCIO DO PROCESSAMENTO DE CANCELAMENTO ===");
    $logger->debug("POST data: " . print_r($_POST, true));
    
    $immediate = $_POST['cancel_type'] === 'immediate';
    $logger->info("Tipo de cancelamento: " . ($immediate ? 'imediato' : 'final do período'));
    $logger->info("User ID: " . $userId);
    
    // Verificar assinatura atual antes do cancelamento
    $currentSubBefore = $auth->getActiveSubscription($userId);
    $logger->debug("Assinatura atual ANTES do cancelamento: " . print_r($currentSubBefore, true));
    
    try {
        $logger->info("Chamando método cancelSubscription...");
        $result = $subscription->cancelSubscription($userId, $immediate);
        $logger->info("Resultado do cancelamento: " . ($result ? 'true' : 'false'));
        
        if ($result) {
            $successMessage = "Assinatura cancelada com sucesso!";
            $logger->info("✅ Cancelamento bem-sucedido!");
            
            // Verificar assinatura após o cancelamento
            $currentSubAfter = $auth->getActiveSubscription($userId);
            $logger->debug("Assinatura atual APÓS o cancelamento: " . print_r($currentSubAfter, true));
            
            // Verificar dados do usuário no banco
            $stmt = $pdo->prepare("SELECT current_plan_id, subscription_expires_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            $logger->debug("Dados do usuário no banco: " . print_r($userData, true));
            
            // NÃO redirecionar imediatamente - deixar mostrar a mensagem
            // O redirecionamento será feito via JavaScript
        } else {
            $errorMessage = "Erro: Não foi possível cancelar a assinatura";
            $logger->error("❌ Falha no cancelamento - resultado false");
        }
        
    } catch (Exception $e) {
        $errorMessage = "Erro ao cancelar assinatura: " . $e->getMessage();
        $logger->error("❌ Exceção no cancelamento: " . $e->getMessage());
        $logger->error("Stack trace: " . $e->getTraceAsString());
    }
    
    $logger->info("=== FIM DO PROCESSAMENTO DE CANCELAMENTO ===");
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
                <h6 class="fw-semibold mb-0">Cancelar Assinatura</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="perfil.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Meu Perfil
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Cancelar Assinatura</li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Cancelar Assinatura</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Gerencie sua assinatura atual
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Conteúdo -->
        <section class="py-80">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        
                                                 <?php if ($successMessage): ?>
                         <div class="alert alert-success mb-32">
                             <i class="ri-check-line me-2"></i>
                             <?= htmlspecialchars($successMessage) ?>
                             <div class="mt-3">
                                 <small>Redirecionando para o perfil em <span id="countdown">3</span> segundos...</small>
                             </div>
                         </div>
                         <?php endif; ?>
                        
                        <?php if ($errorMessage): ?>
                        <div class="alert alert-danger mb-32">
                            <i class="ri-error-warning-line me-2"></i>
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Informações da Assinatura -->
                        <div class="card border-0 shadow-sm mb-32">
                            <div class="card-header bg-base py-16 px-24">
                                <h3 class="mb-0">
                                    <i class="ri-information-line me-2"></i>
                                    Informações da Assinatura
                                </h3>
                            </div>
                            <div class="card-body p-32">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-8">
                                            <span class="fw-semibold text-neutral-600">Plano:</span>
                                            <span class="text-primary fw-bold"><?= htmlspecialchars($currentSubscription['plan_name']) ?></span>
                                        </p>
                                        <p class="mb-8">
                                            <span class="fw-semibold text-neutral-600">Status:</span>
                                            <span class="badge bg-success">Ativa</span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-8">
                                            <span class="fw-semibold text-neutral-600">Início:</span>
                                            <?= date('d/m/Y', strtotime($currentSubscription['start_date'])) ?>
                                        </p>
                                        <p class="mb-8">
                                            <span class="fw-semibold text-neutral-600">Expira em:</span>
                                            <?= date('d/m/Y', strtotime($currentSubscription['end_date'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Opções de Cancelamento -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24">
                                <h3 class="mb-0">
                                    <i class="ri-close-circle-line me-2"></i>
                                    Opções de Cancelamento
                                </h3>
                            </div>
                            <div class="card-body p-32">
                                
                                <div class="alert alert-warning mb-32">
                                    <i class="ri-alert-line me-2"></i>
                                    <strong>Atenção:</strong> Após o cancelamento, você perderá o acesso aos recursos exclusivos do seu plano.
                                </div>
                                
                                <form method="POST">
                                    <div class="mb-24">
                                        <div class="form-check mb-16">
                                            <input type="radio" name="cancel_type" value="end_period" id="end_period" class="form-check-input" checked>
                                            <label for="end_period" class="form-check-label">
                                                <strong>Cancelar no final do período (Recomendado)</strong>
                                                <br>
                                                <small class="text-neutral-600">
                                                    Você manterá o acesso até <?= date('d/m/Y', strtotime($currentSubscription['end_date'])) ?>. 
                                                    Após essa data, sua assinatura será encerrada.
                                                </small>
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-16">
                                            <input type="radio" name="cancel_type" value="immediate" id="immediate" class="form-check-input">
                                            <label for="immediate" class="form-check-label">
                                                <strong>Cancelar imediatamente</strong>
                                                <br>
                                                <small class="text-neutral-600">
                                                    Você perderá o acesso imediatamente. 
                                                    Não será possível reembolso.
                                                </small>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-3">
                                        <button type="submit" name="confirm_cancel" class="btn btn-danger btn-lg">
                                            <i class="ri-close-circle-line me-2"></i>
                                            Confirmar Cancelamento
                                        </button>
                                        
                                        <a href="perfil.php" class="btn btn-outline-secondary btn-lg">
                                            <i class="ri-arrow-left-line me-2"></i>
                                            Voltar ao Perfil
                                        </a>
                                    </div>
                                </form>
                                
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </section>

        <?php include './partials/footer.php' ?>
    </main>

    <?php include './partials/scripts.php' ?>

    <script>
    // Melhorar funcionalidade dos radio buttons
    document.addEventListener('DOMContentLoaded', function() {
        
        // Verificar se há mensagem de sucesso e redirecionar
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            console.log('Mensagem de sucesso encontrada - iniciando contador...');
            
            let countdown = 3;
            const countdownElement = document.getElementById('countdown');
            
            const timer = setInterval(function() {
                countdown--;
                if (countdownElement) {
                    countdownElement.textContent = countdown;
                }
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.href = 'perfil.php?canceled=1';
                }
            }, 1000);
        }
        const endPeriodRadio = document.getElementById('end_period');
        const immediateRadio = document.getElementById('immediate');
        const confirmButton = document.querySelector('button[name="confirm_cancel"]');
        
        // Função para atualizar o texto do botão baseado na seleção
        function updateButtonText() {
            if (immediateRadio.checked) {
                confirmButton.innerHTML = '<i class="ri-close-circle-line me-2"></i>Cancelar Imediatamente';
                confirmButton.classList.remove('btn-danger');
                confirmButton.classList.add('btn-warning');
            } else {
                confirmButton.innerHTML = '<i class="ri-close-circle-line me-2"></i>Confirmar Cancelamento';
                confirmButton.classList.remove('btn-warning');
                confirmButton.classList.add('btn-danger');
            }
        }
        
        // Adicionar event listeners
        endPeriodRadio.addEventListener('change', updateButtonText);
        immediateRadio.addEventListener('change', updateButtonText);
        
        // Atualizar texto inicial
        updateButtonText();
        
        // Melhorar confirmação
        confirmButton.addEventListener('click', function(e) {
            console.log('Botão de cancelamento clicado');
            
            let message = 'Tem certeza que deseja cancelar sua assinatura?';
            
            if (immediateRadio.checked) {
                message = 'ATENÇÃO: Você está cancelando imediatamente!\n\nVocê perderá o acesso AGORA e não haverá reembolso.\n\nTem certeza absoluta?';
                console.log('Cancelamento imediato selecionado');
            } else {
                console.log('Cancelamento no final do período selecionado');
            }
            
            if (!confirm(message)) {
                console.log('Usuário cancelou a confirmação');
                e.preventDefault();
                return false;
            }
            
            console.log('Usuário confirmou - submetendo formulário');
            // Se confirmou, deixar o formulário submeter normalmente
            return true;
        });
    });
    </script>

</body>
</html>

