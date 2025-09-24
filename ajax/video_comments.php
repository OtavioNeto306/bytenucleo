<?php
require_once '../config/database.php';

// Log para debug
error_log("API video_comments.php chamada - Método: " . $_SERVER['REQUEST_METHOD']);
error_log("Session ID: " . session_id());
error_log("User ID na sessão: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NÃO DEFINIDO'));

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    error_log("Erro: Usuário não logado");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

// Pegar video_id dependendo do método
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $video_id = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
} else {
    $video_id = isset($_GET['video_id']) ? (int)$_GET['video_id'] : 0;
}

if ($video_id <= 0) {
    error_log("Erro: video_id inválido - POST: " . (isset($_POST['video_id']) ? $_POST['video_id'] : 'NÃO DEFINIDO') . " GET: " . (isset($_GET['video_id']) ? $_GET['video_id'] : 'NÃO DEFINIDO'));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do vídeo inválido']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("POST recebido - video_id: " . (isset($_POST['video_id']) ? $_POST['video_id'] : 'NÃO DEFINIDO'));
        error_log("POST recebido - comment: " . (isset($_POST['comment']) ? $_POST['comment'] : 'NÃO DEFINIDO'));
        
        // Adicionar comentário
        if (!isset($_POST['comment']) || empty(trim($_POST['comment']))) {
            error_log("Erro: Comentário vazio");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Comentário não pode estar vazio']);
            exit;
        }
        
        $comment = trim($_POST['comment']);
        $user_id = $_SESSION['user_id'];
        
        // Verificar se o comentário não é muito longo
        if (strlen($comment) > 500) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Comentário muito longo (máximo 500 caracteres)']);
            exit;
        }
        
        // Inserir comentário
        $stmt = $pdo->prepare("INSERT INTO video_comments (video_id, user_id, comment, status) VALUES (?, ?, ?, 'approved')");
        $stmt->execute([$video_id, $user_id, $comment]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Comentário adicionado com sucesso!'
        ]);
        
    } else {
        // Listar comentários
        $stmt = $pdo->prepare("
            SELECT vc.*, u.name as user_name, u.avatar 
            FROM video_comments vc 
            JOIN users u ON vc.user_id = u.id 
            WHERE vc.video_id = ? AND vc.status = 'approved'
            ORDER BY vc.created_at DESC
        ");
        $stmt->execute([$video_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatar dados para exibição
        foreach ($comments as &$comment) {
            $comment['created_at_formatted'] = date('d/m/Y H:i', strtotime($comment['created_at']));
            $comment['avatar_url'] = $comment['avatar'] ?: 'assets/images/default-avatar.png';
        }
        
        echo json_encode([
            'success' => true,
            'comments' => $comments,
            'total' => count($comments)
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao processar comentários: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
