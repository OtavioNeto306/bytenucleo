<?php
require_once 'config/database.php';

echo "<h2>🔐 Configuração do reCAPTCHA</h2>";

try {
    // Verificar se as configurações do reCAPTCHA já existem
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key IN ('recaptcha_enabled', 'recaptcha_site_key', 'recaptcha_secret_key')");
    $stmt->execute();
    $existingCount = $stmt->fetchColumn();
    
    if ($existingCount > 0) {
        echo "<p style='color: orange;'>⚠️ Configurações do reCAPTCHA já existem no banco de dados.</p>";
        echo "<p>Configurações encontradas: $existingCount</p>";
        
        // Mostrar configurações existentes
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, setting_label FROM settings WHERE setting_key IN ('recaptcha_enabled', 'recaptcha_site_key', 'recaptcha_secret_key')");
        $stmt->execute();
        $existingSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Configurações Existentes:</h3>";
        echo "<ul>";
        foreach ($existingSettings as $setting) {
            echo "<li><strong>{$setting['setting_label']}</strong>: {$setting['setting_value']}</li>";
        }
        echo "</ul>";
        
    } else {
        // Inserir configurações do reCAPTCHA
        $recaptchaSettings = [
            ['recaptcha_enabled', '0', 'reCAPTCHA Habilitado', 'Ativar/desativar reCAPTCHA no login', 'checkbox', 'system'],
            ['recaptcha_site_key', '', 'reCAPTCHA Site Key', 'Chave pública do reCAPTCHA (Site Key)', 'text', 'system'],
            ['recaptcha_secret_key', '', 'reCAPTCHA Secret Key', 'Chave secreta do reCAPTCHA (Secret Key)', 'text', 'system']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_label, setting_description, setting_type, setting_group, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        
        $insertedCount = 0;
        echo "<h3>Inserindo configurações...</h3>";
        echo "<ul>";
        
        foreach ($recaptchaSettings as $setting) {
            $stmt->execute($setting);
            $insertedCount++;
            echo "<li style='color: green;'>✅ Inserido: {$setting[2]}</li>";
        }
        
        echo "</ul>";
        echo "<p style='color: green; font-weight: bold;'>🎉 Sucesso! $insertedCount configurações do reCAPTCHA foram inseridas no banco de dados.</p>";
    }
    
    echo "<hr>";
    echo "<h3>📋 Próximos Passos:</h3>";
    echo "<ol>";
    echo "<li>Acesse: <strong>Admin > Configurações > Sistema</strong></li>";
    echo "<li>Configure as chaves do reCAPTCHA:</li>";
    echo "<ul>";
    echo "<li><strong>reCAPTCHA Habilitado</strong>: Marque para ativar</li>";
    echo "<li><strong>Site Key</strong>: Cole a chave pública do Google</li>";
    echo "<li><strong>Secret Key</strong>: Cole a chave secreta do Google</li>";
    echo "</ul>";
    echo "<li>Obtenha as chaves em: <a href='https://www.google.com/recaptcha/admin' target='_blank'>https://www.google.com/recaptcha/admin</a></li>";
    echo "<li>Teste o login com o reCAPTCHA ativado</li>";
    echo "</ol>";
    
    echo "<hr>";
    echo "<p><a href='admin/configuracoes.php?group=system'>🔧 Ir para Configurações do Sistema</a></p>";
    echo "<p><a href='login.php'>🔐 Ir para Login</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro ao inserir configurações do reCAPTCHA: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
