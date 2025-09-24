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
    $orderId = $_POST['order_id'] ?? null;
    $adminNotes = $_POST['admin_notes'] ?? '';
    
    if ($orderId) {
        if ($_POST['action'] === 'approve') {
            if ($payment->updateOrderStatus($orderId, 'approved', $adminNotes)) {
                $success = 'Pagamento aprovado com sucesso!';
            } else {
                $error = 'Erro ao aprovar pagamento.';
            }
        } elseif ($_POST['action'] === 'reject') {
            if ($payment->updateOrderStatus($orderId, 'rejected', $adminNotes)) {
                $success = 'Pagamento rejeitado.';
            } else {
                $error = 'Erro ao rejeitar pagamento.';
            }
        }
    }
}

// Filtros
$status = $_GET['status'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$filters = [];
if ($status) $filters['status'] = $status;
if ($paymentMethod) $filters['payment_method'] = $paymentMethod;
if ($search) $filters['search'] = $search;

// Obter pedidos (assinaturas + compras de produtos)
$orders = $payment->getAllOrders($page, 20, $filters);
$productPurchases = $payment->getAllProductPurchases($page, 20, $filters);

// Combinar pedidos, mas evitar duplicação
$allPayments = [];
$processedOrderNumbers = [];

// Adicionar pedidos de assinatura (sempre únicos)
foreach ($orders as $order) {
    if ($order['order_type'] === 'subscription') {
        $allPayments[] = $order;
        $processedOrderNumbers[] = $order['order_number'] ?? $order['id'];
    }
}

// Adicionar pedidos de produto (orders) apenas se não foram aprovados ainda
foreach ($orders as $order) {
    if ($order['order_type'] === 'product' && $order['payment_status'] !== 'approved') {
        $allPayments[] = $order;
        $processedOrderNumbers[] = $order['order_number'] ?? $order['id'];
    }
}

// Adicionar compras de produtos aprovadas (product_purchases)
foreach ($productPurchases as $purchase) {
    $allPayments[] = $purchase;
}

// Ordenar por data
usort($allPayments, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Paginar os resultados combinados
$totalItems = count($allPayments);
$offset = ($page - 1) * 20;
$paginatedPayments = array_slice($allPayments, $offset, 20);

// Obter estatísticas
$stats = $payment->getOrderStats();
$productStats = $payment->getProductPurchaseStats();
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
                        <h1 class="display-4 fw-bold mb-24">Gerenciar Pagamentos</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Aprove ou rejeite pagamentos pendentes
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Estatísticas -->
        <section class="py-40">
            <div class="container">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32">
                                <div class="d-flex align-items-center">
                                    <div class="me-16">
                                        <i class="ri-shopping-cart-line text-primary" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-4"><?= ($stats['total_orders'] ?? 0) + ($productStats['total_purchases'] ?? 0) ?></h4>
                                        <p class="text-neutral-600 mb-0">Total de Pagamentos</p>
                                        <small class="text-neutral-600"><?= $stats['total_orders'] ?? 0 ?> assinaturas + <?= $productStats['total_purchases'] ?? 0 ?> produtos</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32">
                                <div class="d-flex align-items-center">
                                    <div class="me-16">
                                        <i class="ri-time-line text-warning" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-4"><?= ($stats['pending_orders'] ?? 0) + ($productStats['pending_purchases'] ?? 0) ?></h4>
                                        <p class="text-neutral-600 mb-0">Pendentes</p>
                                        <small class="text-neutral-600"><?= $stats['pending_orders'] ?? 0 ?> assinaturas + <?= $productStats['pending_purchases'] ?? 0 ?> produtos</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32">
                                <div class="d-flex align-items-center">
                                    <div class="me-16">
                                        <i class="ri-check-line text-success" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-4"><?= ($stats['approved_orders'] ?? 0) + ($productStats['completed_purchases'] ?? 0) ?></h4>
                                        <p class="text-neutral-600 mb-0">Aprovados</p>
                                        <small class="text-neutral-600"><?= $stats['approved_orders'] ?? 0 ?> assinaturas + <?= $productStats['completed_purchases'] ?? 0 ?> produtos</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32">
                                <div class="d-flex align-items-center">
                                    <div class="me-16">
                                        <i class="ri-money-dollar-circle-line text-primary" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-4">R$ <?= number_format(($stats['total_revenue'] ?? 0) + ($productStats['total_revenue'] ?? 0), 2, ',', '.') ?></h4>
                                        <p class="text-neutral-600 mb-0">Receita Total</p>
                                        <small class="text-neutral-600">R$ <?= number_format($stats['total_revenue'] ?? 0, 2, ',', '.') ?> assinaturas + R$ <?= number_format($productStats['total_revenue'] ?? 0, 2, ',', '.') ?> produtos</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filtros -->
        <section class="py-40 border-bottom">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Buscar pedidos..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">Todos os status</option>
                                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Aprovado</option>
                                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completo</option>
                                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejeitado</option>
                                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="payment_method" class="form-select">
                                    <option value="">Todos os métodos</option>
                                    <option value="pix" <?= $paymentMethod === 'pix' ? 'selected' : '' ?>>PIX</option>
                                    <option value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'selected' : '' ?>>Transferência</option>
                                    <option value="boleto" <?= $paymentMethod === 'boleto' ? 'selected' : '' ?>>Boleto</option>
                                    <option value="card" <?= $paymentMethod === 'card' ? 'selected' : '' ?>>Cartão</option>
                                    <option value="all" <?= $paymentMethod === 'all' ? 'selected' : '' ?>>Mercado Pago</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                            </div>
                            <div class="col-md-3">
                                <a href="pagamentos.php" class="btn btn-outline-primary w-100">Limpar Filtros</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Lista de Pedidos -->
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

                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base p-32">
                                <h4 class="mb-0">Pedidos de Pagamento</h4>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($paginatedPayments)): ?>
                                <div class="p-40 text-center">
                                    <i class="ri-inbox-line text-neutral-400" style="font-size: 3rem;"></i>
                                    <h5 class="mt-16 mb-8">Nenhum pagamento encontrado</h5>
                                    <p class="text-neutral-600">Não há pagamentos que correspondam aos filtros aplicados.</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table bordered-table sm-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Tipo</th>
                                                <th>ID</th>
                                                <th>Cliente</th>
                                                <th>Produto/Plano</th>
                                                <th>Valor</th>
                                                <th>Método</th>
                                                <th>Status</th>
                                                <th>Data</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($paginatedPayments as $payment): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($payment['payment_source'] === 'product'): ?>
                                                        <span class="badge bg-info-subtle text-info">Produto</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary-subtle text-primary">Assinatura</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $displayId = $payment['order_number'] ?? $payment['transaction_id'] ?? $payment['id'];
                                                    $fullId = $displayId;
                                                    $isLongId = strlen($displayId) > 20;
                                                    ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <strong>#<?= $isLongId ? htmlspecialchars(substr($displayId, 0, 20)) . '...' : htmlspecialchars($displayId) ?></strong>
                                                        <?php if ($isLongId): ?>
                                                        <button class="btn btn-sm btn-link p-0 text-primary" 
                                                                onclick="toggleIdDisplay(this, '<?= htmlspecialchars($fullId) ?>')"
                                                                title="Clique para ver completo">
                                                            <i class="ri-eye-line"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (isset($payment['total_items'])): ?>
                                                    <br>
                                                    <small class="text-neutral-600">
                                                        <?= $payment['total_items'] ?> item(s)
                                                    </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($payment['user_name']) ?></strong>
                                                        <br>
                                                        <?php 
                                                        $email = $payment['user_email'];
                                                        $isLongEmail = strlen($email) > 25;
                                                        ?>
                                                        <small class="text-neutral-600">
                                                            <?php if ($isLongEmail): ?>
                                                                <span class="d-inline-block" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($email) ?>">
                                                                    <?= htmlspecialchars($email) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($email) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    // Determinar o nome do item baseado no tipo
                                                    if (isset($payment['payment_source']) && $payment['payment_source'] === 'product') {
                                                        // Compra de produto via Mercado Pago
                                                        $itemName = $payment['item_name'] ?? 'Produto';
                                                    } else {
                                                        // Pedido offline ou assinatura
                                                        if (!empty($payment['item_name'])) {
                                                            $itemName = $payment['item_name'];
                                                        } else {
                                                            // Fallback baseado no order_type
                                                            $itemName = ($payment['order_type'] === 'product') ? 'Produto' : 'Plano de Assinatura';
                                                        }
                                                    }
                                                    $isLongName = strlen($itemName) > 30;
                                                    ?>
                                                    <?php if ($isLongName): ?>
                                                        <strong title="<?= htmlspecialchars($itemName) ?>">
                                                            <?= htmlspecialchars(substr($itemName, 0, 30)) ?>...
                                                        </strong>
                                                    <?php else: ?>
                                                        <strong><?= htmlspecialchars($itemName) ?></strong>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong class="text-primary">
                                                        R$ <?= number_format($payment['total_amount'] ?? $payment['amount'], 2, ',', '.') ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-neutral-100 text-neutral-700">
                                                        <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $paymentStatus = $payment['payment_status'] ?? $payment['status'];
                                                    if ($paymentStatus === 'pending'): ?>
                                                        <span class="badge bg-warning-subtle text-warning">Pendente</span>
                                                    <?php elseif ($paymentStatus === 'approved' || $paymentStatus === 'completed'): ?>
                                                        <span class="badge bg-success-subtle text-success">Aprovado</span>
                                                    <?php elseif ($paymentStatus === 'rejected'): ?>
                                                        <span class="badge bg-danger-subtle text-danger">Rejeitado</span>
                                                    <?php elseif ($paymentStatus === 'cancelled'): ?>
                                                        <span class="badge bg-neutral-100 text-neutral-700">Cancelado</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-neutral-100 text-neutral-700"><?= ucfirst($paymentStatus) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            <i class="ri-more-2-fill"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item d-flex align-items-center gap-3 text-neutral-600" 
                                                                   href="#" onclick="viewPayment(<?= $payment['id'] ?>, '<?= $payment['payment_source'] ?? 'subscription' ?>')">
                                                                    <i class="ri-eye-line me-2"></i>Ver Detalhes
                                                                </a>
                                                            </li>
                                                            <?php if ($paymentStatus === 'pending'): ?>
                                                            <li>
                                                                <a class="dropdown-item d-flex align-items-center gap-3 text-neutral-600" 
                                                                   href="#" onclick="approveOrder(<?= $payment['id'] ?>)">
                                                                    <i class="ri-check-line me-2"></i>Aprovar
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item d-flex align-items-center gap-3 text-neutral-600" 
                                                                   href="#" onclick="rejectOrder(<?= $payment['id'] ?>)">
                                                                    <i class="ri-close-line me-2"></i>Rejeitar
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php include '../partials/footer.php' ?>
    </main>

    <!-- Modal de Detalhes do Pedido -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderModalBody">
                    <!-- Conteúdo carregado via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Aprovação/Rejeição -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle">Ação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="actionOrderId">
                        <input type="hidden" name="action" id="actionType">
                        
                        <div class="mb-24">
                            <label for="admin_notes" class="form-label">Observações (opcional)</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                      placeholder="Adicione observações sobre esta ação..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="actionButton">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../partials/scripts.php' ?>

    <script>
        // Função para expandir/contrair IDs longos
        function toggleIdDisplay(button, fullId) {
            const strongElement = button.previousElementSibling;
            const icon = button.querySelector('i');
            
            if (strongElement.textContent.includes('...')) {
                // Expandir
                strongElement.textContent = '#' + fullId;
                icon.className = 'ri-eye-off-line';
                button.title = 'Clique para ocultar';
            } else {
                // Contrair
                strongElement.textContent = '#' + fullId.substring(0, 20) + '...';
                icon.className = 'ri-eye-line';
                button.title = 'Clique para ver completo';
            }
        }

        function viewPayment(paymentId, paymentType) {
            // Debug temporário
            console.log('DEBUG: viewPayment chamado com:', { paymentId, paymentType });
            
            // Carregar detalhes do pagamento via AJAX
            const url = paymentType === 'product' 
                ? `ajax/get-product-purchase-details.php?id=${paymentId}`
                : `ajax/get-order-details.php?id=${paymentId}`;
                
            console.log('DEBUG: URL sendo chamada:', url);
                
            fetch(url)
                .then(response => {
                    console.log('DEBUG: Response status:', response.status);
                    return response.text();
                })
                .then(html => {
                    console.log('DEBUG: HTML recebido:', html);
                    document.getElementById('orderModalBody').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('orderModal')).show();
                })
                .catch(error => {
                    console.error('DEBUG: Erro no fetch:', error);
                    document.getElementById('orderModalBody').innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes: ' + error + '</div>';
                    new bootstrap.Modal(document.getElementById('orderModal')).show();
                });
        }

        function viewOrder(orderId) {
            // Função de compatibilidade para assinaturas
            viewPayment(orderId, 'subscription');
        }

        function approveOrder(orderId) {
            document.getElementById('actionModalTitle').textContent = 'Aprovar Pagamento';
            document.getElementById('actionOrderId').value = orderId;
            document.getElementById('actionType').value = 'approve';
            document.getElementById('actionButton').className = 'btn btn-success';
            document.getElementById('actionButton').textContent = 'Aprovar';
            new bootstrap.Modal(document.getElementById('actionModal')).show();
        }

        function rejectOrder(orderId) {
            document.getElementById('actionModalTitle').textContent = 'Rejeitar Pagamento';
            document.getElementById('actionOrderId').value = orderId;
            document.getElementById('actionType').value = 'reject';
            document.getElementById('actionButton').className = 'btn btn-danger';
            document.getElementById('actionButton').textContent = 'Rejeitar';
            new bootstrap.Modal(document.getElementById('actionModal')).show();
        }
    </script>


</body>
</html>
