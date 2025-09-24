<?php

class Subscription {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // Incluir Logger se não estiver incluído
        if (!class_exists('Logger')) {
            require_once __DIR__ . '/Logger.php';
        }
        
        $this->logger = new Logger('cancelamento.log');
    }
    
    // ===== MÉTODOS PARA PLANOS =====
    
    // Obter todos os planos
    public function getAllPlans($status = 'active') {
        $sql = "SELECT * FROM subscription_plans";
        if ($status) {
            $sql .= " WHERE status = ?";
        }
        $sql .= " ORDER BY price ASC";
        
        $stmt = $this->pdo->prepare($sql);
        if ($status) {
            $stmt->execute([$status]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obter plano por ID
    public function getPlanById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obter plano por slug
    public function getPlanBySlug($slug) {
        $stmt = $this->pdo->prepare("SELECT * FROM subscription_plans WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Criar novo plano
    public function createPlan($data) {
        $sql = "INSERT INTO subscription_plans (name, slug, description, price, duration_days, max_downloads, max_products, features, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['price'],
            $data['duration_days'],
            $data['max_downloads'],
            $data['max_products'],
            json_encode($data['features']),
            $data['status']
        ]);
    }
    
    // Atualizar plano
    public function updatePlan($id, $data) {
        $sql = "UPDATE subscription_plans SET 
                name = ?, slug = ?, description = ?, price = ?, duration_days = ?, 
                max_downloads = ?, max_products = ?, features = ?, status = ? 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['price'],
            $data['duration_days'],
            $data['max_downloads'],
            $data['max_products'],
            json_encode($data['features']),
            $data['status'],
            $id
        ]);
    }
    
    // Excluir plano
    public function deletePlan($id) {
        $stmt = $this->pdo->prepare("DELETE FROM subscription_plans WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // Obter nomes bonitos das features
    public function getFeatureNames() {
        return [
            'downloads_ilimitados' => 'Downloads Ilimitados',
            'suporte_premium' => 'Suporte Premium',
            'atualizacoes_recorrentes' => 'Atualizações Recorrentes',
            'produtos_exclusivos' => 'Produtos Exclusivos',
            'acesso_antecipado' => 'Acesso Antecipado',
            'comunidade_privada' => 'Comunidade Privada',
            'download_free' => 'Downloads Gratuitos',
            'download_premium' => 'Downloads Premium',
            'download_exclusive' => 'Downloads Exclusivos',
            'unlimited_downloads' => 'Downloads Ilimitados'
        ];
    }
    
    // Converter slug para nome bonito
    public function getFeatureDisplayName($slug) {
        $features = $this->getFeatureNames();
        return $features[$slug] ?? $slug;
    }
    
    // Converter array de slugs para nomes bonitos
    public function getFeatureDisplayNames($slugs) {
        if (!is_array($slugs)) {
            $slugs = json_decode($slugs, true) ?: [];
        }
        
        $features = $this->getFeatureNames();
        $names = [];
        
        foreach ($slugs as $slug) {
            $names[] = $features[$slug] ?? $slug;
        }
        
        return $names;
    }
    
    // ===== MÉTODOS PARA ASSINATURAS =====
    
    // Obter assinatura ativa do usuário
    public function getUserActiveSubscription($userId) {
        $sql = "SELECT us.*, sp.name as plan_name, sp.slug as plan_slug, sp.features 
                FROM user_subscriptions us 
                JOIN subscription_plans sp ON us.plan_id = sp.id 
                WHERE us.user_id = ? AND us.status = 'active' 
                ORDER BY us.created_at DESC 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Criar nova assinatura
    public function createSubscription($userId, $planId) {
        // Obter dados do plano
        $plan = $this->getPlanById($planId);
        if (!$plan) return false;
        
        // Calcular data de expiração
        $endDate = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
        
        // Desativar assinatura anterior se existir
        $this->deactivateUserSubscriptions($userId);
        
        // Criar nova assinatura
        $sql = "INSERT INTO user_subscriptions (user_id, plan_id, status, end_date) VALUES (?, ?, 'active', ?)";
        $stmt = $this->pdo->prepare($sql);
        
        if ($stmt->execute([$userId, $planId, $endDate])) {
            // Atualizar usuário
            $this->updateUserPlan($userId, $planId, $endDate);
            
            // Criar notificação de assinatura ativada
            require_once __DIR__ . '/Notification.php';
            $notification = new Notification($this->pdo);
            $notification->createSubscriptionNotification($userId, $plan['name']);
            
            return true;
        }
        return false;
    }
    
    // Desativar assinaturas do usuário
    private function deactivateUserSubscriptions($userId) {
        $sql = "UPDATE user_subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active'";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId]);
    }
    
    // Atualizar plano do usuário
    private function updateUserPlan($userId, $planId, $expiresAt) {
        $sql = "UPDATE users SET current_plan_id = ?, subscription_expires_at = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$planId, $expiresAt, $userId]);
    }
    
    // Verificar se assinatura está ativa
    public function isSubscriptionActive($userId) {
        $subscription = $this->getUserActiveSubscription($userId);
        if (!$subscription) return false;
        
        return strtotime($subscription['end_date']) > time();
    }
    
    // ===== MÉTODOS PARA PERMISSÕES =====
    
    // Obter todas as permissões
    public function getAllPermissions() {
        $stmt = $this->pdo->prepare("SELECT * FROM permissions ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obter permissões por categoria
    public function getPermissionsByCategory($category) {
        $stmt = $this->pdo->prepare("SELECT * FROM permissions WHERE category = ? ORDER BY name");
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obter permissões do plano
    public function getPlanPermissions($planId) {
        $sql = "SELECT p.* FROM permissions p 
                JOIN plan_permissions pp ON p.id = pp.permission_id 
                WHERE pp.plan_id = ? 
                ORDER BY p.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Verificar se usuário tem permissão
    public function userHasPermission($userId, $permissionSlug) {
        // Obter plano atual do usuário
        $user = $this->getUserWithPlan($userId);
        if (!$user) return false;
        
        // Verificar permissões do plano
        $planPermissions = $this->getPlanPermissions($user['current_plan_id']);
        foreach ($planPermissions as $permission) {
            if ($permission['slug'] === $permissionSlug) {
                return true;
            }
        }
        
        return false;
    }
    
    // Obter usuário com dados do plano
    private function getUserWithPlan($userId) {
        $sql = "SELECT u.*, sp.name as plan_name, sp.slug as plan_slug 
                FROM users u 
                LEFT JOIN subscription_plans sp ON u.current_plan_id = sp.id 
                WHERE u.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ===== MÉTODOS PARA DOWNLOADS =====
    
    // Verificar se usuário pode baixar produto
    public function canDownloadProduct($userId, $productType) {
        switch ($productType) {
            case 'free':
                return true; // Todos podem baixar produtos gratuitos
            case 'premium':
                return $this->userHasPermission($userId, 'download_premium');
            case 'exclusive':
                return $this->userHasPermission($userId, 'download_exclusive');
            default:
                return false;
        }
    }
    
    // Verificar limite de downloads
    public function checkDownloadLimit($userId, $productId) {
        // Obter plano do usuário
        $user = $this->getUserWithPlan($userId);
        if (!$user) return false;
        
        $plan = $this->getPlanById($user['current_plan_id']);
        if (!$plan) return false;
        
        // Se downloads ilimitados
        if ($plan['max_downloads'] == -1) return true;
        
        // Contar downloads do usuário
        $sql = "SELECT COUNT(*) FROM product_downloads WHERE user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $downloadCount = $stmt->fetchColumn();
        
        return $downloadCount < $plan['max_downloads'];
    }
    
    // ===== MÉTODOS AUXILIARES =====
    
    // Criar slug
    public function createSlug($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }
    
    // Verificar se slug existe
    public function slugExists($slug, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM subscription_plans WHERE slug = ?";
        if ($excludeId) {
            $sql .= " AND id != ?";
        }
        
        $stmt = $this->pdo->prepare($sql);
        if ($excludeId) {
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt->execute([$slug]);
        }
        
        return $stmt->fetchColumn() > 0;
    }
    
    // ===== MÉTODOS PARA CANCELAMENTO =====
    
    // Cancelar assinatura
    public function cancelSubscription($userId, $immediate = false) {
        // Usar o logger da classe se disponível, senão criar um novo
        if (isset($this->logger)) {
            $logger = $this->logger;
        } else {
            // Incluir Logger se não estiver incluído
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/Logger.php';
            }
            $logger = new Logger('cancelamento.log');
        }
        
        $logger->info("=== INÍCIO DO MÉTODO cancelSubscription ===");
        $logger->info("User ID: " . $userId . ", Imediato: " . ($immediate ? 'sim' : 'não'));
        
        try {
            $this->pdo->beginTransaction();
            $logger->info("✅ Transação iniciada");
            
            // Debug: verificar se há assinatura ativa
            $logger->info("🔍 Buscando assinatura ativa para usuário: " . $userId);
            
            // Buscar assinatura ativa
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_subscriptions 
                WHERE user_id = ? AND status = 'active' 
                ORDER BY end_date DESC LIMIT 1
            ");
            $stmt->execute([$userId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $logger->debug("Resultado da busca: " . print_r($subscription, true));
            
            if (!$subscription) {
                $logger->error("❌ Nenhuma assinatura ativa encontrada para usuário: " . $userId);
                throw new Exception("Nenhuma assinatura ativa encontrada");
            }
            
            $logger->info("✅ Assinatura encontrada: ID=" . $subscription['id'] . ", Status=" . $subscription['status'] . ", Plano=" . $subscription['plan_id']);
            
            if ($immediate) {
                $logger->info("🔄 Processando cancelamento IMEDIATO");
                
                // Cancelamento imediato
                $stmt = $this->pdo->prepare("
                    UPDATE user_subscriptions 
                    SET status = 'cancelled', cancelled_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$subscription['id']]);
                $logger->info("✅ Status da assinatura alterado para 'cancelled'");
                
                // Definir plano como básico (ID 1) e remover data de expiração
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET current_plan_id = 1, subscription_expires_at = NULL 
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                $logger->info("✅ Plano do usuário alterado para básico (ID 1)");
                
                // Verificar se a atualização funcionou
                $stmt = $this->pdo->prepare("SELECT current_plan_id, subscription_expires_at FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $userAfterUpdate = $stmt->fetch(PDO::FETCH_ASSOC);
                $logger->debug("Dados do usuário após atualização: " . print_r($userAfterUpdate, true));
                
                // Forçar atualização da sessão
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                    $_SESSION['current_plan_id'] = 1;
                    $_SESSION['subscription_expires_at'] = null;
                    $logger->info("✅ Sessão atualizada");
                }
                
            } else {
                $logger->info("🔄 Processando cancelamento no FINAL DO PERÍODO");
                
                // Cancelamento no final do período
                $stmt = $this->pdo->prepare("
                    UPDATE user_subscriptions 
                    SET status = 'cancelling', cancelled_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$subscription['id']]);
                $logger->info("✅ Status da assinatura alterado para 'cancelling'");
            }
            
            $this->pdo->commit();
            $logger->info("✅ Transação commitada com sucesso");
            $logger->info("🎉 Cancelamento concluído com sucesso para usuário: " . $userId . ", imediato: " . ($immediate ? 'sim' : 'não'));
            $logger->info("=== FIM DO MÉTODO cancelSubscription ===");
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $logger->error("❌ Erro no cancelamento: " . $e->getMessage());
            $logger->error("❌ Stack trace: " . $e->getTraceAsString());
            $logger->error("=== FIM DO MÉTODO cancelSubscription (COM ERRO) ===");
            throw $e;
        }
    }
    
    // Obter histórico de assinaturas do usuário
    public function getUserSubscriptionHistory($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        // Contar total
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM user_subscriptions 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $total = $stmt->fetchColumn();
        
        // Buscar assinaturas
        $stmt = $this->pdo->prepare("
            SELECT us.*, sp.name as plan_name, sp.price as plan_price
            FROM user_subscriptions us
            JOIN subscription_plans sp ON us.plan_id = sp.id
            WHERE us.user_id = ?
            ORDER BY us.created_at DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
        $stmt->execute([$userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'subscriptions' => $subscriptions,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    // Obter histórico de pagamentos do usuário
    public function getUserPaymentHistory($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        // Buscar pagamentos de assinaturas (orders)
        $stmt = $this->pdo->prepare("
            SELECT o.*, oi.item_name, oi.item_type, 'subscription' as payment_source
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ? AND o.order_type = 'subscription'
        ");
        $stmt->execute([$userId]);
        $subscriptionPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar pagamentos de produtos (product_purchases)
        $stmt = $this->pdo->prepare("
            SELECT 
                pp.id,
                pp.user_id,
                pp.product_id,
                pp.transaction_id as order_number,
                pp.amount as total_amount,
                pp.payment_method,
                pp.status as payment_status,
                pp.created_at,
                pp.updated_at,
                p.name as item_name,
                'product' as payment_source
            FROM product_purchases pp
            LEFT JOIN products p ON pp.product_id = p.id
            WHERE pp.user_id = ?
        ");
        $stmt->execute([$userId]);
        $productPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combinar e ordenar todos os pagamentos
        $allPayments = array_merge($subscriptionPayments, $productPayments);
        
        // Ordenar por data de criação (mais recente primeiro)
        usort($allPayments, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $total = count($allPayments);
        $paginatedPayments = array_slice($allPayments, $offset, $limit);
        
        return [
            'orders' => $paginatedPayments,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
}

?>
