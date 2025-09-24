<?php

class Config {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Obter valor de uma configuração
     */
    public function get($key, $default = null) {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['setting_value'] : $default;
        } catch (PDOException $e) {
            return $default;
        }
    }
    
    /**
     * Definir valor de uma configuração
     */
    public function set($key, $value, $description = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                setting_description = COALESCE(VALUES(setting_description), setting_description),
                updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$key, $value, $description]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Obter todas as configurações
     */
    public function getAll() {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value, setting_description FROM settings ORDER BY setting_key");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $configs = [];
            foreach ($results as $row) {
                $configs[$row['setting_key']] = $row['setting_value'];
            }
            
            return $configs;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obter configurações agrupadas por categoria
     */
    public function getGrouped() {
        $configs = $this->getAll();
        
        $grouped = [
            'payment' => [
                'mercadopago_enabled' => $configs['mercadopago_enabled'] ?? '0',
                'mercadopago_public_key' => $configs['mercadopago_public_key'] ?? '',
                'mercadopago_access_token' => $configs['mercadopago_access_token'] ?? '',
                'mercadopago_sandbox' => $configs['mercadopago_sandbox'] ?? '1',
                'offline_payments_enabled' => $configs['offline_payments_enabled'] ?? '0',
                'pix_enabled' => $configs['pix_enabled'] ?? '0',
                'pix_key' => $configs['pix_key'] ?? '',
                'pix_key_type' => $configs['pix_key_type'] ?? 'email',
                'bank_transfer_enabled' => $configs['bank_transfer_enabled'] ?? '0',
                'bank_info' => $configs['bank_info'] ?? ''
            ],
            'general' => [
                'site_name' => $configs['site_name'] ?? 'WowDash',
                'site_description' => $configs['site_description'] ?? 'Plataforma de produtos digitais'
            ]
        ];
        
        return $grouped;
    }
    
    /**
     * Salvar configurações agrupadas
     */
    public function saveGrouped($groupedConfigs) {
        $success = true;
        
        foreach ($groupedConfigs as $group => $configs) {
            foreach ($configs as $key => $value) {
                if (!$this->set($key, $value)) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
}
