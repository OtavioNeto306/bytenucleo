<?php
// Classe para gerenciar pagamentos offline
class Payment {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ===== CONFIGURAÇÕES DE PAGAMENTO =====

    // Obter configurações de pagamento ativas
    public function getActivePaymentSettings() {
        $stmt = $this->pdo->prepare("SELECT * FROM payment_settings WHERE is_active = TRUE ORDER BY payment_type");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obter todas as configurações de pagamento
    public function getAllPaymentSettings() {
        $stmt = $this->pdo->prepare("SELECT * FROM payment_settings ORDER BY payment_type");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obter configuração por tipo de pagamento
    public function getPaymentSettingByType($paymentType) {
        $stmt = $this->pdo->prepare("SELECT * FROM payment_settings WHERE payment_type = ?");
        $stmt->execute([$paymentType]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obter configuração específica
    public function getPaymentSetting($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM payment_settings WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Criar configuração de pagamento
    public function createPaymentSetting($data) {
        $sql = "INSERT INTO payment_settings (
                payment_type, title, description, 
                pix_key, pix_key_type, bank_name, 
                bank_agency, bank_account, bank_account_type,
                account_holder, account_document, 
                boleto_instructions, card_instructions, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['payment_type'],
            $data['title'],
            $data['description'],
            $data['pix_key'] ?? null,
            $data['pix_key_type'] ?? null,
            $data['bank_name'] ?? null,
            $data['bank_agency'] ?? null,
            $data['bank_account'] ?? null,
            $data['bank_account_type'] ?? null,
            $data['account_holder'] ?? null,
            $data['account_document'] ?? null,
            $data['boleto_instructions'] ?? null,
            $data['card_instructions'] ?? null,
            $data['is_active'] ?? true
        ]);
    }

    // Atualizar configuração de pagamento
    public function updatePaymentSetting($id, $data) {
        $sql = "UPDATE payment_settings SET 
                payment_type = ?, title = ?, description = ?, 
                pix_key = ?, pix_key_type = ?, bank_name = ?, 
                bank_agency = ?, bank_account = ?, bank_account_type = ?,
                account_holder = ?, account_document = ?, 
                boleto_instructions = ?, card_instructions = ?, is_active = ?
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['payment_type'],
            $data['title'],
            $data['description'],
            $data['pix_key'] ?? null,
            $data['pix_key_type'] ?? null,
            $data['bank_name'] ?? null,
            $data['bank_agency'] ?? null,
            $data['bank_account'] ?? null,
            $data['bank_account_type'] ?? null,
            $data['account_holder'] ?? null,
            $data['account_document'] ?? null,
            $data['boleto_instructions'] ?? null,
            $data['card_instructions'] ?? null,
            $data['is_active'] ?? true,
            $id
        ]);
    }

    // Atualizar ou criar configuração por tipo
    public function upsertPaymentSetting($data) {
        // Verificar se já existe
        $existing = $this->getPaymentSettingByType($data['payment_type']);
        
        if ($existing) {
            // Atualizar existente
            return $this->updatePaymentSetting($existing['id'], $data);
        } else {
            // Criar novo
            return $this->createPaymentSetting($data);
        }
    }

    // ===== PEDIDOS =====

    // Criar novo pedido
    public function createOrder($userId, $orderType, $totalAmount, $paymentMethod, $items = []) {
        try {
            $this->pdo->beginTransaction();

            // Gerar número único do pedido
            $orderNumber = $this->generateOrderNumber();

            // Criar pedido
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (order_number, user_id, order_type, total_amount, payment_method, payment_status, expires_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ");
            $stmt->execute([$orderNumber, $userId, $orderType, $totalAmount, $paymentMethod]);
            $orderId = $this->pdo->lastInsertId();

            // Adicionar itens do pedido
            foreach ($items as $item) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO order_items (order_id, item_type, item_id, item_name, item_price, quantity) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $item['type'],
                    $item['id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity'] ?? 1
                ]);
            }

            $this->pdo->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Obter pedido por ID
    public function getOrder($orderId) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, u.name as user_name, u.email as user_email
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obter itens do pedido
    public function getOrderItems($orderId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM order_items WHERE order_id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obter pedidos do usuário
    public function getUserOrders($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->pdo->prepare("
            SELECT o.*, 
                   COUNT(oi.id) as total_items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obter todos os pedidos (admin)
    public function getAllOrders($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where_conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where_conditions[] = "o.payment_status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['payment_method'])) {
            $where_conditions[] = "o.payment_method = ?";
            $params[] = $filters['payment_method'];
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = "(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "
            SELECT o.*, u.name as user_name, u.email as user_email,
                   COUNT(oi.id) as total_items,
                   GROUP_CONCAT(oi.item_name SEPARATOR ', ') as item_name,
                   o.order_type as payment_source
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            $where_clause
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT $limit OFFSET $offset
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    // Atualizar status do pedido
    public function updateOrderStatus($orderId, $status, $adminNotes = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Verificar se é um pedido de produto (tabela orders)
            $stmt = $this->pdo->prepare("SELECT order_type FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $orderType = $stmt->fetchColumn();
            
            if ($orderType) {
                // Atualizar status do pedido na tabela orders
                $stmt = $this->pdo->prepare("
                    UPDATE orders SET payment_status = ?, admin_notes = ? WHERE id = ?
                ");
                $stmt->execute([$status, $adminNotes, $orderId]);
                
                // Se for um produto e foi aprovado, atualizar também product_purchases
                if ($orderType === 'product' && $status === 'approved') {
                    $stmt = $this->pdo->prepare("
                        UPDATE product_purchases 
                        SET status = 'completed', updated_at = NOW() 
                        WHERE transaction_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$orderId]);
                    
                    // Criar registro em product_downloads
                    $this->createProductDownloads($orderId);
                } elseif ($orderType === 'product' && $status === 'rejected') {
                    $stmt = $this->pdo->prepare("
                        UPDATE product_purchases 
                        SET status = 'cancelled', updated_at = NOW() 
                        WHERE transaction_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$orderId]);
                }
                
                // Se for assinatura e foi aprovado, ativar assinatura
                if ($orderType === 'subscription' && $status === 'approved') {
                    $this->activateSubscriptionFromOrder($orderId);
                }
            } else {
                // Se não é um pedido da tabela orders, pode ser um product_purchase direto
                $stmt = $this->pdo->prepare("
                    UPDATE product_purchases 
                    SET status = ?, updated_at = NOW() 
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$status === 'approved' ? 'completed' : 'cancelled', $orderId]);
                
                if ($status === 'approved') {
                    // Criar registro em product_downloads
                    $this->createProductDownloads($orderId);
                }
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Criar registros em product_downloads para produto aprovado
    private function createProductDownloads($orderId) {
        try {
            // Obter dados do pedido
            $order = $this->getOrder($orderId);
            if (!$order) return false;
            
            // Obter itens do pedido
            $orderItems = $this->getOrderItems($orderId);
            
            foreach ($orderItems as $item) {
                if ($item['item_type'] === 'product') {
                    $productId = $item['item_id'];
                    
                    // Criar registro na tabela product_downloads
                    $stmt = $this->pdo->prepare("
                        INSERT INTO product_downloads (product_id, user_id, download_count, last_downloaded_at, created_at) 
                        VALUES (?, ?, 0, NULL, NOW())
                        ON DUPLICATE KEY UPDATE 
                        download_count = download_count,
                        last_downloaded_at = last_downloaded_at
                    ");
                    $stmt->execute([$productId, $order['user_id']]);
                }
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Ativar assinatura baseada no pedido aprovado
    private function activateSubscriptionFromOrder($orderId) {
        // Obter dados do pedido
        $order = $this->getOrder($orderId);
        if (!$order) {
            return false;
        }
        
        // Obter itens do pedido
        $orderItems = $this->getOrderItems($orderId);
        if (empty($orderItems)) {
            return false;
        }
        
        // Se for assinatura, criar assinatura
        if ($order['order_type'] === 'subscription') {
            // Procurar por item de assinatura
            foreach ($orderItems as $item) {
                if ($item['item_type'] === 'subscription_plan') {
                    $planId = $item['item_id'];
                    
                    // Criar assinatura usando a classe Subscription
                    require_once __DIR__ . '/Subscription.php';
                    $subscription = new Subscription($this->pdo);
                    
                    return $subscription->createSubscription($order['user_id'], $planId);
                }
            }
        }
        
        // Se for produto, criar registro de compra e download
        if ($order['order_type'] === 'product') {
            foreach ($orderItems as $item) {
                if ($item['item_type'] === 'product') {
                    $productId = $item['item_id'];
                    
                    // Criar registro na tabela product_purchases para aparecer no histórico
                    $stmt = $this->pdo->prepare("
                        INSERT INTO product_purchases (product_id, user_id, status, amount, created_at) 
                        VALUES (?, ?, 'completed', ?, NOW())
                    ");
                    $stmt->execute([$productId, $order['user_id'], $item['item_price']]);
                    
                    // Criar registro na tabela product_downloads para aparecer em "Meus Downloads"
                    $stmt = $this->pdo->prepare("
                        INSERT INTO product_downloads (product_id, user_id, downloaded_at, ip_address) 
                        VALUES (?, ?, NOW(), ?)
                    ");
                    $stmt->execute([$productId, $order['user_id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
                }
            }
            return true;
        }
        
        return false;
    }

    // Upload de comprovante
    public function uploadPaymentProof($orderId, $file) {
        $uploadDir = 'uploads/payment_proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'order_' . $orderId . '_' . time() . '_' . basename($file['name']);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $stmt = $this->pdo->prepare("
                UPDATE orders SET 
                payment_proof_path = ?, 
                payment_proof_uploaded_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([$filePath, $orderId]);
        }
        return false;
    }

    // ===== CARRINHO DE COMPRAS =====

    // Obter carrinho do usuário
    public function getUserCart($userId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM shopping_carts 
            WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Criar carrinho
    public function createCart($userId) {
        $stmt = $this->pdo->prepare("
            INSERT INTO shopping_carts (user_id, expires_at) 
            VALUES (?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");
        $stmt->execute([$userId]);
        return $this->pdo->lastInsertId();
    }

    // Adicionar produto ao carrinho
    public function addToCart($cartId, $productId, $quantity = 1) {
        // Verificar se já existe no carrinho
        $stmt = $this->pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cartId, $productId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Atualizar quantidade
            $stmt = $this->pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            return $stmt->execute([$existing['quantity'] + $quantity, $existing['id']]);
        } else {
            // Adicionar novo item
            $stmt = $this->pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
            return $stmt->execute([$cartId, $productId, $quantity]);
        }
    }

    // Remover produto do carrinho
    public function removeFromCart($cartId, $productId) {
        $stmt = $this->pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
        return $stmt->execute([$cartId, $productId]);
    }

    // Obter itens do carrinho
    public function getCartItems($cartId) {
        $stmt = $this->pdo->prepare("
            SELECT ci.*, p.name, p.price, p.image_path, p.slug
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cartId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calcular total do carrinho
    public function getCartTotal($cartId) {
        $stmt = $this->pdo->prepare("
            SELECT SUM(p.price * ci.quantity) as total
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cartId]);
        return $stmt->fetchColumn() ?: 0;
    }

    // Limpar carrinho
    public function clearCart($cartId) {
        $stmt = $this->pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
        return $stmt->execute([$cartId]);
    }

    // ===== UTILITÁRIOS =====

    // Gerar número único do pedido
    private function generateOrderNumber() {
        do {
            $orderNumber = 'ORD' . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 6));
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
            $stmt->execute([$orderNumber]);
        } while ($stmt->fetchColumn() > 0);
        
        return $orderNumber;
    }

    // Verificar se pedido expirou
    public function isOrderExpired($orderId) {
        $stmt = $this->pdo->prepare("SELECT expires_at FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $expiresAt = $stmt->fetchColumn();
        
        if (!$expiresAt) return false;
        return strtotime($expiresAt) < time();
    }

    // Obter estatísticas de pedidos
    public function getOrderStats() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN payment_status = 'approved' THEN 1 END) as approved_orders,
                COUNT(CASE WHEN payment_status = 'rejected' THEN 1 END) as rejected_orders,
                SUM(CASE WHEN payment_status = 'approved' THEN total_amount ELSE 0 END) as total_revenue
            FROM orders
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ===== MÉTODOS PARA COMPRAS DE PRODUTOS (MERCADO PAGO) =====

    // Obter todas as compras de produtos
    public function getAllProductPurchases($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $params = [];
        $where_conditions = [];

        if (!empty($filters['status'])) {
            $where_conditions[] = "pp.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['payment_method'])) {
            $where_conditions[] = "pp.payment_method = ?";
            $params[] = $filters['payment_method'];
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = "(pp.transaction_id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR p.name LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "
            SELECT pp.*, u.name as user_name, u.email as user_email, p.name as item_name,
                   'product' as payment_source
            FROM product_purchases pp
            JOIN users u ON pp.user_id = u.id
            LEFT JOIN products p ON pp.product_id = p.id
            $where_clause
            ORDER BY pp.created_at DESC
            LIMIT $limit OFFSET $offset
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obter estatísticas de compras de produtos
    public function getProductPurchaseStats() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_purchases,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_purchases,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_purchases,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_purchases,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue
            FROM product_purchases
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obter detalhes de uma compra de produto específica
    public function getProductPurchase($id) {
        $stmt = $this->pdo->prepare("
            SELECT pp.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
                   p.name as product_name, p.description as product_description, p.image_path as product_image
            FROM product_purchases pp
            JOIN users u ON pp.user_id = u.id
            LEFT JOIN products p ON pp.product_id = p.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Atualizar status de uma compra de produto
    public function updateProductPurchaseStatus($id, $status, $adminNotes = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE product_purchases 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $id]);
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
