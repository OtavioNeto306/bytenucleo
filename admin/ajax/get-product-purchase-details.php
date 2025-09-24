<?php
require_once '../../config/database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/Payment.php';

$auth = new Auth($pdo);
$payment = new Payment($pdo);

// Verificar se é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    exit('Acesso negado');
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo '<div class="alert alert-danger">ID não fornecido</div>';
    exit;
}

// Debug temporário
error_log("DEBUG: Buscando product purchase com ID: $id");

$purchase = $payment->getProductPurchase($id);
if (!$purchase) {
    echo '<div class="alert alert-danger">Compra não encontrada para ID: ' . htmlspecialchars($id) . '</div>';
    exit;
}

// Debug temporário
error_log("DEBUG: Purchase encontrada: " . json_encode($purchase));
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="fw-bold mb-3">Informações da Compra</h6>
        <table class="table bordered-table sm-table">
            <tr>
                <td><strong>ID:</strong></td>
                <td><?= htmlspecialchars($purchase['id']) ?></td>
            </tr>
            <tr>
                <td><strong>Transaction ID:</strong></td>
                <td><code><?= htmlspecialchars($purchase['transaction_id']) ?></code></td>
            </tr>
            <tr>
                <td><strong>Valor:</strong></td>
                <td><strong class="text-primary">R$ <?= number_format($purchase['amount'], 2, ',', '.') ?></strong></td>
            </tr>
            <tr>
                <td><strong>Método de Pagamento:</strong></td>
                <td><span class="badge bg-neutral-100 text-neutral-700"><?= ucfirst(str_replace('_', ' ', $purchase['payment_method'])) ?></span></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <?php 
                    $status = $purchase['status'];
                    if ($status === 'pending'): ?>
                        <span class="badge bg-warning-subtle text-warning">Pendente</span>
                    <?php elseif ($status === 'completed'): ?>
                        <span class="badge bg-success-subtle text-success">Completo</span>
                    <?php elseif ($status === 'cancelled'): ?>
                        <span class="badge bg-danger-subtle text-danger">Cancelado</span>
                    <?php else: ?>
                        <span class="badge bg-neutral-100 text-neutral-700"><?= ucfirst($status) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Data de Criação:</strong></td>
                <td><?= date('d/m/Y H:i:s', strtotime($purchase['created_at'])) ?></td>
            </tr>
            <tr>
                <td><strong>Última Atualização:</strong></td>
                <td><?= date('d/m/Y H:i:s', strtotime($purchase['updated_at'])) ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="fw-bold mb-3">Informações do Cliente</h6>
        <table class="table bordered-table sm-table">
            <tr>
                <td><strong>Nome:</strong></td>
                <td><?= htmlspecialchars($purchase['user_name']) ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><?= htmlspecialchars($purchase['user_email']) ?></td>
            </tr>
            <?php if ($purchase['user_phone']): ?>
            <tr>
                <td><strong>Telefone:</strong></td>
                <td><?= htmlspecialchars($purchase['user_phone']) ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <h6 class="fw-bold mb-3 mt-4">Informações do Produto</h6>
        <table class="table bordered-table sm-table">
            <tr>
                <td><strong>Produto:</strong></td>
                <td><?= htmlspecialchars($purchase['product_name']) ?></td>
            </tr>
            <tr>
                <td><strong>ID do Produto:</strong></td>
                <td><?= htmlspecialchars($purchase['product_id']) ?></td>
            </tr>
            <?php if ($purchase['product_description']): ?>
            <tr>
                <td><strong>Descrição:</strong></td>
                <td><?= htmlspecialchars($purchase['product_description']) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($purchase['product_image']): ?>
<div class="row mt-4">
    <div class="col-12">
        <h6 class="fw-bold mb-3">Imagem do Produto</h6>
        <img src="../../<?= htmlspecialchars($purchase['product_image']) ?>" 
             alt="<?= htmlspecialchars($purchase['product_name']) ?>" 
             class="img-thumbnail" style="max-width: 200px;">
    </div>
</div>
<?php endif; ?>

<div class="row mt-4">
    <div class="col-12">
        <h6 class="fw-bold mb-3">Ações Disponíveis</h6>
        <div class="d-flex gap-2">
            <?php if ($purchase['status'] === 'pending'): ?>
                <button class="btn btn-success btn-sm" onclick="updateProductPurchaseStatus(<?= $purchase['id'] ?>, 'completed')">
                    <i class="ri-check-line me-1"></i>Marcar como Completo
                </button>
                <button class="btn btn-danger btn-sm" onclick="updateProductPurchaseStatus(<?= $purchase['id'] ?>, 'cancelled')">
                    <i class="ri-close-line me-1"></i>Cancelar
                </button>
            <?php elseif ($purchase['status'] === 'completed'): ?>
                <button class="btn btn-warning btn-sm" onclick="updateProductPurchaseStatus(<?= $purchase['id'] ?>, 'pending')">
                    <i class="ri-time-line me-1"></i>Marcar como Pendente
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateProductPurchaseStatus(id, status) {
    if (confirm(`Tem certeza que deseja alterar o status para "${status}"?`)) {
        fetch('ajax/update-product-purchase-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao atualizar status: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro ao atualizar status: ' + error);
        });
    }
}
</script>
