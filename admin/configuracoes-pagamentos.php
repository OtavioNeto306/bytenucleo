<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Config.php';

$auth = new Auth($pdo);
$config = new Config($pdo);

// Verificar se usuário está logado e tem permissão
if (!$auth->isLoggedIn() || !$auth->hasPermission('manage_payments')) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $message = '';
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_mercadopago':
                $config->set('mercadopago_enabled', $_POST['mercadopago_enabled'] ?? '0');
                $config->set('mercadopago_public_key', $_POST['mercadopago_public_key'] ?? '');
                $config->set('mercadopago_access_token', $_POST['mercadopago_access_token'] ?? '');
                $config->set('mercadopago_sandbox', $_POST['mercadopago_sandbox'] ?? '0');
                $success = true;
                $message = 'Configurações do Mercado Pago salvas com sucesso!';
                break;
                
            case 'save_offline':
                $config->set('offline_payments_enabled', $_POST['offline_payments_enabled'] ?? '0');
                $config->set('pix_enabled', $_POST['pix_enabled'] ?? '0');
                $config->set('pix_key', $_POST['pix_key'] ?? '');
                $config->set('pix_key_type', $_POST['pix_key_type'] ?? 'email');
                $config->set('bank_transfer_enabled', $_POST['bank_transfer_enabled'] ?? '0');
                $config->set('bank_info', $_POST['bank_info'] ?? '');
                $success = true;
                $message = 'Configurações de pagamentos offline salvas com sucesso!';
                break;
        }
    }
    
    if ($success) {
        $successMessage = $message;
    }
}

// Obter configurações atuais
$paymentConfigs = $config->getGrouped()['payment'];
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include '../partials/head.php' ?>

<body>

    <?php include '../partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include '../partials/navbar.php' ?>

        <!-- Breadcrumb -->
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Configurações de Pagamentos</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="configuracoes.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Configurações
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Pagamentos</li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Configurações de Pagamentos</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Configure os métodos de pagamento disponíveis para seus usuários
                        </p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Mensagem de Sucesso -->
        <?php if (isset($successMessage)): ?>
        <section class="py-16">
            <div class="container">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ri-check-line me-8"></i>
                    <?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Configurações -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <!-- Checkboxes Principais -->
                        <div class="card border-0 shadow-sm mb-32">
                            <div class="card-header bg-base">
                                <h5 class="mb-0">Métodos de Pagamento</h5>
                            </div>
                            <div class="card-body p-32">
                                <!-- Mercado Pago -->
                                <div class="form-check mb-24">
                                    <input type="checkbox" class="form-check-input" id="mercadopago_enabled" 
                                           <?= ($paymentConfigs['mercadopago_enabled'] == '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="mercadopago_enabled">
                                        <i class="ri-bank-card-line me-8 text-primary"></i>
                                        Mercado Pago Automático
                                    </label>
                                    <small class="text-neutral-600 d-block mt-4">
                                        Processamento automático de pagamentos via Mercado Pago (PIX e Cartão)
                                    </small>
                                </div>
                                
                                <!-- Pagamentos Offline -->
                                <div class="form-check mb-24">
                                    <input type="checkbox" class="form-check-input" id="offline_payments_enabled" 
                                           <?= ($paymentConfigs['offline_payments_enabled'] == '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="offline_payments_enabled">
                                        <i class="ri-bank-line me-8 text-warning"></i>
                                        Pagamentos Offline
                                    </label>
                                    <small class="text-neutral-600 d-block mt-4">
                                        Métodos de pagamento manual (PIX, Transferência, Boleto, etc.)
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Configurações do Mercado Pago (Expandível) -->
                        <div class="card border-0 shadow-sm mb-32" id="mercadopago_config" style="display: none;">
                            <div class="card-header bg-base">
                                <h5 class="mb-0">
                                    <i class="ri-bank-card-line me-8 text-primary"></i>
                                    Configurações do Mercado Pago
                                </h5>
                            </div>
                            <div class="card-body p-32">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="save_mercadopago">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-24">
                                            <label class="form-label fw-bold">Chave Pública</label>
                                            <input type="text" name="mercadopago_public_key" class="form-control" 
                                                   value="<?= htmlspecialchars($paymentConfigs['mercadopago_public_key']) ?>"
                                                   placeholder="TEST-12345678-1234-1234-1234-123456789012">
                                            <small class="text-neutral-600">Chave pública do Mercado Pago</small>
                                        </div>
                                        <div class="col-md-6 mb-24">
                                            <label class="form-label fw-bold">Token de Acesso</label>
                                            <input type="password" name="mercadopago_access_token" class="form-control" 
                                                   value="<?= htmlspecialchars($paymentConfigs['mercadopago_access_token']) ?>"
                                                   placeholder="TEST-12345678901234567890123456789012-123456-123456">
                                            <small class="text-neutral-600">Token de acesso do Mercado Pago</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-check mb-24">
                                        <input type="checkbox" name="mercadopago_sandbox" class="form-check-input" id="mercadopago_sandbox" 
                                               <?= ($paymentConfigs['mercadopago_sandbox'] == '1') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="mercadopago_sandbox">
                                            Modo Sandbox (Testes)
                                        </label>
                                        <small class="text-neutral-600 d-block mt-4">
                                            Marque para usar o ambiente de testes do Mercado Pago
                                        </small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ri-save-line me-8"></i>
                                        Salvar Configurações do Mercado Pago
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Configurações de Pagamento Offline (Expandível) -->
                        <div class="card border-0 shadow-sm mb-32" id="offline_config" style="display: none;">
                            <div class="card-header bg-base">
                                <h5 class="mb-0">
                                    <i class="ri-bank-line me-8 text-warning"></i>
                                    Configurações de Pagamento Offline
                                </h5>
                            </div>
                            <div class="card-body p-32">
                                <!-- Informações Gerais -->
                                <div class="alert alert-info mb-32">
                                    <div class="d-flex align-items-start">
                                        <i class="ri-information-line me-12 mt-2"></i>
                                        <div>
                                            <h6 class="alert-heading mb-8">Configurações de Pagamento Offline</h6>
                                            <p class="mb-0">
                                                Configure aqui os métodos de pagamento offline disponíveis para seus usuários. 
                                                Cada método pode ser ativado ou desativado individualmente. 
                                                As informações configuradas aqui serão exibidas na página de pagamento para os usuários.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="save_offline">
                                    
                                    <!-- Configurações PIX -->
                                    <div class="card border-0 bg-light mb-32">
                                        <div class="card-body p-24">
                                            <div class="form-check mb-16">
                                                <input type="checkbox" name="pix_enabled" class="form-check-input" id="pix_enabled" 
                                                       <?= ($paymentConfigs['pix_enabled'] == '1') ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold" for="pix_enabled">
                                                    <i class="ri-qr-code-line me-8 text-success"></i>
                                                    Habilitar Pagamento PIX
                                                </label>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-16">
                                                    <label class="form-label">Tipo de Chave PIX</label>
                                                    <select name="pix_key_type" class="form-select">
                                                        <option value="email" <?= ($paymentConfigs['pix_key_type'] == 'email') ? 'selected' : '' ?>>Email</option>
                                                        <option value="cpf" <?= ($paymentConfigs['pix_key_type'] == 'cpf') ? 'selected' : '' ?>>CPF</option>
                                                        <option value="cnpj" <?= ($paymentConfigs['pix_key_type'] == 'cnpj') ? 'selected' : '' ?>>CNPJ</option>
                                                        <option value="phone" <?= ($paymentConfigs['pix_key_type'] == 'phone') ? 'selected' : '' ?>>Telefone</option>
                                                        <option value="random" <?= ($paymentConfigs['pix_key_type'] == 'random') ? 'selected' : '' ?>>Chave Aleatória</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-16">
                                                    <label class="form-label">Chave PIX</label>
                                                    <input type="text" name="pix_key" class="form-control" 
                                                           value="<?= htmlspecialchars($paymentConfigs['pix_key']) ?>"
                                                           placeholder="Digite sua chave PIX">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Configurações Bancárias -->
                                    <div class="card border-0 bg-light mb-32">
                                        <div class="card-body p-24">
                                            <div class="form-check mb-16">
                                                <input type="checkbox" name="bank_transfer_enabled" class="form-check-input" id="bank_transfer_enabled" 
                                                       <?= ($paymentConfigs['bank_transfer_enabled'] == '1') ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold" for="bank_transfer_enabled">
                                                    <i class="ri-bank-line me-8 text-info"></i>
                                                    Habilitar Transferência Bancária
                                                </label>
                                            </div>
                                            
                                            <div class="mb-16">
                                                <label class="form-label">Informações Bancárias</label>
                                                <textarea name="bank_info" class="form-control" rows="4"
                                                          placeholder="Digite as informações bancárias (banco, agência, conta, titular, etc.)"><?= htmlspecialchars($paymentConfigs['bank_info']) ?></textarea>
                                                <small class="text-neutral-600">
                                                    Ex: Banco: Nome do Banco | Agência: 0001 | Conta: 123456-7 | Titular: Nome Completo
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ri-save-line me-8"></i>
                                        Salvar Configurações Offline
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include '../partials/scripts.php' ?>

    <script>
    // Controlar exibição das configurações baseado nos checkboxes
    function toggleConfigSections() {
        const mercadopagoEnabled = document.getElementById('mercadopago_enabled').checked;
        const offlineEnabled = document.getElementById('offline_payments_enabled').checked;
        
        // Mostrar/ocultar configurações do Mercado Pago
        const mercadopagoConfig = document.getElementById('mercadopago_config');
        if (mercadopagoEnabled) {
            mercadopagoConfig.style.display = 'block';
        } else {
            mercadopagoConfig.style.display = 'none';
        }
        
        // Mostrar/ocultar configurações offline
        const offlineConfig = document.getElementById('offline_config');
        if (offlineEnabled) {
            offlineConfig.style.display = 'block';
        } else {
            offlineConfig.style.display = 'none';
        }
    }
    
    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar estado
        toggleConfigSections();
        
        // Adicionar listeners aos checkboxes
        document.getElementById('mercadopago_enabled').addEventListener('change', toggleConfigSections);
        document.getElementById('offline_payments_enabled').addEventListener('change', toggleConfigSections);
    });
    </script>

</body>
</html>
