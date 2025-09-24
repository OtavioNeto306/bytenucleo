<?php
require_once 'config/database.php';

echo "=== CONFIGURANDO MERCADO PAGO PARA PRODUÇÃO ===\n\n";

try {
    // Configurações de PRODUÇÃO com suas chaves reais
    $producaoConfigs = [
        'mercadopago_enabled' => '1', // Habilitar Mercado Pago
        'mercadopago_sandbox' => '0', // DESABILITAR modo sandbox (PRODUÇÃO)
        'mercadopago_public_key' => 'APP_USR-1d850ac7-5f3e-485c-b798-7301145d138b', // SUA CHAVE REAL DE PRODUÇÃO
        'mercadopago_access_token' => 'APP_USR-4328566887326067-090412-41a0543bf10d88c7610d88fb881b8e21-2666473749' // SEU TOKEN REAL DE PRODUÇÃO
    ];
    
    echo "🔑 CONFIGURANDO PARA PRODUÇÃO COM SUAS CHAVES REAIS:\n";
    echo "• Public Key: APP_USR-1d850ac7-5f3e-485c-b798-7301145d138b\n";
    echo "• Access Token: APP_USR-4328566887326067-090412-41a0543bf10d88c7610d88fb881b8e21-2666473749\n";
    echo "• Sandbox: 0 (PRODUÇÃO)\n\n";
    
    $updated = 0;
    
    foreach ($producaoConfigs as $key => $value) {
        // Verificar se a configuração existe
        $stmt = $pdo->prepare("SELECT setting_key FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->rowCount() > 0) {
            // Atualizar configuração existente
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
            echo "✅ Configuração '{$key}' atualizada para: {$value}\n";
            $updated++;
        } else {
            echo "⚠️ Configuração '{$key}' não encontrada\n";
        }
    }
    
    echo "\n=== RESUMO ===\n";
    echo "Configurações atualizadas: {$updated}\n\n";
    
    // Mostrar configurações atuais
    echo "=== CONFIGURAÇÕES ATUAIS DO MERCADO PAGO ===\n";
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'mercadopago_%' ORDER BY setting_key");
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($configs as $config) {
        $value = $config['setting_value'];
        if ($config['setting_key'] === 'mercadopago_access_token' && !empty($value)) {
            $value = substr($value, 0, 10) . '...' . substr($value, -10); // Mascarar token
        }
        echo "• {$config['setting_key']}: {$value}\n";
    }
    
    echo "\n🎉 MERCADO PAGO CONFIGURADO PARA PRODUÇÃO!\n";
    echo "\n⚠️ IMPORTANTE:\n";
    echo "1. Agora está em MODO PRODUÇÃO\n";
    echo "2. Use cartões REAIS (não de teste)\n";
    echo "3. Pagamentos serão REAIS\n";
    
    echo "\n🔧 PRÓXIMOS PASSOS:\n";
    echo "1. Teste o checkout novamente\n";
    echo "2. Deve funcionar perfeitamente agora\n";
    echo "3. Use cartões REAIS para pagamento\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
