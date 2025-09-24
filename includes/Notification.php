<?php

class Notification {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Criar uma nova notificação
     */
    public function create($userId, $type, $title, $message, $icon = null, $link = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, icon, link) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $icon = $icon ?: $this->getDefaultIcon($type);
            
            return $stmt->execute([$userId, $type, $title, $message, $icon, $link]);
        } catch (PDOException $e) {
            error_log("Erro ao criar notificação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar uma nova notificação usando array de dados
     */
    public function createFromArray($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, icon, link) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $icon = $data['icon'] ?? $this->getDefaultIcon($data['type']);
            
            return $stmt->execute([
                $data['user_id'], 
                $data['type'], 
                $data['title'], 
                $data['message'], 
                $icon, 
                $data['link'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao criar notificação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar notificação para múltiplos usuários
     */
    public function createForMultipleUsers($userIds, $type, $title, $message, $icon = null, $link = null) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, icon, link) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $icon = $icon ?: $this->getDefaultIcon($type);
            
            foreach ($userIds as $userId) {
                $stmt->execute([$userId, $type, $title, $message, $icon, $link]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Erro ao criar notificações múltiplas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar notificações de um usuário
     */
    public function getUserNotifications($userId, $limit = 10, $offset = 0, $unreadOnly = false) {
        try {
            $sql = "SELECT * FROM notifications WHERE user_id = ?";
            $params = [$userId];
            
            if ($unreadOnly) {
                $sql .= " AND is_read = FALSE";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar notificações: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Contar notificações não lidas de um usuário
     */
    public function getUnreadCount($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Erro ao contar notificações não lidas: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Buscar notificações recentes para o dropdown do navbar
     */
    public function getRecentNotifications($userId, $limit = 5) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar notificações recentes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marcar notificação como lida
     */
    public function markAsRead($notificationId, $userId = null) {
        try {
            $sql = "UPDATE notifications SET is_read = TRUE WHERE id = ?";
            $params = [$notificationId];
            
            if ($userId) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marcar todas as notificações de um usuário como lidas
     */
    public function markAllAsRead($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications SET is_read = TRUE 
                WHERE user_id = ? AND is_read = FALSE
            ");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Erro ao marcar todas as notificações como lidas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deletar notificação
     */
    public function delete($notificationId, $userId = null) {
        try {
            $sql = "DELETE FROM notifications WHERE id = ?";
            $params = [$notificationId];
            
            if ($userId) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro ao deletar notificação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Deletar notificações antigas (mais de 30 dias)
     */
    public function deleteOldNotifications($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            return $stmt->execute([$days]);
        } catch (PDOException $e) {
            error_log("Erro ao deletar notificações antigas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter ícone padrão baseado no tipo de notificação
     */
    private function getDefaultIcon($type) {
        $icons = [
            'welcome' => 'ri-heart-line',
            'system' => 'ri-notification-line',
            'payment' => 'ri-money-dollar-circle-line',
            'subscription' => 'ri-vip-crown-line',
            'product' => 'ri-box-line',
            'download' => 'ri-download-line',
            'security' => 'ri-shield-check-line',
            'admin' => 'ri-admin-line',
            'news' => 'ri-newspaper-line',
            'success' => 'ri-check-line',
            'warning' => 'ri-alert-line',
            'error' => 'ri-error-warning-line',
            'info' => 'ri-information-line'
        ];
        
        return $icons[$type] ?? 'ri-notification-line';
    }
    
    /**
     * Criar notificação de boas-vindas
     */
    public function createWelcomeNotification($userId, $userName) {
        return $this->create(
            $userId,
            'welcome',
            'Bem-vindo ao Clubinho PRO!',
            "Olá {$userName}! Seja bem-vindo ao Clubinho PRO. Explore nossos produtos exclusivos e aproveite o conteúdo premium.",
            'ri-heart-line'
        );
    }
    
    /**
     * Criar notificação de nova assinatura
     */
    public function createSubscriptionNotification($userId, $planName) {
        return $this->create(
            $userId,
            'subscription',
            'Assinatura Ativada!',
            "Parabéns! Sua assinatura {$planName} foi ativada com sucesso. Agora você tem acesso a todos os conteúdos premium.",
            'ri-vip-crown-line',
            '/perfil.php'
        );
    }
    
    /**
     * Criar notificação de pagamento aprovado
     */
    public function createPaymentApprovedNotification($userId, $amount) {
        return $this->create(
            $userId,
            'payment',
            'Pagamento Aprovado!',
            "Seu pagamento de R$ {$amount} foi aprovado com sucesso. Sua assinatura foi ativada.",
            'ri-money-dollar-circle-line',
            '/perfil.php'
        );
    }
    
    /**
     * Criar notificação de novo produto
     */
    public function createNewProductNotification($userIds, $productName) {
        return $this->createForMultipleUsers(
            $userIds,
            'product',
            'Novo Produto Disponível!',
            "Um novo produto foi adicionado: {$productName}. Confira agora!",
            'ri-box-line',
            '/produtos.php'
        );
    }
    
    /**
     * Criar notificação de download
     */
    public function createDownloadNotification($userId, $productName) {
        return $this->create(
            $userId,
            'download',
            'Download Realizado!',
            "Produto '{$productName}' baixado com sucesso. Aproveite o conteúdo!",
            'ri-download-line',
            '/produtos.php'
        );
    }
    
    /**
     * Criar notificação de login suspeito
     */
    public function createSecurityNotification($userId, $location) {
        return $this->create(
            $userId,
            'security',
            'Novo Login Detectado',
            "Detectamos um novo login em {$location}. Se não foi você, altere sua senha imediatamente.",
            'ri-shield-check-line',
            '/editar-perfil.php'
        );
    }
    
    /**
     * Criar notificação para admins
     */
    public function createAdminNotification($userIds, $title, $message) {
        return $this->createForMultipleUsers(
            $userIds,
            'admin',
            $title,
            $message,
            'ri-admin-line'
        );
    }

    // ===== MÉTODOS PARA NOTIFICAÇÕES DE COMPRAS DE PRODUTOS =====

    /**
     * Criar notificação de compra de produto aprovada
     */
    public function createProductPurchaseApprovedNotification($userId, $productName, $amount) {
        return $this->create(
            $userId,
            'payment',
            'Compra Aprovada!',
            "Sua compra do produto '{$productName}' por R$ {$amount} foi aprovada com sucesso. Você já pode baixar o produto!",
            'ri-shopping-cart-line',
            '/meus-downloads.php'
        );
    }

    /**
     * Criar notificação de compra de produto pendente
     */
    public function createProductPurchasePendingNotification($userId, $productName, $amount) {
        return $this->create(
            $userId,
            'payment',
            'Compra em Análise',
            "Sua compra do produto '{$productName}' por R$ {$amount} está sendo processada. Você receberá uma notificação quando for aprovada.",
            'ri-time-line',
            '/historico-pagamentos.php'
        );
    }

    /**
     * Criar notificação de compra de produto cancelada
     */
    public function createProductPurchaseCancelledNotification($userId, $productName, $amount) {
        return $this->create(
            $userId,
            'error',
            'Compra Cancelada',
            "Sua compra do produto '{$productName}' por R$ {$amount} foi cancelada. Entre em contato conosco se precisar de ajuda.",
            'ri-close-circle-line',
            '/historico-pagamentos.php'
        );
    }

    /**
     * Criar notificação para admin sobre nova compra de produto
     */
    public function createAdminProductPurchaseNotification($adminUserIds, $userName, $productName, $amount) {
        return $this->createForMultipleUsers(
            $adminUserIds,
            'admin',
            'Nova Compra de Produto',
            "O usuário {$userName} comprou o produto '{$productName}' por R$ {$amount}.",
            'ri-shopping-cart-line',
            '/admin/pagamentos.php'
        );
    }
}
