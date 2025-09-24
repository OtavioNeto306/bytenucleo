<?php
// Classe para gerenciar notícias e avisos do sistema

// Incluir a classe Notification se não estiver incluída
if (!class_exists('Notification')) {
    require_once __DIR__ . '/Notification.php';
}

class News {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ===== CRUD BÁSICO =====

    // Criar nova notícia
    public function create($data) {
        $sql = "INSERT INTO news (title, content, type, priority, created_by) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        if ($stmt->execute([
            $data['title'],
            $data['content'],
            $data['type'] ?? 'info',
            $data['priority'] ?? 'medium',
            $data['created_by']
        ])) {
            $newsId = $this->pdo->lastInsertId();
            
            // Criar notificações para todos os usuários
            $this->createNotificationsForAllUsers($newsId, $data);
            
            return $newsId;
        }
        
        return false;
    }

    // Obter notícia por ID
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT n.*, u.name as author_name 
            FROM news n 
            LEFT JOIN users u ON n.created_by = u.id 
            WHERE n.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Listar todas as notícias (admin)
    public function getAll($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filters['type'])) {
            $where .= " AND n.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['priority'])) {
            $where .= " AND n.priority = ?";
            $params[] = $filters['priority'];
        }

        if (!empty($filters['search'])) {
            $where .= " AND (n.title LIKE ? OR n.content LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql = "
            SELECT n.*, u.name as author_name 
            FROM news n 
            LEFT JOIN users u ON n.created_by = u.id 
            {$where}
            ORDER BY n.created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        // Converter para inteiros explicitamente
        $limitInt = (int)$limit;
        $offsetInt = (int)$offset;
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind dos parâmetros com tipos corretos
        $paramIndex = 1;
        foreach ($params as $param) {
            $stmt->bindValue($paramIndex++, $param);
        }
        $stmt->bindValue($paramIndex++, $limitInt, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex++, $offsetInt, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Listar notícias ativas para usuários
    public function getActiveNews($limit = 10) {
        $limitInt = (int)$limit;
        $stmt = $this->pdo->prepare("
            SELECT n.*, u.name as author_name 
            FROM news n 
            LEFT JOIN users u ON n.created_by = u.id 
            WHERE n.is_active = 1 
            ORDER BY n.priority DESC, n.created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $limitInt, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Atualizar notícia
    public function update($id, $data) {
        $sql = "UPDATE news SET title = ?, content = ?, type = ?, priority = ?, is_active = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['title'],
            $data['content'],
            $data['type'] ?? 'info',
            $data['priority'] ?? 'medium',
            $data['is_active'] ?? true,
            $id
        ]);
    }

    // Excluir notícia
    public function delete($id) {
        // Primeiro excluir notificações relacionadas
        $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE type = 'news' AND link LIKE ?");
        $stmt->execute(["news.php?id={$id}"]);
        
        // Depois excluir a notícia
        $stmt = $this->pdo->prepare("DELETE FROM news WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ===== ESTATÍSTICAS =====

    // Contar total de notícias
    public function getCount($filters = []) {
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($filters['type'])) {
            $where .= " AND type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['priority'])) {
            $where .= " AND priority = ?";
            $params[] = $filters['priority'];
        }

        if (!empty($filters['search'])) {
            $where .= " AND (title LIKE ? OR content LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql = "SELECT COUNT(*) FROM news {$where}";
        $stmt = $this->pdo->prepare($sql);
        
        // Bind dos parâmetros com tipos corretos
        $paramIndex = 1;
        foreach ($params as $param) {
            $stmt->bindValue($paramIndex++, $param);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // Obter estatísticas
    public function getStats() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_news,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_news,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as `high_priority`,
                COUNT(CASE WHEN type = 'info' THEN 1 END) as info_count,
                COUNT(CASE WHEN type = 'warning' THEN 1 END) as warning_count,
                COUNT(CASE WHEN type = 'success' THEN 1 END) as success_count,
                COUNT(CASE WHEN type = 'danger' THEN 1 END) as danger_count
            FROM news
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ===== NOTIFICAÇÕES =====

    // Criar notificações para todos os usuários
    private function createNotificationsForAllUsers($newsId, $newsData) {
        // Obter todos os usuários ativos
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE status = 'active'");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Criar notificação para cada usuário
        $notification = new Notification($this->pdo);
        
        foreach ($users as $userId) {
            $notification->createFromArray([
                'user_id' => $userId,
                'type' => 'news',
                'title' => $newsData['title'],
                'message' => substr($newsData['content'], 0, 150) . (strlen($newsData['content']) > 150 ? '...' : ''),
                'icon' => $this->getTypeIcon($newsData['type']),
                'link' => null // Não precisa de link, aparece direto nas notificações
            ]);
        }
    }

    // Obter ícone baseado no tipo
    public function getTypeIcon($type) {
        $icons = [
            'info' => 'ri-information-line',
            'warning' => 'ri-alert-line',
            'success' => 'ri-check-line',
            'danger' => 'ri-error-warning-line'
        ];
        
        return $icons[$type] ?? 'ri-information-line';
    }

    // ===== UTILITÁRIOS =====

    // Obter tipos disponíveis
    public function getTypes() {
        return [
            'info' => 'Informação',
            'warning' => 'Aviso',
            'success' => 'Sucesso',
            'danger' => 'Urgente'
        ];
    }

    // Obter prioridades disponíveis
    public function getPriorities() {
        return [
            'low' => 'Baixa',
            'medium' => 'Média',
            'high' => 'Alta'
        ];
    }

    // Formatar data
    public function formatDate($date) {
        $timestamp = strtotime($date);
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return 'Agora mesmo';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return "Há {$minutes} min" . ($minutes > 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "Há {$hours} hora" . ($hours > 1 ? 's' : '');
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return "Há {$days} dia" . ($days > 1 ? 's' : '');
        } else {
            return date('d/m/Y H:i', $timestamp);
        }
    }

    // Obter cor do tipo
    public function getTypeColor($type) {
        $colors = [
            'info' => 'info',
            'warning' => 'warning',
            'success' => 'success',
            'danger' => 'danger'
        ];
        
        return $colors[$type] ?? 'info';
    }
}
?>
