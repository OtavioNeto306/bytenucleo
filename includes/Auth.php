<?php
// Não incluir database.php aqui, pois será incluído pelo arquivo que chama esta classe

class Auth {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Registrar novo usuário
    public function register($name, $email, $password) {
        try {
            // Verificar se email já existe
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Email já cadastrado'];
            }

            // Hash da senha
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Inserir usuário (role_id = 3 = usuário comum)
            $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password, role_id, status) VALUES (?, ?, ?, 3, 'active')");
            $stmt->execute([$name, $email, $hashedPassword]);
            
            // Obter ID do usuário recém-criado
            $userId = $this->pdo->lastInsertId();
            
            // Criar notificação de boas-vindas
            require_once 'Notification.php';
            $notification = new Notification($this->pdo);
            $notification->createWelcomeNotification($userId, $name);

            return ['success' => true, 'message' => 'Usuário registrado com sucesso'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao registrar: ' . $e->getMessage()];
        }
    }

    // Fazer login
    public function login($email, $password) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, r.name as role_name, r.description as role_description 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.email = ? AND u.status = 'active'
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Criar sessão
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_avatar'] = $user['avatar'];
                $_SESSION['user_role'] = $user['role_name'];
                $_SESSION['user_role_id'] = $user['role_id'];
                $_SESSION['logged_in'] = true;

                return ['success' => true, 'message' => 'Login realizado com sucesso'];
            } else {
                return ['success' => false, 'message' => 'Email ou senha incorretos'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro ao fazer login: ' . $e->getMessage()];
        }
    }

    // Verificar se está logado
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    // Obter dados do usuário logado
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, r.name as role_name, r.description as role_description 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }

    // Fazer logout
    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Logout realizado com sucesso'];
    }

    // Verificar se tem assinatura ativa
    public function hasActiveSubscription($userId = null) {
        if (!$userId) {
            $userId = $_SESSION['user_id'] ?? null;
        }

        if (!$userId) return false;

        try {
            // Primeiro, verificar e corrigir assinaturas expiradas do usuário
            $this->checkAndFixExpiredSubscriptions($userId);
            
            $stmt = $this->pdo->prepare("
                SELECT us.* FROM user_subscriptions us 
                WHERE us.user_id = ? AND (us.status = 'active' OR us.status = 'cancelling')
                AND us.end_date > NOW()
                ORDER BY us.end_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Verificar e corrigir assinaturas expiradas do usuário específico
    private function checkAndFixExpiredSubscriptions($userId) {
        try {
            // Buscar assinaturas expiradas do usuário
            $stmt = $this->pdo->prepare("
                SELECT id FROM user_subscriptions 
                WHERE user_id = ? AND status = 'active' AND end_date < NOW()
            ");
            $stmt->execute([$userId]);
            $expiredSubscriptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($expiredSubscriptions)) {
                // Marcar como expiradas
                $stmt = $this->pdo->prepare("
                    UPDATE user_subscriptions 
                    SET status = 'expired' 
                    WHERE user_id = ? AND status = 'active' AND end_date < NOW()
                ");
                $stmt->execute([$userId]);
                
                // Resetar plano do usuário para básico
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET current_plan_id = 1, subscription_expires_at = NULL 
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                
                // Atualizar sessão se for o usuário logado
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                    $_SESSION['current_plan_id'] = 1;
                    $_SESSION['subscription_expires_at'] = null;
                }
            }
        } catch (PDOException $e) {
            // Log do erro mas não interrompe o fluxo
            error_log("Erro ao verificar assinaturas expiradas para usuário $userId: " . $e->getMessage());
        }
    }
    
    // Obter assinatura ativa
    public function getActiveSubscription($userId = null) {
        if (!$userId) {
            $userId = $_SESSION['user_id'] ?? null;
        }

        if (!$userId) {
            // Não vamos logar isso pois é normal quando não há usuário logado
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT us.*, sp.name as plan_name, sp.features 
                FROM user_subscriptions us 
                JOIN subscription_plans sp ON us.plan_id = sp.id
                WHERE us.user_id = ? AND (us.status = 'active' OR us.status = 'cancelling')
                AND us.end_date > NOW()
                ORDER BY us.end_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result;
        } catch (PDOException $e) {
            // Criar logger local para este erro
            $logger = new Logger('cancelamento.log');
            $logger->error("❌ getActiveSubscription: Erro PDO: " . $e->getMessage());
            return null;
        }
    }

    // Verificar se usuário tem permissão específica
    public function hasPermission($permissionName, $userId = null) {
        if (!$userId) {
            $userId = $_SESSION['user_id'] ?? null;
        }

        if (!$userId) return false;

        try {
            // Primeiro, verificar se a permissão existe na tabela user_permissions (permissões individuais)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM user_permissions up
                JOIN permissions p ON up.permission = p.slug
                WHERE up.user_id = ? AND p.slug = ? AND up.granted = TRUE
            ");
            $stmt->execute([$userId, $permissionName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                return true;
            }
            
            // Se não encontrou permissão individual, verificar permissões do role
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM users u
                JOIN role_permissions rp ON u.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE u.id = ? AND p.slug = ?
            ");
            $stmt->execute([$userId, $permissionName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Verificar se usuário tem role específica
    public function hasRole($roleName, $userId = null) {
        if (!$userId) {
            $userId = $_SESSION['user_id'] ?? null;
        }

        if (!$userId) return false;

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND r.name = ?
            ");
            $stmt->execute([$userId, $roleName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Verificar se é super admin
    public function isSuperAdmin($userId = null) {
        return $this->hasRole('super_admin', $userId);
    }

    // Verificar se é admin
    public function isAdmin($userId = null) {
        return $this->hasRole('admin', $userId) || $this->hasRole('super_admin', $userId);
    }

    // Obter todas as permissões do usuário
    public function getUserPermissions($userId = null) {
        if (!$userId) {
            $userId = $_SESSION['user_id'] ?? null;
        }

        if (!$userId) return [];

        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT p.name, p.description
                FROM users u
                JOIN role_permissions rp ON u.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE u.id = ?
                ORDER BY p.name
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Obter todos os roles disponíveis
    public function getAllRoles() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM roles ORDER BY id");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Obter todas as permissões disponíveis
    public function getAllPermissions() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM permissions ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Obter nome do role pelo ID
    public function getRoleName($roleId) {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM roles WHERE id = ?");
            $stmt->execute([$roleId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['name'] : 'Usuário';
        } catch (PDOException $e) {
            return 'Usuário';
        }
    }
    
    // Obter ID do usuário logado
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    // Obter role do usuário logado
    public function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    // Obter role ID do usuário logado
    public function getUserRoleId() {
        return $_SESSION['user_role_id'] ?? null;
    }
}
?>
