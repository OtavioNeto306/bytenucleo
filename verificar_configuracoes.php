<?php
require_once 'config/database.php';

echo "=== VERIFICANDO CONFIGURAÇÕES ATUAIS ===\n\n";

try {
    // Verificar se a tabela settings existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    if ($stmt->rowCount() == 0) {
        echo "❌ Tabela settings não existe!\n";
        exit;
    }
    
    echo "✅ Tabela settings encontrada\n\n";
    
    // Mostrar configurações atuais
    echo "=== CONFIGURAÇÕES ATUAIS DO MERCADO PAGO ===\n";
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'mercadopago_%' ORDER BY setting_key");
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Converter para array associativo simples
    $configArray = [];
    foreach ($configs as $config) {
        $configArray[$config['setting_key']] = $config['setting_value'];
    }
    
    foreach ($configArray as $key => $value) {
        if ($key === 'mercadopago_access_token' && !empty($value)) {
            $value = substr($value, 0, 10) . '...' . substr($value, -10); // Mascarar token
        }
        echo "• {$key}: {$value}\n";
    }
    
    echo "\n=== RESUMO ===\n";
    
    // Verificar se está em sandbox ou produção
    $sandbox = $configArray['mercadopago_sandbox'] ?? '0';
    if ($sandbox === '1') {
        echo "⚠️ SISTEMA ESTÁ EM MODO SANDBOX (TESTE)\n";
    } else {
        echo "✅ SISTEMA ESTÁ EM MODO PRODUÇÃO\n";
    }
    
    // Verificar se as chaves estão configuradas
    $publicKey = $configArray['mercadopago_public_key'] ?? '';
    $accessToken = $configArray['mercadopago_access_token'] ?? '';
    
    if (empty($publicKey) || empty($accessToken)) {
        echo "❌ CHAVES NÃO ESTÃO CONFIGURADAS!\n";
    } else {
        echo "✅ CHAVES ESTÃO CONFIGURADAS\n";
        
        // Verificar se são chaves de produção
        if (strpos($publicKey, 'APP_USR-') === 0 && strpos($accessToken, 'APP_USR-') === 0) {
            echo "✅ CHAVES SÃO DE PRODUÇÃO (APP_USR-)\n";
        } elseif (strpos($publicKey, 'TEST-') === 0 && strpos($accessToken, 'TEST-') === 0) {
            echo "⚠️ CHAVES SÃO DE TESTE (TEST-)\n";
        } else {
            echo "❓ FORMATO DE CHAVES DESCONHECIDO\n";
        }
    }
    
    echo "\n🔧 STATUS ATUAL:\n";
    if ($sandbox === '0' && strpos($publicKey, 'APP_USR-') === 0) {
        echo "✅ SISTEMA CONFIGURADO CORRETAMENTE PARA PRODUÇÃO!\n";
        echo "✅ CHAVES DE PRODUÇÃO APLICADAS!\n";
        echo "✅ PRONTO PARA TESTAR O CHECKOUT!\n";
    } else {
        echo "⚠️ SISTEMA NÃO ESTÁ CONFIGURADO CORRETAMENTE!\n";
        echo "🔧 EXECUTE: configurar_producao.php\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
