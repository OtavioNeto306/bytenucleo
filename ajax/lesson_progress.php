<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';

header('Content-Type: application/json');

$auth = new Auth($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // Marcar aula como finalizada
            $input = json_decode(file_get_contents('php://input'), true);
            $videoId = $input['video_id'] ?? null;
            $productId = $input['product_id'] ?? null;
            
            if (!$videoId || !$productId) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }
            
            // Verificar se o usuário tem acesso ao produto usando a mesma lógica do Product.php
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception('Produto não encontrado');
            }
            
            // Verificar se é Super Admin - Super Admin tem acesso a tudo
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND r.name = 'Super Admin'
            ");
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() > 0) {
                // Super Admin tem acesso
            } else {
                // Verificar se o produto tem planos associados
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM product_plans 
                    WHERE product_id = ?
                ");
                $stmt->execute([$productId]);
                $hasPlans = $stmt->fetchColumn() > 0;
                
                if ($hasPlans) {
                    // Verificar se o usuário tem uma assinatura ativa para algum dos planos do produto
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM user_subscriptions us
                        JOIN product_plans pp ON us.plan_id = pp.plan_id
                        WHERE us.user_id = ? AND pp.product_id = ? 
                        AND us.status = 'active' AND us.end_date > NOW()
                    ");
                    $stmt->execute([$userId, $productId]);
                    $hasPlan = $stmt->fetchColumn() > 0;
                    
                    if (!$hasPlan) {
                        // Se não tem plano, verificar se pode comprar individualmente
                        if ($product['individual_sale'] && $product['individual_price'] > 0) {
                            // Verificar se já comprou o produto individualmente
                            $hasMercadoPagoPurchase = false;
                            $hasOfflinePurchase = false;
                            
                            try {
                                // Verificar compras Mercado Pago
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(*) FROM product_purchases 
                                    WHERE product_id = ? AND user_id = ? AND status = 'completed'
                                ");
                                $stmt->execute([$productId, $userId]);
                                $hasMercadoPagoPurchase = $stmt->fetchColumn() > 0;
                                
                                // Verificar compras offline
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(*) FROM orders o
                                    JOIN order_items oi ON o.id = oi.order_id
                                    WHERE o.user_id = ? AND o.order_type = 'product' 
                                    AND o.payment_status = 'approved' AND oi.item_id = ?
                                ");
                                $stmt->execute([$userId, $productId]);
                                $hasOfflinePurchase = $stmt->fetchColumn() > 0;
                                
                            } catch (PDOException $e) {
                                // Se alguma tabela não existe, continuar
                            }
                            
                            if (!$hasMercadoPagoPurchase && !$hasOfflinePurchase) {
                                throw new Exception('Usuário não tem acesso a este produto');
                            }
                        } else {
                            throw new Exception('Usuário não tem acesso a este produto');
                        }
                    }
                } else {
                    // Produto sem planos - verificar se é gratuito ou tem venda individual
                    if ($product['individual_sale'] && $product['individual_price'] > 0) {
                        // Verificar se já comprou o produto individualmente
                        $hasMercadoPagoPurchase = false;
                        $hasOfflinePurchase = false;
                        
                        try {
                            // Verificar compras Mercado Pago
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) FROM product_purchases 
                                WHERE product_id = ? AND user_id = ? AND status = 'completed'
                            ");
                            $stmt->execute([$productId, $userId]);
                            $hasMercadoPagoPurchase = $stmt->fetchColumn() > 0;
                            
                            // Verificar compras offline
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) FROM orders o
                                JOIN order_items oi ON o.id = oi.order_id
                                WHERE o.user_id = ? AND o.order_type = 'product' 
                                AND o.payment_status = 'approved' AND oi.item_id = ?
                            ");
                            $stmt->execute([$userId, $productId]);
                            $hasOfflinePurchase = $stmt->fetchColumn() > 0;
                            
                        } catch (PDOException $e) {
                            // Se alguma tabela não existe, continuar
                        }
                        
                        if (!$hasMercadoPagoPurchase && !$hasOfflinePurchase) {
                            throw new Exception('Usuário não tem acesso a este produto');
                        }
                    }
                    // Se não tem venda individual, é gratuito e todos têm acesso
                }
            }
            
            // Verificar se a aula existe no produto
            $stmt = $pdo->prepare("SELECT * FROM product_videos WHERE id = ? AND product_id = ?");
            $stmt->execute([$videoId, $productId]);
            $video = $stmt->fetch();
            
            if (!$video) {
                throw new Exception('Aula não encontrada');
            }
            
            // Inserir ou atualizar progresso
            $stmt = $pdo->prepare("
                INSERT INTO lesson_progress (user_id, product_id, video_id, completed_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE completed_at = NOW()
            ");
            $stmt->execute([$userId, $productId, $videoId]);
            
            // Buscar próxima aula
            $stmt = $pdo->prepare("
                SELECT * FROM product_videos 
                WHERE product_id = ? AND id > ? 
                ORDER BY id ASC 
                LIMIT 1
            ");
            $stmt->execute([$productId, $videoId]);
            $nextVideo = $stmt->fetch();
            
            // Calcular progresso geral
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_videos,
                    (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.product_id = ? AND lp.user_id = ?) as completed_videos
                FROM product_videos 
                WHERE product_id = ?
            ");
            $stmt->execute([$productId, $userId, $productId]);
            $progress = $stmt->fetch();
            
            $progressPercentage = $progress['total_videos'] > 0 ? 
                round(($progress['completed_videos'] / $progress['total_videos']) * 100) : 0;
            
            echo json_encode([
                'success' => true,
                'message' => 'Aula marcada como finalizada',
                'next_video' => $nextVideo,
                'progress' => [
                    'completed' => $progress['completed_videos'],
                    'total' => $progress['total_videos'],
                    'percentage' => $progressPercentage
                ]
            ]);
            break;
            
        case 'GET':
            // Buscar progresso das aulas
            $productId = $_GET['product_id'] ?? null;
            
            if (!$productId) {
                throw new Exception('ID do produto não fornecido');
            }
            
            // Buscar todas as aulas do produto com progresso
            $stmt = $pdo->prepare("
                SELECT 
                    pv.*,
                    lp.completed_at,
                    CASE WHEN lp.completed_at IS NOT NULL THEN 1 ELSE 0 END as is_completed
                FROM product_videos pv
                LEFT JOIN lesson_progress lp ON pv.id = lp.video_id AND lp.user_id = ?
                WHERE pv.product_id = ?
                ORDER BY pv.id ASC
            ");
            $stmt->execute([$userId, $productId]);
            $videos = $stmt->fetchAll();
            
            // Calcular progresso geral
            $totalVideos = count($videos);
            $completedVideos = count(array_filter($videos, function($v) { return $v['is_completed']; }));
            $progressPercentage = $totalVideos > 0 ? round(($completedVideos / $totalVideos) * 100) : 0;
            
            echo json_encode([
                'success' => true,
                'videos' => $videos,
                'progress' => [
                    'completed' => $completedVideos,
                    'total' => $totalVideos,
                    'percentage' => $progressPercentage
                ]
            ]);
            break;
            
        default:
            throw new Exception('Método não permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
