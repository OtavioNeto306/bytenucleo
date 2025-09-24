<?php
require_once '../../config/database.php';
require_once '../../includes/Auth.php';

$auth = new Auth($pdo);

// Verificar se é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$userId = $_GET['id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário não fornecido']);
    exit;
}

try {
    // Buscar dados do usuário
    $stmt = $pdo->prepare("
        SELECT u.*, 
               sp.name as plan_name,
               us.status as subscription_status,
               us.end_date as subscription_end_date
        FROM users u
        LEFT JOIN subscription_plans sp ON u.current_plan_id = sp.id
        LEFT JOIN user_subscriptions us ON u.id = us.user_id 
            AND us.status = 'active' 
            AND us.end_date > NOW()
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    
    // Buscar histórico de assinaturas
    $stmt = $pdo->prepare("
        SELECT us.*, sp.name as plan_name
        FROM user_subscriptions us
        LEFT JOIN subscription_plans sp ON us.plan_id = sp.id
        WHERE us.user_id = ?
        ORDER BY us.created_at DESC
    ");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar histórico de compras
    $stmt = $pdo->prepare("
        SELECT pp.*, p.name as product_name
        FROM product_purchases pp
        LEFT JOIN products p ON pp.product_id = p.id
        WHERE pp.user_id = ?
        ORDER BY pp.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Gerar HTML
    $html = '
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-body mb-3">Informações Básicas</h6>
            <table class="table table-sm">
                <tr>
                    <td class="text-body"><strong>ID:</strong></td>
                    <td class="text-body">' . $user['id'] . '</td>
                </tr>
                <tr>
                    <td class="text-body"><strong>Nome:</strong></td>
                    <td class="text-body">' . htmlspecialchars($user['name']) . '</td>
                </tr>
                <tr>
                    <td class="text-body"><strong>Email:</strong></td>
                    <td class="text-body">' . htmlspecialchars($user['email']) . '</td>
                </tr>
                <tr>
                    <td class="text-body"><strong>Cadastrado em:</strong></td>
                    <td class="text-body">' . date('d/m/Y H:i', strtotime($user['created_at'])) . '</td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-body mb-3">Plano Atual</h6>
            <table class="table table-sm">
                <tr>
                    <td class="text-body"><strong>Plano:</strong></td>
                    <td class="text-body">' . ($user['plan_name'] ?: 'Básico') . '</td>
                </tr>
                <tr>
                    <td class="text-body"><strong>Status:</strong></td>
                    <td class="text-body">
                        <span class="badge bg-' . ($user['subscription_status'] === 'active' ? 'success' : 'warning') . '">
                            ' . ucfirst($user['subscription_status'] ?: 'Sem assinatura') . '
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="text-body"><strong>Expira em:</strong></td>
                    <td class="text-body">' . ($user['subscription_end_date'] ? date('d/m/Y H:i', strtotime($user['subscription_end_date'])) : 'Sem expiração') . '</td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <h6 class="text-body mb-3">Histórico de Assinaturas</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th class="text-body">Plano</th>
                            <th class="text-body">Status</th>
                            <th class="text-body">Início</th>
                            <th class="text-body">Expiração</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    if (empty($subscriptions)) {
        $html .= '<tr><td colspan="4" class="text-center text-body">Nenhuma assinatura encontrada</td></tr>';
    } else {
        foreach ($subscriptions as $sub) {
            $statusClass = $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'expired' ? 'danger' : 'warning');
            $html .= '
                <tr>
                    <td class="text-body">' . htmlspecialchars($sub['plan_name'] ?: 'Plano') . '</td>
                    <td class="text-body"><span class="badge bg-' . $statusClass . '">' . ucfirst($sub['status']) . '</span></td>
                    <td class="text-body">' . date('d/m/Y', strtotime($sub['created_at'])) . '</td>
                    <td class="text-body">' . ($sub['end_date'] ? date('d/m/Y', strtotime($sub['end_date'])) : '-') . '</td>
                </tr>';
        }
    }
    
    $html .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <h6 class="text-body mb-3">Últimas Compras de Produtos</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th class="text-body">Produto</th>
                            <th class="text-body">Status</th>
                            <th class="text-body">Valor</th>
                            <th class="text-body">Data</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    if (empty($purchases)) {
        $html .= '<tr><td colspan="4" class="text-center text-body">Nenhuma compra encontrada</td></tr>';
    } else {
        foreach ($purchases as $purchase) {
            $statusClass = $purchase['status'] === 'completed' ? 'success' : ($purchase['status'] === 'pending' ? 'warning' : 'danger');
            $html .= '
                <tr>
                    <td class="text-body">' . htmlspecialchars($purchase['product_name'] ?: 'Produto') . '</td>
                    <td class="text-body"><span class="badge bg-' . $statusClass . '">' . ucfirst($purchase['status']) . '</span></td>
                    <td class="text-body">R$ ' . number_format($purchase['amount'], 2, ',', '.') . '</td>
                    <td class="text-body">' . date('d/m/Y H:i', strtotime($purchase['created_at'])) . '</td>
                </tr>';
        }
    }
    
    $html .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>
