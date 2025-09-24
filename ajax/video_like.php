<?php
require_once '../config/database.php';

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

// Se for GET, apenas retornar status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $video_id = isset($_GET['video_id']) ? (int)$_GET['video_id'] : 0;
    $user_id = $_SESSION['user_id'];
    
    if ($video_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do vídeo inválido']);
        exit;
    }
    
    try {
        // Verificar se usuário curtiu
        $stmt = $pdo->prepare("SELECT id FROM video_likes WHERE video_id = ? AND user_id = ?");
        $stmt->execute([$video_id, $user_id]);
        $liked = $stmt->rowCount() > 0;
        
        // Contar total de curtidas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM video_likes WHERE video_id = ?");
        $stmt->execute([$video_id]);
        $total_likes = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'total_likes' => (int)$total_likes
        ]);
        
    } catch (PDOException $e) {
        error_log("Erro ao verificar curtidas: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    }
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar dados recebidos
if (!isset($_POST['video_id']) || !isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$video_id = (int)$_POST['video_id'];
$user_id = $_SESSION['user_id'];
$action = $_POST['action']; // 'like' ou 'unlike'

try {
    if ($action === 'like') {
        // Verificar se já curtiu
        $stmt = $pdo->prepare("SELECT id FROM video_likes WHERE video_id = ? AND user_id = ?");
        $stmt->execute([$video_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Você já curtiu este vídeo']);
            exit;
        }
        
        // Adicionar curtida
        $stmt = $pdo->prepare("INSERT INTO video_likes (video_id, user_id) VALUES (?, ?)");
        $stmt->execute([$video_id, $user_id]);
        
        $liked = true;
        
    } elseif ($action === 'unlike') {
        // Remover curtida
        $stmt = $pdo->prepare("DELETE FROM video_likes WHERE video_id = ? AND user_id = ?");
        $stmt->execute([$video_id, $user_id]);
        
        $liked = false;
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        exit;
    }
    
    // Contar total de curtidas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM video_likes WHERE video_id = ?");
    $stmt->execute([$video_id]);
    $total_likes = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'total_likes' => (int)$total_likes,
        'message' => $liked ? 'Vídeo curtido!' : 'Curtida removida!'
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao processar curtida: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
