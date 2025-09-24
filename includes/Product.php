<?php
// Não incluir database.php aqui, pois será incluído pelo arquivo que chama esta classe

class Product {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Obter todos os produtos com paginação e filtros
    public function getAllProducts($page = 1, $limit = 10, $filters = []) {
        // Garantir que page e limit sejam válidos
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        
        // Aplicar filtros
        if (!empty($filters['search'])) {
            $where_conditions[] = "(p.name LIKE ? OR p.short_description LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['category_id'])) {
            $where_conditions[] = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['product_type'])) {
            $where_conditions[] = "p.product_type = ?";
            $params[] = $filters['product_type'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['plan_id'])) {
            if ($filters['plan_id'] === 'null') {
                $where_conditions[] = "p.plan_id IS NULL";
            } else {
                $where_conditions[] = "p.plan_id = ?";
                $params[] = $filters['plan_id'];
            }
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Contar total de registros
        $count_sql = "SELECT COUNT(*) FROM products p $where_clause";
        $stmt = $this->pdo->prepare($count_sql);
        $stmt->execute($params);
        $total_records = $stmt->fetchColumn();
        
        // Buscar produtos com planos associados
        $sql = "SELECT p.*, pc.name as category_name,
                       GROUP_CONCAT(sp.name SEPARATOR ', ') as plan_names,
                       GROUP_CONCAT(sp.id SEPARATOR ',') as plan_ids
                FROM products p 
                LEFT JOIN product_categories pc ON p.category_id = pc.id 
                LEFT JOIN product_plans pp ON p.id = pp.product_id
                LEFT JOIN subscription_plans sp ON pp.plan_id = sp.id 
                $where_clause 
                GROUP BY p.id
                ORDER BY p.created_at DESC 
                LIMIT " . $limit . " OFFSET " . $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'products' => $products,
            'total_records' => $total_records,
            'total_pages' => ceil($total_records / $limit)
        ];
    }
    
    // Obter produto por ID
    public function getProductById($id) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, pc.name as category_name,
                   GROUP_CONCAT(sp.name SEPARATOR ', ') as plan_names,
                   GROUP_CONCAT(sp.id SEPARATOR ',') as plan_ids
            FROM products p 
            LEFT JOIN product_categories pc ON p.category_id = pc.id 
            LEFT JOIN product_plans pp ON p.id = pp.product_id
            LEFT JOIN subscription_plans sp ON pp.plan_id = sp.id 
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Criar produto
    public function createProduct($data) {
        $sql = "INSERT INTO products (
            name, slug, short_description, full_description, category_id, plan_id,
            individual_sale, individual_price, product_type, file_path, image_path, gallery_images, 
            video_url, video_apresentacao, video_thumbnail, max_downloads_per_user, featured, status, 
            meta_title, meta_description, tags, published_at, version, last_updated, demo_url, requirements
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            $data['name'],
            $this->createSlug($data['name']),
            $data['short_description'],
            $data['full_description'],
            $data['category_id'],
            $data['plan_id'] ?? null,
            $data['individual_sale'] ?? 0,
            $data['individual_price'] ?? 0.00,
            $data['product_type'],
            $data['file_path'] ?? null,
            $data['image_path'] ?? null,
            $data['gallery_images'] ?? null,
            $data['video_url'] ?? null,
            $data['video_apresentacao'] ?? null,
            $data['video_thumbnail'] ?? null,
            $data['max_downloads_per_user'] ?? -1,
            $data['featured'] ?? false,
            $data['status'],
            $data['meta_title'] ?? null,
            $data['meta_description'] ?? null,
            $data['tags'] ?? null,
            $data['published_at'] ?? date('Y-m-d H:i:s'),
            $data['version'] ?? null,
            $data['last_updated'] ?? null,
            $data['demo_url'] ?? null,
            $data['requirements'] ?? null
        ]);
        
        if ($result) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }
    
    // Atualizar produto
    public function updateProduct($id, $data) {
        $sql = "UPDATE products SET 
            name = ?, slug = ?, short_description = ?, full_description = ?, 
            category_id = ?, plan_id = ?, individual_sale = ?, individual_price = ?, product_type = ?, file_path = ?, 
            image_path = ?, gallery_images = ?, video_url = ?, 
            video_apresentacao = ?, video_thumbnail = ?, max_downloads_per_user = ?, featured = ?, status = ?, 
            meta_title = ?, meta_description = ?, tags = ?, 
            published_at = ?, updated_at = CURRENT_TIMESTAMP, version = ?, last_updated = ?, demo_url = ?, requirements = ?
            WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $this->createSlug($data['name']),
            $data['short_description'],
            $data['full_description'],
            $data['category_id'],
            $data['plan_id'] ?? null,
            $data['individual_sale'] ?? 0,
            $data['individual_price'] ?? 0.00,
            $data['product_type'],
            $data['file_path'] ?? null,
            $data['image_path'] ?? null,
            $data['gallery_images'] ?? null,
            $data['video_url'] ?? null,
            $data['video_apresentacao'] ?? null,
            $data['video_thumbnail'] ?? null,
            $data['max_downloads_per_user'] ?? -1,
            $data['featured'] ?? false,
            $data['status'],
            $data['meta_title'] ?? null,
            $data['meta_description'] ?? null,
            $data['tags'] ?? null,
            $data['published_at'] ?? date('Y-m-d H:i:s'),
            $data['version'] ?? null,
            $data['last_updated'] ?? null,
            $data['demo_url'] ?? null,
            $data['requirements'] ?? null,
            $id
        ]);
    }
    
    // Excluir produto
    public function deleteProduct($id) {
        // Primeiro excluir downloads
        $stmt = $this->pdo->prepare("DELETE FROM product_downloads WHERE product_id = ?");
        $stmt->execute([$id]);
        
        // Depois excluir o produto
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // Obter todas as categorias
    public function getAllCategories() {
        $stmt = $this->pdo->query("SELECT * FROM product_categories ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obter categorias com contagem de produtos
    public function getCategoriesWithProductCount($page = 1, $limit = 12) {
        // Garantir que page e limit sejam válidos
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        
        // Contar total de categorias
        $count_sql = "SELECT COUNT(*) FROM product_categories";
        $stmt = $this->pdo->prepare($count_sql);
        $stmt->execute();
        $total_records = $stmt->fetchColumn();
        
        // Buscar categorias com contagem de produtos
        $sql = "
            SELECT pc.*, COUNT(p.id) as product_count 
            FROM product_categories pc 
            LEFT JOIN products p ON pc.id = p.category_id AND p.status = 'active'
            GROUP BY pc.id 
            ORDER BY pc.name 
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'categories' => $categories,
            'total_records' => $total_records,
            'total_pages' => ceil($total_records / $limit)
        ];
    }
    
    // Registrar download
    public function registerDownload($product_id, $user_id, $ip_address = null) {
        if ($ip_address === null) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        
        // Verificar se já baixou hoje
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM product_downloads 
            WHERE product_id = ? AND user_id = ? AND DATE(downloaded_at) = CURDATE()
        ");
        $stmt->execute([$product_id, $user_id]);
        $today_downloads = $stmt->fetchColumn();
        
        // Inserir registro de download
        $stmt = $this->pdo->prepare("
            INSERT INTO product_downloads (product_id, user_id, ip_address) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$product_id, $user_id, $ip_address]);
        
        // Atualizar contador de downloads
        $stmt = $this->pdo->prepare("
            UPDATE products SET downloads_count = downloads_count + 1 
            WHERE id = ?
        ");
        $stmt->execute([$product_id]);
        
        return $today_downloads + 1;
    }
    
    // Verificar se usuário pode baixar
    public function canUserDownload($product_id, $user_id) {
        $product = $this->getProductById($product_id);
        if (!$product) return false;
        
        // Verificar se é Super Admin - Super Admin tem acesso a tudo
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND r.name = 'super_admin'
        ");
        $stmt->execute([$user_id]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        
        // Verificar se o produto tem planos associados (nova estrutura)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM product_plans 
            WHERE product_id = ?
        ");
        $stmt->execute([$product_id]);
        $hasPlans = $stmt->fetchColumn() > 0;
        
        if ($hasPlans) {
            // Verificar se o usuário tem uma assinatura ativa para algum dos planos do produto
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_subscriptions us
                JOIN product_plans pp ON us.plan_id = pp.plan_id
                WHERE us.user_id = ? AND pp.product_id = ? 
                AND us.status = 'active' AND us.end_date > NOW()
            ");
            $stmt->execute([$user_id, $product_id]);
            $hasPlan = $stmt->fetchColumn() > 0;
            
            if (!$hasPlan) {
                // Se não tem plano, verificar se pode comprar individualmente
                if ($product['individual_sale'] && $product['individual_price'] > 0) {
                    // Verificar se já comprou o produto individualmente
                    try {
                        $stmt = $this->pdo->prepare("
                            SELECT COUNT(*) FROM product_purchases 
                            WHERE product_id = ? AND user_id = ? AND status = 'completed'
                        ");
                        $stmt->execute([$product_id, $user_id]);
                        $hasMercadoPagoPurchase = $stmt->fetchColumn() > 0;
                        
                        // Verificar compras offline também
                        $stmt = $this->pdo->prepare("
                            SELECT COUNT(*) FROM orders o
                            JOIN order_items oi ON o.id = oi.order_id
                            WHERE o.user_id = ? AND o.order_type = 'product' 
                            AND o.payment_status = 'approved' AND oi.item_id = ?
                        ");
                        $stmt->execute([$user_id, $product_id]);
                        $hasOfflinePurchase = $stmt->fetchColumn() > 0;
                        
                        return $hasMercadoPagoPurchase || $hasOfflinePurchase;
                    } catch (PDOException $e) {
                        // Se a tabela não existe, retornar false
                        return false;
                    }
                }
                return false; // Usuário não tem plano e não pode comprar individualmente
            }
        } else {
            // Produto sem planos - verificar se é gratuito ou tem venda individual
            if ($product['individual_sale'] && $product['individual_price'] > 0) {
                // Verificar se já comprou o produto individualmente
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) FROM product_purchases 
                        WHERE product_id = ? AND user_id = ? AND status = 'completed'
                    ");
                    $stmt->execute([$product_id, $user_id]);
                    $hasMercadoPagoPurchase = $stmt->fetchColumn() > 0;
                    
                    // Verificar compras offline também
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) FROM orders o
                        JOIN order_items oi ON o.id = oi.order_id
                        WHERE o.user_id = ? AND o.order_type = 'product' 
                        AND o.payment_status = 'approved' AND oi.item_id = ?
                    ");
                    $stmt->execute([$user_id, $product_id]);
                    $hasOfflinePurchase = $stmt->fetchColumn() > 0;
                    
                    $hasPurchased = $hasMercadoPagoPurchase || $hasOfflinePurchase;
                    
                    if ($hasPurchased) {
                        // Se comprou, verificar limite de downloads
                        if ($product['max_downloads_per_user'] == -1) return true;
                        
                        $stmt = $this->pdo->prepare("
                            SELECT COUNT(*) FROM product_downloads 
                            WHERE product_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$product_id, $user_id]);
                        $downloads = $stmt->fetchColumn();
                        
                        return $downloads < $product['max_downloads_per_user'];
                    }
                    return false; // Não comprou
                } catch (PDOException $e) {
                    // Se a tabela não existe, retornar false
                    return false;
                }
            }
            // Produto gratuito - pode baixar (verificar limite)
        }
        
        // Verificar limite de downloads para produtos gratuitos
        if ($product['max_downloads_per_user'] == -1) return true;
        
        // Verificar quantas vezes já baixou
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM product_downloads 
            WHERE product_id = ? AND user_id = ?
        ");
        $stmt->execute([$product_id, $user_id]);
        $downloads = $stmt->fetchColumn();
        
        return $downloads < $product['max_downloads_per_user'];
    }
    
    // Criar slug único
    private function createSlug($name) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = trim($slug, '-');
        
        // Verificar se já existe
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
        $stmt->execute([$slug]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $slug .= '-' . time();
        }
        
        return $slug;
    }
    
    // Obter produtos em destaque
    public function getFeaturedProducts($limit = 6) {
        // Garantir que limit seja um número válido
        $limit = max(1, (int)$limit);
        
        $sql = "SELECT p.*, pc.name as category_name,
                       GROUP_CONCAT(sp.name SEPARATOR ', ') as plan_names,
                       GROUP_CONCAT(sp.id SEPARATOR ',') as plan_ids
                FROM products p 
                LEFT JOIN product_categories pc ON p.category_id = pc.id 
                LEFT JOIN product_plans pp ON p.id = pp.product_id
                LEFT JOIN subscription_plans sp ON pp.plan_id = sp.id 
                WHERE p.featured = 1 AND p.status = 'active' 
                GROUP BY p.id
                ORDER BY p.created_at DESC 
                LIMIT " . $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obter produtos mais baixados
    public function getMostDownloadedProducts($limit = 10) {
        // Garantir que limit seja um número válido
        $limit = max(1, (int)$limit);
        
        $sql = "SELECT p.*, pc.name as category_name,
                       GROUP_CONCAT(sp.name SEPARATOR ', ') as plan_names,
                       GROUP_CONCAT(sp.id SEPARATOR ',') as plan_ids
                FROM products p 
                LEFT JOIN product_categories pc ON p.category_id = pc.id 
                LEFT JOIN product_plans pp ON p.id = pp.product_id
                LEFT JOIN subscription_plans sp ON pp.plan_id = sp.id 
                WHERE p.status = 'active' 
                GROUP BY p.id
                ORDER BY p.downloads_count DESC 
                LIMIT " . $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obter produtos gratuitos
    public function getFreeProducts($limit = 10) {
        // Garantir que limit seja um número válido
        $limit = max(1, (int)$limit);
        
        $sql = "SELECT p.*, pc.name as category_name,
                       GROUP_CONCAT(sp.name SEPARATOR ', ') as plan_names,
                       GROUP_CONCAT(sp.id SEPARATOR ',') as plan_ids
                FROM products p 
                LEFT JOIN product_categories pc ON p.category_id = pc.id 
                LEFT JOIN product_plans pp ON p.id = pp.product_id
                LEFT JOIN subscription_plans sp ON pp.plan_id = sp.id 
                WHERE p.status = 'active' 
                AND (p.individual_sale = 0 OR p.individual_price = 0)
                AND (sp.name IS NULL OR sp.name = '')
                GROUP BY p.id
                ORDER BY p.created_at DESC 
                LIMIT " . $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obter produtos recentes
    public function getRecentProducts($limit = 10) {
        // Garantir que limit seja um número válido
        $limit = max(1, (int)$limit);
        
        $sql = "SELECT p.*, pc.name as category_name,
                       GROUP_CONCAT(sp.name SEPARATOR ', ') as plan_names,
                       GROUP_CONCAT(sp.id SEPARATOR ',') as plan_ids
                FROM products p 
                LEFT JOIN product_categories pc ON p.category_id = pc.id 
                LEFT JOIN product_plans pp ON p.id = pp.product_id
                LEFT JOIN subscription_plans sp ON pp.plan_id = sp.id 
                WHERE p.status = 'active' 
                GROUP BY p.id
                ORDER BY p.created_at DESC 
                LIMIT " . $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ===== MÉTODOS DE COMPATIBILIDADE COM PÁGINAS ANTIGAS =====
    
    // Buscar produtos (compatibilidade)
    public function searchProducts($search) {
        $filters = ['search' => $search, 'status' => 'active'];
        $result = $this->getAllProducts(1, 50, $filters);
        return $result['products'];
    }
    
    // Obter produtos premium (compatibilidade)
    public function getPremiumProducts($limit = null) {
        $filters = ['product_type' => 'premium', 'status' => 'active'];
        $result = $this->getAllProducts(1, $limit ?: 50, $filters);
        return $result['products'];
    }
    
    // Obter categorias (compatibilidade)
    public function getCategories() {
        return $this->getAllCategories();
    }
    
    // Verificar se pode baixar (compatibilidade)
    public function canDownload($product_id, $user_id) {
        return $this->canUserDownload($product_id, $user_id);
    }
    
    // Verificar se usuário pode ver conteúdo completo do produto
    public function canViewContent($product_id, $user_id) {
        $product = $this->getProductById($product_id);
        if (!$product) return false;
        
        // Verificar se é Super Admin - Super Admin tem acesso a tudo
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND r.name = 'super_admin'
        ");
        $stmt->execute([$user_id]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        
        // Verificar se o produto tem planos associados (nova estrutura)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM product_plans 
            WHERE product_id = ?
        ");
        $stmt->execute([$product_id]);
        $hasPlans = $stmt->fetchColumn() > 0;
        
        if ($hasPlans) {
            // Verificar se o usuário tem uma assinatura ativa para algum dos planos do produto
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_subscriptions us
                JOIN product_plans pp ON us.plan_id = pp.plan_id
                WHERE us.user_id = ? AND pp.product_id = ? 
                AND us.status = 'active' AND us.end_date > NOW()
            ");
            $stmt->execute([$user_id, $product_id]);
            $hasPlan = $stmt->fetchColumn() > 0;
            
            if (!$hasPlan) {
                // Se não tem plano, verificar se pode comprar individualmente
                if ($product['individual_sale'] && $product['individual_price'] > 0) {
                    // Verificar se já comprou o produto individualmente
                    try {
                        $stmt = $this->pdo->prepare("
                            SELECT COUNT(*) FROM product_purchases 
                            WHERE product_id = ? AND user_id = ? AND status = 'completed'
                        ");
                        $stmt->execute([$product_id, $user_id]);
                        $hasMercadoPagoPurchase = $stmt->fetchColumn() > 0;
                        
                        // Verificar compras offline também
                        $stmt = $this->pdo->prepare("
                            SELECT COUNT(*) FROM orders o
                            JOIN order_items oi ON o.id = oi.order_id
                            WHERE o.user_id = ? AND o.order_type = 'product' 
                            AND o.payment_status = 'approved' AND oi.item_id = ?
                        ");
                        $stmt->execute([$user_id, $product_id]);
                        $hasOfflinePurchase = $stmt->fetchColumn() > 0;
                        
                        return $hasMercadoPagoPurchase || $hasOfflinePurchase;
                    } catch (PDOException $e) {
                        // Se a tabela não existe, retornar false
                        return false;
                    }
                }
                return false; // Usuário não tem plano e não pode comprar individualmente
            }
        } else {
            // Produto sem planos - verificar se é gratuito ou tem venda individual
            if ($product['individual_sale'] && $product['individual_price'] > 0) {
                // Verificar se já comprou o produto individualmente (Mercado Pago)
                $hasMercadoPagoPurchase = false;
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) FROM product_purchases 
                        WHERE product_id = ? AND user_id = ? AND status = 'completed'
                    ");
                    $stmt->execute([$product_id, $user_id]);
                    $hasMercadoPagoPurchase = $stmt->fetchColumn() > 0;
                } catch (PDOException $e) {
                    // Se a tabela não existe, continuar
                }
                
                // Verificar se já comprou o produto individualmente (Offline)
                $hasOfflinePurchase = false;
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) FROM orders o
                        JOIN order_items oi ON o.id = oi.order_id
                        WHERE o.user_id = ? AND o.order_type = 'product' 
                        AND o.payment_status = 'approved' AND oi.item_id = ?
                    ");
                    $stmt->execute([$user_id, $product_id]);
                    $hasOfflinePurchase = $stmt->fetchColumn() > 0;
                } catch (PDOException $e) {
                    // Se a tabela não existe, continuar
                }
                
                return $hasMercadoPagoPurchase || $hasOfflinePurchase;
            }
            // Produto gratuito - pode ver tudo
        }
        
        return true;
    }
    
    // Obter downloads do usuário (compatibilidade)
    public function getUserDownloads($user_id, $limit = 10) {
        // Garantir que limit seja um número válido
        $limit = max(1, (int)$limit);
        
        $stmt = $this->pdo->prepare("
            SELECT pd.*, p.name as product_name, p.slug, p.image_path, pc.name as category_name
            FROM product_downloads pd
            JOIN products p ON pd.product_id = p.id
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            WHERE pd.user_id = ?
            ORDER BY pd.downloaded_at DESC
            LIMIT $limit
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obter downloads do usuário com paginação e filtros
    public function getUserDownloadsPaginated($user_id, $page = 1, $limit = 10, $filters = []) {
        // Garantir que page e limit sejam válidos
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        
        $where_conditions = ["pd.user_id = ?"];
        $params = [$user_id];
        
        // Aplicar filtros
        if (!empty($filters['search'])) {
            $where_conditions[] = "(p.name LIKE ? OR p.short_description LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['category_id'])) {
            $where_conditions[] = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['product_type'])) {
            $where_conditions[] = "p.product_type = ?";
            $params[] = $filters['product_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(pd.downloaded_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(pd.downloaded_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Contar total de registros
        $count_sql = "
            SELECT COUNT(*) 
            FROM product_downloads pd
            JOIN products p ON pd.product_id = p.id
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            $where_clause
        ";
        $stmt = $this->pdo->prepare($count_sql);
        $stmt->execute($params);
        $total_records = $stmt->fetchColumn();
        
        // Buscar downloads
        $sql = "
            SELECT pd.*, p.name as product_name, p.slug, p.image_path, p.product_type, pc.name as category_name
            FROM product_downloads pd
            JOIN products p ON pd.product_id = p.id
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            $where_clause
            ORDER BY pd.downloaded_at DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'downloads' => $downloads,
            'total_records' => $total_records,
            'total_pages' => ceil($total_records / $limit)
        ];
    }
    
    // Obter produtos por categoria (compatibilidade) - método separado para evitar conflito
    public function getProductsByCategory($categoryId = null) {
        $filters = ['status' => 'active'];
        if ($categoryId) {
            $filters['category_id'] = $categoryId;
        }
        $result = $this->getAllProducts(1, 50, $filters);
        return $result['products'];
    }
    
    // ===== MÉTODOS PARA VÍDEOS =====
    
    // Adicionar vídeo ao produto
    public function addVideo($product_id, $title, $description, $youtube_url, $duration = null, $order_index = 0) {
        $stmt = $this->pdo->prepare("
            INSERT INTO product_videos (product_id, title, description, youtube_url, duration, order_index) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$product_id, $title, $description, $youtube_url, $duration, $order_index]);
        return $this->pdo->lastInsertId();
    }
    
    // Obter vídeos do produto
    public function getProductVideos($product_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM product_videos 
            WHERE product_id = ? 
            ORDER BY order_index ASC, created_at ASC
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Atualizar vídeo
    public function updateVideo($video_id, $title, $description, $youtube_url, $duration = null, $order_index = 0) {
        $stmt = $this->pdo->prepare("
            UPDATE product_videos 
            SET title = ?, description = ?, youtube_url = ?, duration = ?, order_index = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$title, $description, $youtube_url, $duration, $order_index, $video_id]);
    }
    
    // Remover vídeo
    public function deleteVideo($video_id) {
        $stmt = $this->pdo->prepare("DELETE FROM product_videos WHERE id = ?");
        return $stmt->execute([$video_id]);
    }
    
    // ===== MÉTODOS PARA MATERIAIS =====
    
    // Adicionar material ao produto
    public function addMaterial($product_id, $name, $type, $file_path = null, $external_url = null, $order_index = 0, $is_gradual_release = false, $release_days = 0) {
        $stmt = $this->pdo->prepare("
            INSERT INTO product_materials (product_id, name, type, file_path, external_url, order_index, is_gradual_release, release_days) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$product_id, $name, $type, $file_path, $external_url, $order_index, $is_gradual_release, $release_days]);
    }
    
    // Obter materiais do produto
    public function getProductMaterials($product_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM product_materials 
            WHERE product_id = ? 
            ORDER BY order_index ASC, created_at ASC
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Verificar se um material está liberado para o usuário
    public function isMaterialReleased($material_id, $user_id) {
        $stmt = $this->pdo->prepare("
            SELECT pm.is_gradual_release, pm.release_days, pp.created_at
            FROM product_materials pm
            JOIN product_purchases pp ON pm.product_id = pp.product_id
            WHERE pm.id = ? AND pp.user_id = ? AND pp.status = 'aprovado'
        ");
        $stmt->execute([$material_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false; // Usuário não comprou o produto
        }
        
        if (!$result['is_gradual_release']) {
            return true; // Material liberado imediatamente
        }
        
        // Calcular data de liberação
        $purchase_date = new DateTime($result['created_at']);
        $release_date = $purchase_date->add(new DateInterval('P' . $result['release_days'] . 'D'));
        $current_date = new DateTime();
        
        return $current_date >= $release_date;
    }
    
    // Obter materiais do produto com status de liberação para o usuário
    public function getProductMaterialsWithReleaseStatus($product_id, $user_id) {
        $materials = $this->getProductMaterials($product_id);
        
        // Obter data de compra do usuário para este produto
        $purchaseDate = $this->getUserPurchaseDate($product_id, $user_id);
        
        foreach ($materials as &$material) {
            $material['is_released'] = true;
            $material['release_date'] = null;
            $material['days_remaining'] = 0;
            
            // Se tem liberação gradual, calcular se está liberado
            if ($material['is_gradual_release'] && $material['release_days'] > 0 && $purchaseDate) {
                // Calcular data de liberação baseada na data de compra
                $purchaseDateTime = new DateTime($purchaseDate);
                $releaseDateTime = clone $purchaseDateTime;
                $releaseDateTime->add(new DateInterval('P' . $material['release_days'] . 'D'));
                
                $currentDateTime = new DateTime();
                $material['release_date'] = $releaseDateTime->format('d/m/Y');
                
                if ($currentDateTime >= $releaseDateTime) {
                    // Material já foi liberado
                    $material['is_released'] = true;
                    $material['days_remaining'] = 0;
                } else {
                    // Material ainda não foi liberado
                    $material['is_released'] = false;
                    $interval = $currentDateTime->diff($releaseDateTime);
                    $material['days_remaining'] = $interval->days + 1; // +1 para incluir o dia atual
                }
            }
        }
        
        return $materials;
    }
    
    // Método auxiliar para obter data de compra do usuário
    private function getUserPurchaseDate($product_id, $user_id) {
        // Buscar com diferentes status possíveis
        $statusOptions = ['aprovado', 'approved', 'completed', 'paid'];
        
        foreach ($statusOptions as $status) {
            $stmt = $this->pdo->prepare("
                SELECT created_at 
                FROM product_purchases 
                WHERE product_id = ? AND user_id = ? AND status = ?
                ORDER BY created_at ASC 
                LIMIT 1
            ");
            $stmt->execute([$product_id, $user_id, $status]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                break; // Encontrou uma compra com status válido
            }
        }
        
        return $result ? $result['created_at'] : null;
    }
    
    // Atualizar material
    public function updateMaterial($material_id, $name, $type, $file_path = null, $external_url = null, $order_index = 0) {
        $stmt = $this->pdo->prepare("
            UPDATE product_materials 
            SET name = ?, type = ?, file_path = ?, external_url = ?, order_index = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$name, $type, $file_path, $external_url, $order_index, $material_id]);
    }
    
    // Remover material
    public function deleteMaterial($material_id) {
        $stmt = $this->pdo->prepare("DELETE FROM product_materials WHERE id = ?");
        return $stmt->execute([$material_id]);
    }
    
    // ===== MÉTODOS AUXILIARES =====
    
    // Extrair ID do YouTube da URL
    public function extractYouTubeId($url) {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i';
        if (preg_match($pattern, $url, $match)) {
            return $match[1];
        }
        return null;
    }
    
    // Gerar URL de thumbnail do YouTube
    public function getYouTubeThumbnail($youtube_id) {
        return "https://img.youtube.com/vi/{$youtube_id}/maxresdefault.jpg";
    }
    
    // ===== MÉTODOS PARA PLANOS =====
    
    // Obter produtos por plano
    public function getProductsByPlan($plan_id, $page = 1, $limit = 10) {
        $filters = ['plan_id' => $plan_id];
        return $this->getAllProducts($page, $limit, $filters);
    }
    
    // Obter produtos que o usuário pode acessar baseado no seu plano
    public function getProductsForUser($user_plan_id, $page = 1, $limit = 10) {
        // Produtos gratuitos (plan_id = NULL) + produtos do plano do usuário
        $where_conditions = ["(p.plan_id IS NULL OR p.plan_id = ?)"];
        $params = [$user_plan_id];
        
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        
        // Contar total de registros
        $count_sql = "SELECT COUNT(*) FROM products p 
                      LEFT JOIN product_categories pc ON p.category_id = pc.id 
                      WHERE " . implode(' AND ', $where_conditions);
        $stmt = $this->pdo->prepare($count_sql);
        $stmt->execute($params);
        $total_records = $stmt->fetchColumn();
        
        // Buscar produtos
        $sql = "SELECT p.*, pc.name as category_name, sp.name as plan_name 
                FROM products p 
                LEFT JOIN product_categories pc ON p.category_id = pc.id 
                LEFT JOIN subscription_plans sp ON p.plan_id = sp.id 
                WHERE " . implode(' AND ', $where_conditions) . "
                ORDER BY p.created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'products' => $products,
            'total_records' => $total_records,
            'total_pages' => ceil($total_records / $limit)
        ];
    }
    
    // ===== MÉTODOS PARA DASHBOARD =====
    
    // Obter total de produtos
    public function getTotalProducts() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE status = 'active'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    // Obter total de downloads do usuário
    public function getUserTotalDownloads($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM product_downloads 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }
    
    // Obter produtos aleatórios
    public function getRandomProducts($limit = 5) {
        // Garantir que limit seja um número válido
        $limit = max(1, (int)$limit);
        
        $sql = "SELECT p.*, pc.name as category_name, sp.name as plan_name 
                FROM products p 
                LEFT JOIN product_categories pc ON p.category_id = pc.id 
                LEFT JOIN subscription_plans sp ON p.plan_id = sp.id 
                WHERE p.status = 'active'
                ORDER BY RAND() 
                LIMIT " . $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obter favoritos do usuário com paginação e filtros
    public function getUserFavoritesPaginated($user_id, $page = 1, $limit = 12, $filters = []) {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        
        $where_conditions = ["pf.user_id = ?"];
        $params = [$user_id];
        
        // Aplicar filtros
        if (!empty($filters['search'])) {
            $where_conditions[] = "(p.name LIKE ? OR p.short_description LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['category_id'])) {
            $where_conditions[] = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Query para contar total de registros
        $count_sql = "SELECT COUNT(*) as total 
                      FROM product_favorites pf 
                      INNER JOIN products p ON pf.product_id = p.id 
                      WHERE $where_clause AND p.status = 'active'";
        
        $count_stmt = $this->pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $limit);
        
        // Query para obter os favoritos
        $sql = "SELECT p.*, pc.name as category_name, pf.created_at as favorited_at
                FROM product_favorites pf 
                INNER JOIN products p ON pf.product_id = p.id 
                LEFT JOIN product_categories pc ON p.category_id = pc.id 
                WHERE $where_clause AND p.status = 'active'
                ORDER BY pf.created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'favorites' => $favorites,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'limit' => $limit
        ];
    }
}
?>
