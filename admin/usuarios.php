<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';

$auth = new Auth($pdo);

// Verificar se usuário está logado e é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Processar ações
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role_id = $_POST['role_id'] ?? 3;
            $phone = trim($_POST['phone'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $current_plan_id = $_POST['current_plan_id'] ?? 1;
            $subscription_expires_at = $_POST['subscription_expires_at'] ?? null;
            
            if (empty($name) || empty($email) || empty($password)) {
                $error = 'Nome, email e senha são obrigatórios';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email inválido';
            } elseif (strlen($password) < 6) {
                $error = 'A senha deve ter pelo menos 6 caracteres';
            } else {
                // Verificar se email já existe
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $error = 'Este email já está em uso';
                } else {
                    // Hash da senha
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    try {
                        $pdo->beginTransaction();
                        
                        // Criar usuário
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role_id, phone, bio, status, current_plan_id, subscription_expires_at) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?)");
                        $stmt->execute([$name, $email, $hashedPassword, $role_id, $phone, $bio, $current_plan_id, $subscription_expires_at]);
                        $userId = $pdo->lastInsertId();
                        
                        // Se o plano não é básico (ID 1) e há data de expiração, criar assinatura
                        if ($current_plan_id != 1 && $subscription_expires_at) {
                            $stmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status, created_at) VALUES (?, ?, NOW(), ?, 'active', NOW())");
                            $stmt->execute([$userId, $current_plan_id, $subscription_expires_at]);
                        }
                        
                        // Log da criação (opcional)
                        error_log("Admin criou usuário $userId: plano $current_plan_id, expiração $subscription_expires_at");
                        
                        $pdo->commit();
                        $message = 'Usuário criado com sucesso!';
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $error = 'Erro ao criar usuário: ' . $e->getMessage();
                    }
                }
            }
            break;
            
        case 'update_user':
            $user_id = $_POST['user_id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role_id = $_POST['role_id'] ?? 3;
            $status = $_POST['status'] ?? 'active';
            $phone = trim($_POST['phone'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $current_plan_id = $_POST['current_plan_id'] ?? 1;
            $subscription_expires_at = $_POST['subscription_expires_at'] ?? null;
            
            if (empty($user_id) || empty($name) || empty($email)) {
                $error = 'Dados obrigatórios não fornecidos';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email inválido';
            } else {
                // Verificar se email já existe (exceto para o usuário atual)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->rowCount() > 0) {
                    $error = 'Este email já está em uso';
                } else {
                        try {
                            $pdo->beginTransaction();
                            
                            // Obter dados atuais do usuário para comparar plano
                            $stmt = $pdo->prepare("SELECT current_plan_id FROM users WHERE id = ?");
                            $stmt->execute([$user_id]);
                            $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
                            $oldPlanId = $currentUser['current_plan_id'] ?? 1;
                            
                            // Atualizar dados básicos do usuário
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role_id = ?, status = ?, phone = ?, bio = ?, current_plan_id = ?, subscription_expires_at = ? WHERE id = ?");
                            $stmt->execute([$name, $email, $role_id, $status, $phone, $bio, $current_plan_id, $subscription_expires_at, $user_id]);
                            
                            // Se o plano foi alterado e não é o plano básico (ID 1), criar/atualizar assinatura
                            if ($current_plan_id != $oldPlanId && $current_plan_id != 1) {
                                // Desativar assinaturas anteriores
                                $stmt = $pdo->prepare("UPDATE user_subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active'");
                                $stmt->execute([$user_id]);
                                
                                // Criar nova assinatura se há data de expiração
                                if ($subscription_expires_at) {
                                    $stmt = $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status, created_at) VALUES (?, ?, NOW(), ?, 'active', NOW())");
                                    $stmt->execute([$user_id, $current_plan_id, $subscription_expires_at]);
                                }
                            } elseif ($current_plan_id == 1) {
                                // Se mudou para plano básico, desativar assinaturas
                                $stmt = $pdo->prepare("UPDATE user_subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active'");
                                $stmt->execute([$user_id]);
                            }
                            
                            // Log da alteração (opcional)
                            error_log("Admin atualizou usuário $user_id: plano $current_plan_id, expiração $subscription_expires_at");
                            
                            $pdo->commit();
                            $message = 'Usuário atualizado com sucesso!';
                        } catch (PDOException $e) {
                            $pdo->rollBack();
                            $error = 'Erro ao atualizar usuário: ' . $e->getMessage();
                        }
                }
            }
            break;
            
        case 'delete_user':
            $user_id = $_POST['user_id'] ?? '';
            
            if (empty($user_id)) {
                $error = 'ID do usuário não fornecido';
            } elseif ($user_id == $_SESSION['user_id']) {
                $error = 'Você não pode excluir sua própria conta';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $message = 'Usuário excluído com sucesso!';
                } catch (PDOException $e) {
                    $error = 'Erro ao excluir usuário: ' . $e->getMessage();
                }
            }
            break;
            
        case 'change_password':
            $user_id = $_POST['user_id'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            
            if (empty($user_id) || empty($new_password)) {
                $error = 'Dados obrigatórios não fornecidos';
            } elseif (strlen($new_password) < 6) {
                $error = 'A nova senha deve ter pelo menos 6 caracteres';
            } else {
                try {
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $user_id]);
                    $message = 'Senha alterada com sucesso!';
                } catch (PDOException $e) {
                    $error = 'Erro ao alterar senha: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Obter todos os usuários com paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtros
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$role_filter = $_GET['role'] ?? '';

try {
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "u.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($role_filter)) {
        $where_conditions[] = "u.role_id = ?";
        $params[] = $role_filter;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Contar total de registros
    $count_sql = "SELECT COUNT(*) FROM users u LEFT JOIN roles r ON u.role_id = r.id $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Buscar usuários
    $sql = "SELECT u.*, r.name as role_name, r.description as role_description
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            $where_clause
            ORDER BY u.created_at DESC
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $error = 'Erro ao carregar usuários: ' . $e->getMessage();
    $total_pages = 0;
}

// Obter roles
$roles = $auth->getAllRoles();

// Obter planos de assinatura
try {
    $stmt = $pdo->query("SELECT id, name, price FROM subscription_plans ORDER BY price ASC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $plans = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include '../partials/head.php' ?>

<body>

    <?php include '../partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include '../partials/navbar.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Gerenciar Usuários</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Administre usuários, roles e permissões do sistema
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mensagens -->
        <?php if ($message): ?>
        <section class="py-24">
            <div class="container">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ri-check-circle-line me-12"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($error): ?>
        <section class="py-24">
            <div class="container">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ri-error-warning-line me-12"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Lista de Usuários -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
                                <div class="d-flex align-items-center flex-wrap gap-3">
                                    <span class="text-md fw-medium text-neutral-600 mb-0">Mostrar</span>
                                    <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" id="limitSelect">
                                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                    </select>
                                    
                                    <!-- Busca -->
                                                                         <form class="navbar-search" method="GET">
                                         <input type="text" class="bg-base h-40-px w-auto" name="search" placeholder="Buscar usuários..." value="<?= htmlspecialchars($search) ?>">
                                         <i class="ri-search-line"></i>
                                     </form>
                                    
                                    <!-- Filtro de Status -->
                                    <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" name="status" onchange="this.form.submit()">
                                        <option value="">Todos os Status</option>
                                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Ativo</option>
                                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                    </select>
                                    
                                    <!-- Filtro de Role -->
                                    <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" name="role" onchange="this.form.submit()">
                                        <option value="">Todos os Níveis</option>
                                        <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['id'] ?>" <?= $role_filter == $role['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($role['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                                                 <button type="button" class="btn btn-primary text-sm btn-sm px-12 py-12 radius-8 d-flex align-items-center gap-2" 
                                         data-bs-toggle="modal" data-bs-target="#createUserModal">
                                     <i class="ri-add-line"></i>
                                     Adicionar Usuário
                                 </button>
                            </div>
                            
                            <div class="card-body p-24">
                                <div class="table-responsive scroll-sm">
                                    <table class="table bordered-table sm-table mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">
                                                    <div class="d-flex align-items-center gap-10">
                                                        <div class="form-check style-check d-flex align-items-center">
                                                            <input class="form-check-input radius-4 border input-form-dark" type="checkbox" name="checkbox" id="selectAll">
                                                        </div>
                                                        ID
                                                    </div>
                                                </th>
                                                <th scope="col">Data de Cadastro</th>
                                                <th scope="col">Nome</th>
                                                <th scope="col">Email</th>
                                                <th scope="col">Telefone</th>
                                                <th scope="col">Nível de Acesso</th>
                                                <th scope="col">Plano Atual</th>
                                                <th scope="col" class="text-center">Status</th>
                                                <th scope="col" class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($users)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-40">
                                                    <i class="ri-user-line text-muted" style="font-size: 3rem;"></i>
                                                    <h5 class="mt-16 mb-8">Nenhum usuário encontrado</h5>
                                                    <p class="text-neutral-600">Não há usuários que correspondam aos filtros aplicados.</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($users as $index => $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-10">
                                                            <div class="form-check style-check d-flex align-items-center">
                                                                <input class="form-check-input radius-4 border border-neutral-400" type="checkbox" name="checkbox">
                                                            </div>
                                                            <?= $user['id'] ?>
                                                        </div>
                                                    </td>
                                                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                                                <div style="background-image: url('<?= htmlspecialchars($user['avatar']) ?>'); width: 40px; height: 40px; border-radius: 100%; background-size: cover; background-repeat: no-repeat; background-position: center; border: 1px solid var(--primary-600);" class="flex-shrink-0 me-12 overflow-hidden"></div>
                                                            <?php else: ?>
                                                                <div class="w-40-px h-40-px rounded-circle flex-shrink-0 me-12 overflow-hidden bg-primary d-flex align-items-center justify-content-center">
                                                                    <i class="ri-user-line" style="font-size: 1.2rem;"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="flex-grow-1">
                                                                <span class="text-md mb-0 fw-normal text-neutral-600"><?= htmlspecialchars($user['name']) ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><span class="text-md mb-0 fw-normal text-neutral-600"><?= htmlspecialchars($user['email']) ?></span></td>
                                                    <td><span class="text-md mb-0 fw-normal text-neutral-600"><?= htmlspecialchars($user['phone'] ?? '-') ?></span></td>
                                                    <td>
                                                        <span class="bg-<?= $user['role_name'] === 'super_admin' ? 'danger' : ($user['role_name'] === 'admin' ? 'warning' : 'info') ?>-focus text-<?= $user['role_name'] === 'super_admin' ? 'danger' : ($user['role_name'] === 'admin' ? 'warning' : 'info') ?>-600 border border-<?= $user['role_name'] === 'super_admin' ? 'danger' : ($user['role_name'] === 'admin' ? 'warning' : 'info') ?>-main px-24 py-4 radius-4 fw-medium text-sm">
                                                            <?= htmlspecialchars($user['role_name']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $planName = 'Básico';
                                                        foreach ($plans as $plan) {
                                                            if ($plan['id'] == $user['current_plan_id']) {
                                                                $planName = $plan['name'];
                                                                break;
                                                            }
                                                        }
                                                        ?>
                                                        <span class="bg-primary-focus text-primary-600 border border-primary-main px-24 py-4 radius-4 fw-medium text-sm">
                                                            <?= htmlspecialchars($planName) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="bg-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'neutral') ?>-focus text-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'neutral') ?>-600 border border-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'neutral') ?>-main px-24 py-4 radius-4 fw-medium text-sm">
                                                            <?= ucfirst($user['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                                                                                 <div class="d-flex align-items-center gap-10 justify-content-center">
                                                             <button type="button" class="bg-info-focus bg-hover-info-200 text-info-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"
                                                                     onclick="viewUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                                                 <i class="ri-eye-line"></i>
                                                             </button>
                                                             <button type="button" class="bg-success-focus text-success-600 bg-hover-success-200 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"
                                                                     onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                                                 <i class="ri-edit-line"></i>
                                                             </button>
                                                             <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                             <button type="button" class="remove-item-btn bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"
                                                                     onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')">
                                                                 <i class="ri-delete-bin-line"></i>
                                                             </button>
                                                             <?php endif; ?>
                                                         </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Paginação -->
                                <?php if ($total_pages > 1): ?>
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-24">
                                    <span>Mostrando <?= $offset + 1 ?> a <?= min($offset + $limit, $total_records) ?> de <?= $total_records ?> registros</span>
                                    <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
                                                                                 <?php if ($page > 1): ?>
                                         <li class="page-item">
                                                                             <a class="page-link bg-neutral-200 text-neutral-600 fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" 
                                                 href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&role=<?= urlencode($role_filter) ?>">
                                                 <i class="ri-arrow-left-s-line"></i>
                                             </a>
                                         </li>
                                         <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item">
                                            <a class="page-link <?= $i == $page ? 'bg-primary-600 text-white' : 'bg-neutral-200 text-neutral-600' ?> fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px" 
                                               href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&role=<?= urlencode($role_filter) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                                                                 <?php if ($page < $total_pages): ?>
                                         <li class="page-item">
                                                                             <a class="page-link bg-neutral-200 text-neutral-600 fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" 
                                                 href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&role=<?= urlencode($role_filter) ?>">
                                                 <i class="ri-arrow-right-s-line"></i>
                                             </a>
                                         </li>
                                         <?php endif; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php include '../partials/footer.php' ?>
    </main>

    <!-- Modal Criar Usuário -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Novo Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nome Completo <span class="text-danger-600">*</span></label>
                                <input type="text" name="name" class="form-control radius-8" required>
                            </div>
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Email <span class="text-danger-600">*</span></label>
                                <input type="email" name="email" class="form-control radius-8" required>
                            </div>
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Senha <span class="text-danger-600">*</span></label>
                                <input type="password" name="password" class="form-control radius-8" required>
                            </div>
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Telefone</label>
                                <input type="tel" name="phone" class="form-control radius-8">
                            </div>
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nível de Acesso <span class="text-danger-600">*</span></label>
                                <select name="role_id" class="form-select radius-8" required>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?> - <?= htmlspecialchars($role['description']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Biografia</label>
                                <textarea name="bio" class="form-control radius-8" rows="3" placeholder="Conte um pouco sobre o usuário..."></textarea>
                            </div>
                            
                            <!-- Seção de Plano e Assinatura -->
                            <div class="col-md-12 mb-24">
                                <hr class="my-16">
                                <h6 class="fw-semibold text-primary-light mb-16">Configuração de Plano</h6>
                            </div>
                            
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Plano de Assinatura</label>
                                <select name="current_plan_id" class="form-select radius-8">
                                    <option value="1">Básico (Gratuito)</option>
                                    <?php foreach ($plans as $plan): ?>
                                    <option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?> - R$ <?= number_format($plan['price'], 2, ',', '.') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Data de Expiração</label>
                                <input type="datetime-local" name="subscription_expires_at" class="form-control radius-8">
                                <div class="form-text text-secondary-light">Deixe vazio para plano sem expiração</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar Usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Usuário -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nome Completo <span class="text-danger-600">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control radius-8" required>
                            </div>
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Email <span class="text-danger-600">*</span></label>
                                <input type="email" name="email" id="edit_email" class="form-control radius-8" required>
                            </div>
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Telefone</label>
                                <input type="tel" name="phone" id="edit_phone" class="form-control radius-8">
                            </div>
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nível de Acesso <span class="text-danger-600">*</span></label>
                                <select name="role_id" id="edit_role_id" class="form-select radius-8" required>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?> - <?= htmlspecialchars($role['description']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Status <span class="text-danger-600">*</span></label>
                                <select name="status" id="edit_status" class="form-select radius-8" required>
                                    <option value="active">Ativo</option>
                                    <option value="inactive">Inativo</option>
                                    <option value="pending">Pendente</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Biografia</label>
                                <textarea name="bio" id="edit_bio" class="form-control radius-8" rows="3" placeholder="Conte um pouco sobre o usuário..."></textarea>
                            </div>
                            
                            <!-- Seção de Plano e Assinatura -->
                            <div class="col-md-12 mb-24">
                                <hr class="my-16">
                                <h6 class="fw-semibold text-primary-light mb-16">Configuração de Plano</h6>
                            </div>
                            
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Plano de Assinatura</label>
                                <select name="current_plan_id" id="edit_current_plan_id" class="form-select radius-8">
                                    <option value="1">Básico (Gratuito)</option>
                                    <?php foreach ($plans as $plan): ?>
                                    <option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?> - R$ <?= number_format($plan['price'], 2, ',', '.') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-24">
                                <label class="form-label fw-semibold text-primary-light text-sm mb-8">Data de Expiração</label>
                                <input type="datetime-local" name="subscription_expires_at" id="edit_subscription_expires_at" class="form-control radius-8">
                                <div class="form-text text-secondary-light">Deixe vazio para plano sem expiração</div>
                            </div>
                            
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar Usuário -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-24 text-center">
                            <div id="view_avatar" style="width: 120px; height: 120px; margin: 0 auto; border-radius: 100%; background-size: cover; background-repeat: no-repeat; background-position: center; border: 1px solid var(--primary-600);"></div>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nome Completo</label>
                            <p class="text-neutral-600" id="view_name"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Email</label>
                            <p class="text-neutral-600" id="view_email"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Telefone</label>
                            <p class="text-neutral-600" id="view_phone"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nível de Acesso</label>
                            <p class="text-neutral-600" id="view_role"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Status</label>
                            <p class="text-neutral-600" id="view_status"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Data de Cadastro</label>
                            <p class="text-neutral-600" id="view_created_at"></p>
                        </div>
                        <div class="col-md-12 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Biografia</label>
                            <p class="text-neutral-600" id="view_bio"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Alterar Senha -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Alterar Senha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="user_id" id="change_password_user_id">
                    <div class="modal-body">
                        <div class="mb-24">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nova Senha <span class="text-danger-600">*</span></label>
                            <input type="password" name="new_password" class="form-control radius-8" required>
                        </div>
                        <div class="mb-24">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Confirmar Nova Senha <span class="text-danger-600">*</span></label>
                            <input type="password" name="confirm_password" class="form-control radius-8" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Alterar Senha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Exclusão -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-24">
                        <i class="ri-error-warning-line text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <p>Tem certeza que deseja excluir o usuário <strong id="delete_user_name"></strong>?</p>
                    <p class="text-danger">Esta ação não pode ser desfeita!</p>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Excluir Usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../partials/scripts.php' ?>

    <script>
    // Selecionar todos
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Visualizar usuário
    function viewUser(user) {
        document.getElementById('view_name').textContent = user.name;
        document.getElementById('view_email').textContent = user.email;
        document.getElementById('view_phone').textContent = user.phone || '-';
        document.getElementById('view_role').textContent = user.role_name;
        document.getElementById('view_status').textContent = user.status.charAt(0).toUpperCase() + user.status.slice(1);
        document.getElementById('view_created_at').textContent = new Date(user.created_at).toLocaleDateString('pt-BR');
        document.getElementById('view_bio').textContent = user.bio || '-';
        
        const avatarDiv = document.getElementById('view_avatar');
        if (user.avatar && user.avatar.trim() !== '') {
            avatarDiv.style.backgroundImage = `url('${user.avatar}')`;
        } else {
            avatarDiv.style.backgroundImage = 'none';
            avatarDiv.style.backgroundColor = '#f8f9fa';
            avatarDiv.innerHTML = '<i class="ri-user-line" style="font-size: 3rem; color: #667eea; line-height: 120px;"></i>';
        }
        
        new bootstrap.Modal(document.getElementById('viewUserModal')).show();
    }

    // Editar usuário
    function editUser(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_name').value = user.name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_phone').value = user.phone || '';
        document.getElementById('edit_role_id').value = user.role_id;
        document.getElementById('edit_status').value = user.status;
        document.getElementById('edit_bio').value = user.bio || '';
        document.getElementById('edit_current_plan_id').value = user.current_plan_id || 1;
        
        // Formatar data de expiração para datetime-local
        if (user.subscription_expires_at) {
            const date = new Date(user.subscription_expires_at);
            const formattedDate = date.toISOString().slice(0, 16);
            document.getElementById('edit_subscription_expires_at').value = formattedDate;
        } else {
            document.getElementById('edit_subscription_expires_at').value = '';
        }
        
        
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }

    // Excluir usuário
    function deleteUser(userId, userName) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('delete_user_name').textContent = userName;
        
        new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
    }

    // Alterar senha
    function changePassword(userId, userName) {
        document.getElementById('change_password_user_id').value = userId;
        
        new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
    }

    // Remover item (efeito visual)
    document.querySelectorAll('.remove-item-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            this.closest('tr').classList.add('d-none');
        });
    });
    </script>

</body>
</html>
