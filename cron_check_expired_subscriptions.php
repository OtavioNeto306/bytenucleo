<?php
// Script CRON para verificar assinaturas expiradas (versão HTTP)
// Comando CRON: curl -s https://seusite.com/cron_check_expired_subscriptions_http.php

// Configurar headers para JSON
header('Content-Type: application/json');

try {
    // Verificar se o script principal existe
    if (!file_exists(__DIR__ . '/check_expired_subscriptions.php')) {
        throw new Exception('Script principal não encontrado');
    }
    
    // Incluir o script principal
    require_once __DIR__ . '/check_expired_subscriptions.php';
    
    // Se chegou até aqui, tudo funcionou
    echo json_encode([
        'success' => true,
        'message' => 'Verificação de assinaturas expiradas executada com sucesso',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
