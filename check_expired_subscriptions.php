<?php
/**
 * Script para verificar e atualizar assinaturas expiradas
 * Deve ser executado via CRON job diariamente
 * 
 * Exemplo de CRON:
 * 0 0 * * * /usr/bin/php /caminho/para/check_expired_subscriptions.php
 */

require_once 'config/database.php';
require_once 'includes/Notification.php';

// Função para salvar logs
function saveLog($message) {
    $logFile = __DIR__ . '/logs/subscription_expiration.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

saveLog("=== VERIFICAÇÃO DE ASSINATURAS EXPIRADAS INICIADA ===");

try {
    $pdo->beginTransaction();
    
    // 1. Buscar assinaturas que expiraram mas ainda estão marcadas como 'active'
    $stmt = $pdo->prepare("
        SELECT us.*, u.name as user_name, u.email as user_email, sp.name as plan_name
        FROM user_subscriptions us
        JOIN users u ON us.user_id = u.id
        LEFT JOIN subscription_plans sp ON us.plan_id = sp.id
        WHERE us.status = 'active' 
        AND us.end_date < NOW()
        ORDER BY us.end_date ASC
    ");
    $stmt->execute();
    $expiredSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    saveLog("Encontradas " . count($expiredSubscriptions) . " assinaturas expiradas");
    
    $notification = new Notification($pdo);
    $updatedCount = 0;
    
    foreach ($expiredSubscriptions as $subscription) {
        $userId = $subscription['user_id'];
        $planName = $subscription['plan_name'] ?? 'Plano';
        
        saveLog("Processando assinatura expirada - User ID: $userId, Plano: $planName");
        
        // 2. Marcar assinatura como 'expired'
        $stmt = $pdo->prepare("
            UPDATE user_subscriptions 
            SET status = 'expired' 
            WHERE id = ?
        ");
        $stmt->execute([$subscription['id']]);
        
        // 3. Resetar plano do usuário para básico (ID 1)
        $stmt = $pdo->prepare("
            UPDATE users 
            SET current_plan_id = 1, subscription_expires_at = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        // 4. Criar notificação para o usuário
        $notification->create(
            $userId,
            'subscription',
            'Assinatura Expirada',
            "Sua assinatura do plano '{$planName}' expirou. Você foi automaticamente transferido para o plano básico.",
            null
        );
        
        saveLog("✅ Assinatura ID {$subscription['id']} marcada como expirada e usuário resetado para plano básico");
        $updatedCount++;
    }
    
    // 5. Verificar se há usuários com current_plan_id incorreto
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.current_plan_id, us.status, us.end_date
        FROM users u
        LEFT JOIN user_subscriptions us ON u.id = us.user_id 
            AND us.status = 'active' 
            AND us.end_date > NOW()
        WHERE u.current_plan_id != 1 
        AND (us.id IS NULL OR us.status != 'active')
    ");
    $stmt->execute();
    $incorrectUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    saveLog("Encontrados " . count($incorrectUsers) . " usuários com plano incorreto");
    
    foreach ($incorrectUsers as $user) {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET current_plan_id = 1, subscription_expires_at = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        saveLog("✅ Usuário ID {$user['id']} resetado para plano básico");
        $updatedCount++;
    }
    
    $pdo->commit();
    
    saveLog("=== VERIFICAÇÃO CONCLUÍDA ===");
    saveLog("Total de registros atualizados: $updatedCount");
    
    // Se executado via CLI, mostrar resumo
    if (php_sapi_name() === 'cli') {
        echo "Verificação de assinaturas expiradas concluída!\n";
        echo "Assinaturas expiradas encontradas: " . count($expiredSubscriptions) . "\n";
        echo "Usuários com plano incorreto: " . count($incorrectUsers) . "\n";
        echo "Total de atualizações: $updatedCount\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    saveLog("❌ ERRO: " . $e->getMessage());
    
    if (php_sapi_name() === 'cli') {
        echo "Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
    exit;
}
?>
