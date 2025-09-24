<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Subscription.php';

$auth = new Auth($pdo);
$subscription = new Subscription($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user_id'];
$user = $auth->getCurrentUser();

// Parâmetros de paginação
$page = $_GET['page'] ?? 1;
$tab = $_GET['tab'] ?? 'payments'; // payments ou subscriptions

// Obter dados baseado na aba
if ($tab === 'subscriptions') {
    $subscriptionHistory = $subscription->getUserSubscriptionHistory($userId, $page, 10);
    $data = $subscriptionHistory['subscriptions'];
    $total = $subscriptionHistory['total'];
    $pages = $subscriptionHistory['pages'];
} else {
    $paymentHistory = $subscription->getUserPaymentHistory($userId, $page, 10);
    $data = $paymentHistory['orders'];
    $total = $paymentHistory['total'];
    $pages = $paymentHistory['pages'];
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
                <h6 class="fw-semibold mb-0">Histórico de Pagamentos</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="perfil.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Meu Perfil
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Histórico de Pagamentos</li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Histórico de Pagamentos</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Acompanhe seus pagamentos e assinaturas
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Conteúdo -->
        <section class="py-80">
            <div class="container">
                
                <!-- Abas -->
                <div class="row mb-40">
                    <div class="col-12">
                        <ul class="nav nav-tabs" id="historyTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $tab === 'payments' ? 'active' : '' ?>" 
                                        id="payments-tab" 
                                        onclick="window.location.href='?tab=payments'"
                                        type="button" role="tab">
                                    <i class="ri-bank-card-line me-2"></i>
                                    Pagamentos
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $tab === 'subscriptions' ? 'active' : '' ?>" 
                                        id="subscriptions-tab" 
                                        onclick="window.location.href='?tab=subscriptions'"
                                        type="button" role="tab">
                                    <i class="ri-calendar-line me-2"></i>
                                    Assinaturas
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Tabela de Dados -->
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h3 class="mb-0">
                                        <?= $tab === 'payments' ? 'Histórico de Pagamentos' : 'Histórico de Assinaturas' ?>
                                    </h3>
                                    <span class="text-neutral-600">
                                        <?= $total ?> registro<?= $total !== 1 ? 's' : '' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                
                                <?php if (empty($data)): ?>
                                <div class="text-center py-80">
                                    <i class="ri-file-list-line text-neutral-600" style="font-size: 4rem;"></i>
                                    <h4 class="mt-24 mb-16 fw-semibold">Nenhum registro encontrado</h4>
                                    <p class="text-neutral-600 mb-32">
                                        <?= $tab === 'payments' ? 
                                            'Você ainda não realizou nenhum pagamento.' : 
                                            'Você ainda não possui histórico de assinaturas.' ?>
                                    </p>
                                    <a href="<?= $tab === 'payments' ? 'planos.php' : 'perfil.php' ?>" class="btn btn-primary">
                                        <?= $tab === 'payments' ? 'Ver Planos' : 'Voltar ao Perfil' ?>
                                    </a>
                                </div>
                                <?php else: ?>
                                
                                <div class="table-responsive">
                                    <table class="table bordered-table sm-table mb-0">
                                        <thead>
                                            <tr>
                                                <?php if ($tab === 'payments'): ?>
                                                <th>Pedido</th>
                                                <th>Produto</th>
                                                <th>Valor</th>
                                                <th>Status</th>
                                                <th>Data</th>
                                                <th>Ações</th>
                                                <?php else: ?>
                                                <th>Plano</th>
                                                <th>Status</th>
                                                <th>Início</th>
                                                <th>Fim</th>
                                                <th>Valor</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data as $item): ?>
                                            <tr>
                                                <?php if ($tab === 'payments'): ?>
                                                <td>
                                                    <span class="fw-semibold text-neutral-600">
                                                        #<?= htmlspecialchars($item['order_number']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="fw-semibold text-primary">
                                                        <?= htmlspecialchars($item['item_name'] ?? 'Assinatura') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="fw-semibold text-success">
                                                        R$ <?= number_format($item['total_amount'], 2, ',', '.') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusText = '';
                                                    
                                                    // Mapear status do Mercado Pago para status do sistema
                                                    $status = $item['payment_status'] ?? '';
                                                    
                                                    // Se for pagamento de produto (Mercado Pago)
                                                    if (isset($item['payment_source']) && $item['payment_source'] === 'product') {
                                                        switch ($status) {
                                                            case 'pending':
                                                                $statusClass = 'bg-warning';
                                                                $statusText = 'Pendente';
                                                                break;
                                                            case 'completed':
                                                                $statusClass = 'bg-success';
                                                                $statusText = 'Aprovado';
                                                                break;
                                                            case 'cancelled':
                                                                $statusClass = 'bg-danger';
                                                                $statusText = 'Cancelado';
                                                                break;
                                                            case '':
                                                            case null:
                                                                $statusClass = 'bg-warning';
                                                                $statusText = 'Pendente';
                                                                break;
                                                            default:
                                                                $statusClass = 'bg-secondary';
                                                                $statusText = !empty($status) ? ucfirst($status) : 'Pendente';
                                                        }
                                                    } else {
                                                        // Pagamentos de assinatura (sistema antigo)
                                                        switch ($status) {
                                                            case 'pending':
                                                                $statusClass = 'bg-warning';
                                                                $statusText = 'Pendente';
                                                                break;
                                                            case 'approved':
                                                                $statusClass = 'bg-success';
                                                                $statusText = 'Aprovado';
                                                                break;
                                                            case 'rejected':
                                                                $statusClass = 'bg-danger';
                                                                $statusText = 'Rejeitado';
                                                                break;
                                                            case '':
                                                            case null:
                                                                $statusClass = 'bg-warning';
                                                                $statusText = 'Pendente';
                                                                break;
                                                            default:
                                                                $statusClass = 'bg-secondary';
                                                                $statusText = !empty($status) ? ucfirst($status) : 'Pendente';
                                                        }
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?> text-white">
                                                        <?= $statusText ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-neutral-600">
                                                        <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="produto.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="ri-eye-line"></i>
                                                    </a>
                                                </td>
                                                <?php else: ?>
                                                <td>
                                                    <span class="fw-semibold text-primary">
                                                        <?= htmlspecialchars($item['plan_name']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusText = '';
                                                    switch ($item['status']) {
                                                        case 'active':
                                                            $statusClass = 'bg-success';
                                                            $statusText = 'Ativa';
                                                            break;
                                                        case 'cancelling':
                                                            $statusClass = 'bg-warning';
                                                            $statusText = 'Cancelando';
                                                            break;
                                                        case 'cancelled':
                                                            $statusClass = 'bg-danger';
                                                            $statusText = 'Cancelada';
                                                            break;
                                                        case 'expired':
                                                            $statusClass = 'bg-secondary';
                                                            $statusText = 'Expirada';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-secondary';
                                                            $statusText = ucfirst($item['status']);
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?> text-white">
                                                        <?= $statusText ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-neutral-600">
                                                        <?= date('d/m/Y', strtotime($item['start_date'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-neutral-600">
                                                        <?= date('d/m/Y', strtotime($item['end_date'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="fw-semibold text-success">
                                                        R$ <?= number_format($item['plan_price'], 2, ',', '.') ?>
                                                    </span>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Paginação -->
                                <?php if ($pages > 1): ?>
                                <div class="card-footer bg-base py-16 px-24">
                                    <nav aria-label="Paginação">
                                        <ul class="pagination justify-content-center mb-0">
                                            <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?tab=<?= $tab ?>&page=<?= $page - 1 ?>">
                                                    <i class="ri-arrow-left-line"></i>
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?tab=<?= $tab ?>&page=<?= $i ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?tab=<?= $tab ?>&page=<?= $page + 1 ?>">
                                                    <i class="ri-arrow-right-line"></i>
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                                <?php endif; ?>
                                
                                <?php endif; ?>
                                
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </section>

        <?php include './partials/footer.php' ?>
    </main>

    <?php include './partials/scripts.php' ?>

</body>
</html>


