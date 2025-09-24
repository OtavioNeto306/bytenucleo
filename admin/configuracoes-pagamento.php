<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Payment.php';

$auth = new Auth($pdo);
$payment = new Payment($pdo);

// Verificar se é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Processar ações
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_payment_setting') {
        $settingId = $_POST['setting_id'] ?? null;
        $data = [
            'payment_type' => $_POST['payment_type'],
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'pix_key' => $_POST['pix_key'] ?? null,
            'pix_key_type' => $_POST['pix_key_type'] ?? null,
            'bank_name' => $_POST['bank_name'] ?? null,
            'bank_agency' => $_POST['bank_agency'] ?? null,
            'bank_account' => $_POST['bank_account'] ?? null,
            'bank_account_type' => $_POST['bank_account_type'] ?? null,
            'account_holder' => $_POST['account_holder'] ?? null,
            'account_document' => $_POST['account_document'] ?? null,
            'boleto_instructions' => $_POST['boleto_instructions'] ?? null,
            'card_instructions' => $_POST['card_instructions'] ?? null,
            'is_active' => isset($_POST['is_active']) ? true : false
        ];
        
        if ($payment->updatePaymentSetting($settingId, $data)) {
            $success = 'Configuração de pagamento atualizada com sucesso!';
        } else {
            $error = 'Erro ao atualizar configuração de pagamento.';
        }
    }
}

// Obter configurações de pagamento
$paymentSettings = $payment->getActivePaymentSettings();
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include '../partials/head.php' ?>

<body>

    <?php include '../partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include '../partials/navbar.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Configurações de Pagamento</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Configure os métodos de pagamento disponíveis para os usuários
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Configurações -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="ri-check-line me-2"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="ri-error-warning-line me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>

                        <!-- PIX -->
                        <div class="card border-0 shadow-sm mb-32">
                            <div class="card-header bg-base p-32">
                                <div class="d-flex align-items-center">
                                    <i class="ri-qr-code-line text-primary me-16" style="font-size: 1.5rem;"></i>
                                    <h4 class="mb-0">Configuração PIX</h4>
                                </div>
                            </div>
                            <div class="card-body p-32">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_payment_setting">
                                    <input type="hidden" name="setting_id" value="1">
                                    <input type="hidden" name="payment_type" value="pix">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="pix_title" class="form-label">Título do Método</label>
                                                <input type="text" class="form-control" id="pix_title" name="title" 
                                                       value="Pagamento via PIX" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="pix_description" class="form-label">Descrição</label>
                                                <input type="text" class="form-control" id="pix_description" name="description" 
                                                       value="Pagamento instantâneo via PIX" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="pix_key" class="form-label">Chave PIX</label>
                                                <input type="text" class="form-control" id="pix_key" name="pix_key" 
                                                       placeholder="exemplo@email.com ou 123.456.789-00">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="pix_key_type" class="form-label">Tipo da Chave</label>
                                                <select class="form-select" id="pix_key_type" name="pix_key_type">
                                                    <option value="email">Email</option>
                                                    <option value="cpf">CPF</option>
                                                    <option value="cnpj">CNPJ</option>
                                                    <option value="phone">Telefone</option>
                                                    <option value="random">Chave Aleatória</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-24">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="pix_active" name="is_active" checked>
                                            <label class="form-check-label" for="pix_active">
                                                Ativar método PIX
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="ri-save-line me-2"></i>
                                        Salvar Configuração PIX
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Transferência Bancária -->
                        <div class="card border-0 shadow-sm mb-32">
                            <div class="card-header bg-base p-32">
                                <div class="d-flex align-items-center">
                                    <i class="ri-bank-line text-primary me-16" style="font-size: 1.5rem;"></i>
                                    <h4 class="mb-0">Configuração Transferência Bancária</h4>
                                </div>
                            </div>
                            <div class="card-body p-32">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_payment_setting">
                                    <input type="hidden" name="setting_id" value="2">
                                    <input type="hidden" name="payment_type" value="bank_transfer">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="bank_title" class="form-label">Título do Método</label>
                                                <input type="text" class="form-control" id="bank_title" name="title" 
                                                       value="Transferência Bancária" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="bank_description" class="form-label">Descrição</label>
                                                <input type="text" class="form-control" id="bank_description" name="description" 
                                                       value="Transferência para conta bancária" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="bank_name" class="form-label">Nome do Banco</label>
                                                <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                                       placeholder="Ex: Banco do Brasil">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="bank_agency" class="form-label">Agência</label>
                                                <input type="text" class="form-control" id="bank_agency" name="bank_agency" 
                                                       placeholder="Ex: 0001">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="bank_account" class="form-label">Conta</label>
                                                <input type="text" class="form-control" id="bank_account" name="bank_account" 
                                                       placeholder="Ex: 123456-7">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="bank_account_type" class="form-label">Tipo de Conta</label>
                                                <select class="form-select" id="bank_account_type" name="bank_account_type">
                                                    <option value="corrente">Conta Corrente</option>
                                                    <option value="poupanca">Conta Poupança</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="account_holder" class="form-label">Titular da Conta</label>
                                                <input type="text" class="form-control" id="account_holder" name="account_holder" 
                                                       placeholder="Nome do titular">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="account_document" class="form-label">CPF/CNPJ</label>
                                                <input type="text" class="form-control" id="account_document" name="account_document" 
                                                       placeholder="00.000.000/0001-00">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-24">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bank_active" name="is_active">
                                            <label class="form-check-label" for="bank_active">
                                                Ativar método Transferência Bancária
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="ri-save-line me-2"></i>
                                        Salvar Configuração Bancária
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Boleto -->
                        <div class="card border-0 shadow-sm mb-32">
                            <div class="card-header bg-base p-32">
                                <div class="d-flex align-items-center">
                                    <i class="ri-file-text-line text-primary me-16" style="font-size: 1.5rem;"></i>
                                    <h4 class="mb-0">Configuração Boleto</h4>
                                </div>
                            </div>
                            <div class="card-body p-32">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_payment_setting">
                                    <input type="hidden" name="setting_id" value="3">
                                    <input type="hidden" name="payment_type" value="boleto">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="boleto_title" class="form-label">Título do Método</label>
                                                <input type="text" class="form-control" id="boleto_title" name="title" 
                                                       value="Boleto Bancário" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="boleto_description" class="form-label">Descrição</label>
                                                <input type="text" class="form-control" id="boleto_description" name="description" 
                                                       value="Pagamento via boleto bancário" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-24">
                                        <label for="boleto_instructions" class="form-label">Instruções do Boleto</label>
                                        <textarea class="form-control" id="boleto_instructions" name="boleto_instructions" rows="3" 
                                                  placeholder="Instruções que aparecerão no boleto..."></textarea>
                                    </div>

                                    <div class="mb-24">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="boleto_active" name="is_active">
                                            <label class="form-check-label" for="boleto_active">
                                                Ativar método Boleto
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="ri-save-line me-2"></i>
                                        Salvar Configuração Boleto
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Cartão de Crédito -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base p-32">
                                <div class="d-flex align-items-center">
                                    <i class="ri-credit-card-line text-primary me-16" style="font-size: 1.5rem;"></i>
                                    <h4 class="mb-0">Configuração Cartão de Crédito</h4>
                                </div>
                            </div>
                            <div class="card-body p-32">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_payment_setting">
                                    <input type="hidden" name="setting_id" value="4">
                                    <input type="hidden" name="payment_type" value="card">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="card_title" class="form-label">Título do Método</label>
                                                <input type="text" class="form-control" id="card_title" name="title" 
                                                       value="Cartão de Crédito" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-24">
                                                <label for="card_description" class="form-label">Descrição</label>
                                                <input type="text" class="form-control" id="card_description" name="description" 
                                                       value="Pagamento via cartão de crédito" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-24">
                                        <label for="card_instructions" class="form-label">Instruções do Cartão</label>
                                        <textarea class="form-control" id="card_instructions" name="card_instructions" rows="3" 
                                                  placeholder="Instruções para pagamento via cartão..."></textarea>
                                    </div>

                                    <div class="mb-24">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="card_active" name="is_active">
                                            <label class="form-check-label" for="card_active">
                                                Ativar método Cartão de Crédito
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="ri-save-line me-2"></i>
                                        Salvar Configuração Cartão
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php include '../partials/footer.php' ?>
    </main>

    <?php include '../partials/scripts.php' ?>

</body>
</html>
