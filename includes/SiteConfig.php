<?php
// Não incluir database.php aqui, pois será incluído pelo arquivo que chama esta classe

class SiteConfig {
    private $pdo;
    private $cache = [];
    private $cacheLoaded = false;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Carregar todas as configurações do banco
     */
    private function loadCache() {
        if ($this->cacheLoaded) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM settings");
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->cache[$row['setting_key']] = $row['setting_value'];
            }
            
            $this->cacheLoaded = true;
        } catch (PDOException $e) {
            error_log("Erro ao carregar configurações: " . $e->getMessage());
        }
    }

    /**
     * Obter valor de uma configuração
     */
    public function get($key, $default = null) {
        $this->loadCache();
        return $this->cache[$key] ?? $default;
    }

    /**
     * Definir valor de uma configuração
     */
    public function set($key, $value) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
            
            // Atualizar cache
            $this->cache[$key] = $value;
            
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao salvar configuração: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter todas as configurações
     */
    public function getAll() {
        $this->loadCache();
        return $this->cache;
    }

    /**
     * Obter configurações por grupo
     */
    public function getByGroup($group) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM settings 
                WHERE setting_group = ? 
                ORDER BY setting_key
            ");
            $stmt->execute([$group]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao carregar configurações do grupo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obter configuração com metadados
     */
    public function getWithMeta($key) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao carregar configuração: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Atualizar configuração com metadados
     */
    public function update($key, $value, $type = null, $group = null, $label = null, $description = null) {
        try {
            if ($type) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label, setting_description) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        setting_type = VALUES(setting_type),
                        setting_group = VALUES(setting_group),
                        setting_label = VALUES(setting_label),
                        setting_description = VALUES(setting_description)
                ");
                $stmt->execute([$key, $value, $type, $group, $label, $description]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE settings SET setting_value = ? WHERE setting_key = ?
                ");
                $stmt->execute([$value, $key]);
            }
            
            // Atualizar cache
            $this->cache[$key] = $value;
            
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao atualizar configuração: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletar configuração
     */
    public function delete($key) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            // Remover do cache
            unset($this->cache[$key]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao deletar configuração: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpar cache
     */
    public function clearCache() {
        $this->cache = [];
        $this->cacheLoaded = false;
    }

    /**
     * Obter grupos disponíveis
     */
    public function getGroups() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT setting_group, COUNT(*) as count 
                FROM settings 
                GROUP BY setting_group 
                ORDER BY setting_group
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao carregar grupos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar se modo manutenção está ativo
     */
    public function isMaintenanceMode() {
        return $this->get('maintenance_mode', '0') === '1';
    }

    /**
     * Obter mensagem de manutenção
     */
    public function getMaintenanceMessage() {
        return $this->get('maintenance_message', 'Site em manutenção. Volte em breve!');
    }

    /**
     * Obter nome do site
     */
    public function getSiteName() {
        return $this->get('site_name', 'Área de Membros');
    }

    /**
     * Obter descrição do site
     */
    public function getSiteDescription() {
        return $this->get('site_description', 'Sua área de membros com conteúdo exclusivo');
    }

    /**
     * Obter logo claro
     */
    public function getLogoLight() {
        return $this->get('site_logo_light', 'assets/images/logo.png');
    }

    /**
     * Obter logo escuro
     */
    public function getLogoDark() {
        return $this->get('site_logo_dark', 'assets/images/logo-light.png');
    }

    /**
     * Obter logo ícone
     */
    public function getLogoIcon() {
        return $this->get('site_logo_icon', 'assets/images/logo-icon.png');
    }

    /**
     * Obter favicon
     */
    public function getFavicon() {
        return $this->get('site_favicon', 'assets/images/favicon.png');
    }

    /**
     * Obter email de contato
     */
    public function getContactEmail() {
        return $this->get('contact_email', 'contato@areademembros.com');
    }

    /**
     * Obter telefone de contato
     */
    public function getContactPhone() {
        return $this->get('contact_phone', '+55 (11) 99999-9999');
    }

    /**
     * Obter redes sociais
     */
    public function getSocialLinks() {
        return [
            'facebook' => $this->get('social_facebook', ''),
            'twitter' => $this->get('social_twitter', ''),
            'instagram' => $this->get('social_instagram', ''),
            'linkedin' => $this->get('social_linkedin', '')
        ];
    }

    /**
     * Obter texto do rodapé
     */
    public function getFooterText() {
        return $this->get('footer_text', '© 2024 Área de Membros. Todos os direitos reservados.');
    }
}
?>
