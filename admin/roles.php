<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';

// Função para converter slug para nome em português
function getPermissionDisplayName($slug, $originalName) {
    $mappings = [
        'download_free' => 'Download Gratuito',
        'download_premium' => 'Download Premium', 
        'download_exclusive' => 'Download Exclusivo',
        'unlimited_downloads' => 'Downloads Ilimitados',
        'manage_users' => 'Gerenciar Usuários',
        'manage_products' => 'Gerenciar Produtos',
        'manage_categories' => 'Gerenciar Categorias',
        'manage_plans' => 'Gerenciar Planos',
        'manage_subscriptions' => 'Gerenciar Assinaturas',
        'manage_payments' => 'Gerenciar Pagamentos',
        'manage_news' => 'Gerenciar Avisos',
        'manage_roles' => 'Gerenciar Roles',
        'view_reports' => 'Ver Relatórios',
        'manage_system' => 'Configurações do Sistema',
        'access_premium_content' => 'Acesso a Conteúdo Premium',
        'access_exclusive_content' => 'Acesso a Conteúdo Exclusivo',
        'priority_support' => 'Suporte Prioritário',
        'download_products' => 'Baixar Produtos',
        'view_products' => 'Visualizar Produtos'
    ];
    
    // Se não encontrar no mapeamento, formata o slug removendo underscores
    if (!isset($mappings[$slug])) {
        return ucwords(str_replace('_', ' ', $slug));
    }
    
    return $mappings[$slug];
}

$auth = new Auth($pdo);

// Verificar se usuário está logado e é super admin
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php?redirect=admin/roles.php');
    exit;
}

if (!$auth->isSuperAdmin()) {
    header('Location: ../perfil.php?error=acesso_negado');
    exit;
}

$error = '';
$success = '';

// Processar formulário de atualização de permissões
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_permissions') {
        $role_id = (int)$_POST['role_id'];
        $permissions = $_POST['permissions'] ?? [];
        
        try {
            $pdo->beginTransaction();
            
            // Remover todas as permissões atuais do role
            $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$role_id]);
            
            // Adicionar as novas permissões
            if (!empty($permissions)) {
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($permissions as $permission_id) {
                    $stmt->execute([$role_id, $permission_id]);
                }
            }
            
            $pdo->commit();
            $success = 'Permissões atualizadas com sucesso!';
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Erro ao atualizar permissões: ' . $e->getMessage();
        }
    }
}

// Buscar roles e permissões
try {
    // Buscar todos os roles
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY id");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar todas as permissões
    $stmt = $pdo->query("SELECT id, name, slug, description, category FROM permissions ORDER BY name");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar permissões atuais de cada role
    $rolePermissions = [];
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$role['id']]);
        $rolePermissions[$role['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
} catch (PDOException $e) {
    $error = 'Erro ao carregar dados: ' . $e->getMessage();
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
                        <h1 class="display-4 fw-bold mb-24">Roles & Permissões</h1>
                        <p class="text-lg text-secondary-light mb-0">
                            Gerencie as permissões dos diferentes níveis de usuários
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Conteúdo principal -->
        <section class="py-80">
            <div class="container">
                
                <!-- Alertas -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <?php foreach ($roles as $role): ?>
                    <div class="col-lg-4 mb-40">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-<?= $role['name'] === 'super_admin' ? 'danger' : ($role['name'] === 'admin' ? 'warning' : 'info') ?>-subtle border-0">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-16">
                                        <div class="w-40 h-40 rounded-circle bg-<?= $role['name'] === 'super_admin' ? 'danger' : ($role['name'] === 'admin' ? 'warning' : 'info') ?> d-flex align-items-center justify-content-center">
                                            <i class="ri-user-line text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 text-<?= $role['name'] === 'super_admin' ? 'danger' : ($role['name'] === 'admin' ? 'warning' : 'info') ?>">
                                            <?= ucfirst(str_replace('_', ' ', $role['name'])) ?>
                                        </h5>
                                        <small class="text-primary-light"><?= htmlspecialchars($role['description']) ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-24">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_permissions">
                                    <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                    
                                    <div class="mb-24">
                                        <h6 class="mb-16">Permissões Disponíveis</h6>
                                        <div class="row">
                                                                                         <?php foreach ($permissions as $permission): ?>
                                             <div class="col-12 mb-16">
                                                 <div class="form-check d-flex align-items-start">
                                                     <input class="form-check-input me-3 mt-1" type="checkbox" 
                                                            name="permissions[]" 
                                                            value="<?= $permission['id'] ?>" 
                                                            id="perm_<?= $role['id'] ?>_<?= $permission['id'] ?>"
                                                            <?= in_array($permission['id'], $rolePermissions[$role['id']] ?? []) ? 'checked' : '' ?>>
                                                     <label class="form-check-label flex-grow-1" for="perm_<?= $role['id'] ?>_<?= $permission['id'] ?>">
                                                         <div class="fw-medium text-primary-light mb-2"><?= htmlspecialchars(getPermissionDisplayName($permission['slug'], $permission['name'])) ?></div>
                                                         <small class="text-sm text-secondary-light d-flex align-items-center"><?= htmlspecialchars($permission['description']) ?></small>
                                                     </label>
                                                 </div>
                                             </div>
                                             <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                                                                 <small class="text-primary-light">
                                             <?= count($rolePermissions[$role['id']] ?? []) ?> permissões ativas
                                         </small>
                                        <button type="submit" class="btn btn-primary d-flex align-items-center">
                                            <iconify-icon icon="solar:check-circle-outline" class="me-8"></iconify-icon>
                                            Salvar Permissões
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Informações adicionais -->
                <div class="row mt-40">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-24">
                                <h5 class="card-title mb-16">Informações sobre Roles</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-16">
                                        <div class="d-flex align-items-center">
                                            <div class="w-32 h-32 rounded-circle bg-danger me-12"></div>
                                            <div>
                                                <h6 class="mb-4">Super Admin</h6>
                                                                                                 <small class="text-primary-light">Acesso total ao sistema, incluindo configurações avançadas</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-16">
                                        <div class="d-flex align-items-center">
                                            <div class="w-32 h-32 rounded-circle bg-warning me-12"></div>
                                            <div>
                                                <h6 class="mb-4">Admin</h6>
                                                                                                 <small class="text-primary-light">Gerenciamento de usuários, produtos e conteúdo</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-16">
                                        <div class="d-flex align-items-center">
                                            <div class="w-32 h-32 rounded-circle bg-info me-12"></div>
                                            <div>
                                                <h6 class="mb-4">Usuário</h6>
                                                                                                 <small class="text-primary-light">Acesso básico para visualizar e baixar produtos</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <?php include '../partials/footer.php' ?>
    </main>

    <?php include '../partials/scripts.php' ?>

    <style>
        /* Estilos personalizados para os checkboxes */
        .form-check-input {
            width: 18px;
            height: 18px;
            border: 2px solid #6c757d;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .form-check-input:focus {
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .form-check-label {
            cursor: pointer;
            padding-left: 0;
        }
        
        /* Hover effect */
        .form-check:hover {
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 8px;
            margin: -8px;
            transition: all 0.2s ease;
        }
    </style>

    <script>
        $(document).ready(function() {
            // Atualizar contador de permissões em tempo real
            $('input[type="checkbox"]').change(function() {
                var form = $(this).closest('form');
                var checkedCount = form.find('input[name="permissions[]"]:checked').length;
                form.find('small').text(checkedCount + ' permissões ativas');
            });
            
            // Confirmar antes de salvar
            $('form').submit(function() {
                return confirm('Tem certeza que deseja atualizar as permissões deste role?');
            });
        });
    </script>

</body>
</html>
