<?php
require_once '../../config/database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/Subscription.php';
require_once '../../includes/Notification.php';

$auth = new Auth($pdo);
$subscription = new Subscription($pdo);
$notification = new Notification($pdo);

// Verificar se é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$userId = $_POST['user_id'] ?? null;
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$currentPlanId = $_POST['current_plan_id'] ?? 1;
$subscriptionExpiresAt = $_POST['subscription_expires_at'] ?? null;
$subscriptionStatus = $_POST['subscription_status'] ?? 'active';
$changeType = $_POST['change_type'] ?? 'update';
$adminNotes = $_POST['admin_notes'] ?? '';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário não fornecido']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 1. Atualizar dados básicos do usuário
    $stmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, email = ?, current_plan_id = ?, subscription_expires_at = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $email, $currentPlanId, $subscriptionExpiresAt, $userId]);
    
    // 2. Gerenciar assinatura baseado no tipo de alteração
    if ($changeType === 'create_subscription' && $currentPlanId != 1) {
        // Desativar assinaturas ativas existentes
        $subscription->deactivateUserSubscriptions($userId);
        
        // Criar nova assinatura
        $planData = [
            'id' => $currentPlanId,
            'duration_days' => 30 // Default, pode ser ajustado
        ];
        
        if ($subscriptionExpiresAt) {
            $planData['duration_days'] = ceil((strtotime($subscriptionExpiresAt) - time()) / (60 * 60 * 24));
        }
        
        $subscription->createSubscription($userId, $planData, 'admin_created');
        
        // Criar notificação
        $notification->create(
            $userId,
            'subscription',
            'Plano Atualizado',
            "Seu plano foi atualizado pelo administrador. Observações: " . $adminNotes,
            null
        );
        
    } elseif ($changeType === 'extend_subscription' && $subscriptionExpiresAt) {
        // Estender assinatura atual
        $stmt = $pdo->prepare("
            UPDATE user_subscriptions 
            SET end_date = ?, updated_at = NOW()
            WHERE user_id = ? AND status = 'active' AND end_date > NOW()
        ");
        $stmt->execute([$subscriptionExpiresAt, $userId]);
        
        // Criar notificação
        $notification->create(
            $userId,
            'subscription',
            'Assinatura Estendida',
            "Sua assinatura foi estendida até " . date('d/m/Y', strtotime($subscriptionExpiresAt)) . ". Observações: " . $adminNotes,
            null
        );
        
    } elseif ($changeType === 'update') {
        // Atualizar status da assinatura se necessário
        if ($subscriptionStatus === 'expired') {
            $stmt = $pdo->prepare("
                UPDATE user_subscriptions 
                SET status = 'expired', updated_at = NOW()
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$userId]);
        }
        
        // Se mudou para plano básico, desativar assinaturas
        if ($currentPlanId == 1) {
            $subscription->deactivateUserSubscriptions($userId);
        }
    }
    
    // Log da alteração (opcional)
    error_log("Admin {$_SESSION['user_id']} atualizou usuário $userId: plano $currentPlanId, expiração $subscriptionExpiresAt");
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Usuário atualizado com sucesso!'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar usuário: ' . $e->getMessage()
    ]);
}
?>
