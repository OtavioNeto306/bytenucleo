<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';

$auth = new Auth($pdo);

// Verificar se usuário está logado e tem permissão
if (!$auth->isLoggedIn() || !$auth->hasPermission('manage_users')) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Obter todos os usuários
try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, r.description as role_description
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $error = 'Erro ao carregar usuários: ' . $e->getMessage();
}

$script = '<script>
    $(".remove-item-btn").on("click", function() {
        $(this).closest("tr").addClass("d-none")
    });
</script>';
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include '../partials/head.php' ?>

<body>

    <?php include '../partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include '../partials/navbar.php' ?>

        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Lista de Usuários</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="/admin/usuarios.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Dashboard
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Lista de Usuários</li>
                </ul>
            </div>

            <div class="card h-100 p-0 radius-12">
                <div class="card-header border-bottom bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <span class="text-md fw-medium text-secondary-light mb-0">Mostrar</span>
                        <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                            <option>10</option>
                            <option>25</option>
                            <option>50</option>
                            <option>100</option>
                        </select>
                                                 <form class="navbar-search">
                             <input type="text" class="bg-base h-40-px w-auto" name="search" placeholder="Buscar usuários...">
                             <i class="ri-search-line"></i>
                         </form>
                        <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px">
                            <option>Status</option>
                            <option>Ativo</option>
                            <option>Inativo</option>
                            <option>Pendente</option>
                        </select>
                    </div>
                                         <a href="/admin/usuarios.php" class="btn btn-primary text-sm btn-sm px-12 py-12 radius-8 d-flex align-items-center gap-2">
                         <i class="ri-add-line"></i>
                         Adicionar Usuário
                     </a>
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
                                    <th scope="col" class="text-center">Status</th>
                                    <th scope="col" class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-40">
                                        <i class="ri-user-line text-muted" style="font-size: 3rem;"></i>
                                        <h5 class="mt-16 mb-8">Nenhum usuário encontrado</h5>
                                        <p class="text-secondary-light">Não há usuários cadastrados no sistema.</p>
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
                                                        <i class="ri-user-line text-white" style="font-size: 1.2rem;"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <span class="text-md mb-0 fw-normal text-secondary-light"><?= htmlspecialchars($user['name']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="text-md mb-0 fw-normal text-secondary-light"><?= htmlspecialchars($user['email']) ?></span></td>
                                        <td><span class="text-md mb-0 fw-normal text-secondary-light"><?= htmlspecialchars($user['phone'] ?? '-') ?></span></td>
                                        <td>
                                            <span class="bg-<?= $user['role_name'] === 'super_admin' ? 'danger' : ($user['role_name'] === 'admin' ? 'warning' : 'info') ?>-focus text-<?= $user['role_name'] === 'super_admin' ? 'danger' : ($user['role_name'] === 'admin' ? 'warning' : 'info') ?>-600 border border-<?= $user['role_name'] === 'super_admin' ? 'danger' : ($user['role_name'] === 'admin' ? 'warning' : 'info') ?>-main px-24 py-4 radius-4 fw-medium text-sm">
                                                <?= htmlspecialchars($user['role_name']) ?>
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

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-24">
                        <span>Mostrando 1 a <?= count($users) ?> de <?= count($users) ?> registros</span>
                        <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
                                                         <li class="page-item">
                                 <a class="page-link bg-neutral-200 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" href="javascript:void(0)">
                                     <i class="ri-arrow-left-s-line"></i>
                                 </a>
                             </li>
                            <li class="page-item">
                                <a class="page-link text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md bg-primary-600 text-white" href="javascript:void(0)">1</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../partials/footer.php' ?>
    </main>

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
                            <p class="text-secondary-light" id="view_name"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Email</label>
                            <p class="text-secondary-light" id="view_email"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Telefone</label>
                            <p class="text-secondary-light" id="view_phone"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nível de Acesso</label>
                            <p class="text-secondary-light" id="view_role"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Status</label>
                            <p class="text-secondary-light" id="view_status"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Data de Cadastro</label>
                            <p class="text-secondary-light" id="view_created_at"></p>
                        </div>
                        <div class="col-md-12 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Biografia</label>
                            <p class="text-secondary-light" id="view_bio"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
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
                <form method="POST" action="/admin/usuarios.php">
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
                                    <option value="1">super_admin - Super Administrador - Acesso total ao sistema</option>
                                    <option value="2">admin - Administrador - Gerenciamento de conteúdo</option>
                                    <option value="3">user - Usuário - Acesso básico</option>
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
                <form method="POST" action="/admin/usuarios.php">
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
        
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }

    // Excluir usuário
    function deleteUser(userId, userName) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('delete_user_name').textContent = userName;
        
        new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
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
