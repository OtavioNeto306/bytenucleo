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

$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    http_response_code(400);
    exit('ID do pedido não fornecido');
}

// Obter dados do pedido
$order = $payment->getOrder($orderId);
if (!$order) {
    http_response_code(404);
    exit('Pedido não encontrado');
}

// Obter itens do pedido
$orderItems = $payment->getOrderItems($orderId);
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="mb-16 text-lg fw-semibold">Informações do Pedido</h6>
        <table class="table table-sm bordered-table">
            <tr>
                <td class="text-secondary-light"><strong>Número:</strong></td>
                <td class="text-lg">#<?= htmlspecialchars($order['order_number']) ?></td>
            </tr>
            <tr>
                <td class="text-secondary-light"><strong>Status:</strong></td>
                <td class="text-lg">
                    <?php if ($order['payment_status'] === 'pending'): ?>
                        <span class="badge bg-warning text-dark">Pendente</span>
                    <?php elseif ($order['payment_status'] === 'approved'): ?>
                        <span class="badge bg-success">Aprovado</span>
                    <?php elseif ($order['payment_status'] === 'rejected'): ?>
                        <span class="badge bg-danger">Rejeitado</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Cancelado</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="text-secondary-light"><strong>Valor Total:</strong></td>
                <td class="text-lg"><strong class="text-primary">R$ <?= number_format($order['total_amount'], 2, ',', '.') ?></strong></td>
            </tr>
            <tr>
                <td class="text-secondary-light"><strong>Método:</strong></td>
                <td class="text-lg"><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></td>
            </tr>
            <tr>
                <td class="text-secondary-light"><strong>Data:</strong></td>
                <td class="text-lg"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
            </tr>
            <tr>
                <td class="text-secondary-light"><strong>Expira:</strong></td>
                <td class="text-lg"><?= date('d/m/Y H:i', strtotime($order['expires_at'])) ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="mb-16 text-lg fw-semibold">Informações do Cliente</h6>
        <table class="table table-sm bordered-table">
            <tr>
                <td class="text-secondary-light"><strong>Nome:</strong></td>
                <td class="text-lg"><?= htmlspecialchars($order['user_name']) ?></td>
            </tr>
            <tr>
                <td class="text-secondary-light"><strong>Email:</strong></td>
                <td class="text-lg"><?= htmlspecialchars($order['user_email']) ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="row mt-32">
    <div class="col-12">
        <h6 class="mb-16 text-lg fw-semibold">Itens do Pedido</h6>
        <div class="table-responsive">
            <table class="table table-sm bordered-table">
                <thead>
                    <tr>
                        <th class="text-lg fw-semibold">Item</th>
                        <th class="text-lg fw-semibold">Tipo</th>
                        <th class="text-lg fw-semibold">Preço</th>
                        <th class="text-lg fw-semibold">Qtd</th>
                        <th class="text-lg fw-semibold">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td class="text-lg"><?= htmlspecialchars($item['item_name']) ?></td>
                        <td class="text-secondary-light">
                            <?= $item['item_type'] === 'subscription_plan' ? 'Plano de Assinatura' : 'Produto' ?>
                        </td>
                        <td class="text-lg">R$ <?= number_format($item['item_price'], 2, ',', '.') ?></td>
                        <td class="text-lg"><?= $item['quantity'] ?></td>
                        <td class="text-lg">R$ <?= number_format($item['item_price'] * $item['quantity'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($order['payment_proof_path']): ?>
<div class="row mt-32">
    <div class="col-12">
        <h6 class="mb-16 text-lg fw-semibold">Comprovante de Pagamento</h6>
        <div class="p-24 rounded">
            <p class="mb-8 text-lg">
                <strong class="text-secondary-light">Enviado em:</strong> 
                <?= date('d/m/Y H:i', strtotime($order['payment_proof_uploaded_at'])) ?>
            </p>
            <p class="mb-0 text-lg">
                <strong class="text-secondary-light">Arquivo:</strong> 
                <?= basename($order['payment_proof_path']) ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($order['admin_notes']): ?>
<div class="row mt-32">
    <div class="col-12">
        <h6 class="mb-16 text-lg fw-semibold">Observações do Administrador</h6>
        <div class="p-24 rounded">
            <p class="mb-0 text-lg"><?= nl2br(htmlspecialchars($order['admin_notes'])) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>
