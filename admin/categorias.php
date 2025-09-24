<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Product.php';

$auth = new Auth($pdo);
$product = new Product($pdo);

// Verificar se usuário está logado e tem permissão
if (!$auth->isLoggedIn() || !$auth->hasPermission('manage_categories')) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Processar ações
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_category':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
            
            if (empty($name)) {
                $error = 'Nome da categoria é obrigatório';
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                $slug = trim($slug, '-');
                
                // Verificar se slug já existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE slug = ?");
                $stmt->execute([$slug]);
                if ($stmt->fetchColumn() > 0) {
                    $slug .= '-' . time();
                }
                
                $stmt = $pdo->prepare("INSERT INTO product_categories (name, slug, description, parent_id) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$name, $slug, $description, $parent_id])) {
                    $message = 'Categoria criada com sucesso!';
                } else {
                    $error = 'Erro ao criar categoria';
                }
            }
            break;
            
        case 'update_category':
            $category_id = $_POST['category_id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
            
            if (empty($category_id) || empty($name)) {
                $error = 'ID e nome da categoria são obrigatórios';
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                $slug = trim($slug, '-');
                
                // Verificar se slug já existe (exceto para a categoria atual)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $category_id]);
                if ($stmt->fetchColumn() > 0) {
                    $slug .= '-' . time();
                }
                
                $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, slug = ?, description = ?, parent_id = ? WHERE id = ?");
                if ($stmt->execute([$name, $slug, $description, $parent_id, $category_id])) {
                    $message = 'Categoria atualizada com sucesso!';
                } else {
                    $error = 'Erro ao atualizar categoria';
                }
            }
            break;
            
        case 'delete_category':
            $category_id = $_POST['category_id'] ?? '';
            
            if (empty($category_id)) {
                $error = 'ID da categoria não fornecido';
            } else {
                // Verificar se há produtos usando esta categoria
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                $stmt->execute([$category_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Não é possível excluir uma categoria que possui produtos associados';
                } else {
                    // Verificar se há subcategorias
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE parent_id = ?");
                    $stmt->execute([$category_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Não é possível excluir uma categoria que possui subcategorias';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM product_categories WHERE id = ?");
                        if ($stmt->execute([$category_id])) {
                            $message = 'Categoria excluída com sucesso!';
                        } else {
                            $error = 'Erro ao excluir categoria';
                        }
                    }
                }
            }
            break;
    }
}

// Obter categorias
$categories = $product->getAllCategories();

// Organizar categorias em hierarquia
function buildCategoryTree($categories, $parent_id = null) {
    $tree = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parent_id) {
            $category['children'] = buildCategoryTree($categories, $category['id']);
            $tree[] = $category;
        }
    }
    return $tree;
}

$categoryTree = buildCategoryTree($categories);

// Função para renderizar categoria recursivamente
function renderCategory($category, $level = 0) {
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
    $hasChildren = !empty($category['children']);
    
    echo '<tr>';
    echo '<td>';
    echo '<div class="d-flex align-items-center gap-10">';
    echo '<div class="form-check style-check d-flex align-items-center">';
    echo '<input class="form-check-input radius-4 border border-neutral-400" type="checkbox" name="checkbox">';
    echo '</div>';
    echo $indent . $category['id'];
    echo '</div>';
    echo '</td>';
    echo '<td>';
    echo '<div class="flex-grow-1">';
    echo '<h6 class="mb-4 fw-semibold">' . htmlspecialchars($category['name']) . '</h6>';
    if ($hasChildren) {
        echo '<span class="badge bg-info-focus text-info-600 text-xs">' . count($category['children']) . ' subcategorias</span>';
    }
    echo '</div>';
    echo '</td>';
    echo '<td>';
    echo '<span class="text-md mb-0 fw-normal text-secondary-light">';
    echo htmlspecialchars($category['slug']);
    echo '</span>';
    echo '</td>';
    echo '<td>';
    echo '<span class="text-md mb-0 fw-normal text-secondary-light">';
    echo htmlspecialchars($category['description'] ?: 'Sem descrição');
    echo '</span>';
    echo '</td>';
    echo '<td>';
    echo '<span class="text-md mb-0 fw-normal text-secondary-light">';
    echo date('d/m/Y H:i', strtotime($category['created_at']));
    echo '</span>';
    echo '</td>';
    echo '<td class="text-center">';
    echo '<div class="d-flex align-items-center gap-10 justify-content-center">';
    echo '<button type="button" class="bg-info-focus bg-hover-info-200 text-info-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"';
    echo ' onclick="viewCategory(' . htmlspecialchars(json_encode($category)) . ')">';
    echo '<i class="ri-eye-line"></i>';
    echo '</button>';
    echo '<button type="button" class="bg-success-focus text-success-600 bg-hover-success-200 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"';
    echo ' onclick="editCategory(' . htmlspecialchars(json_encode($category)) . ')">';
    echo '<i class="ri-edit-line"></i>';
    echo '</button>';
    echo '<button type="button" class="remove-item-btn bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"';
    echo ' onclick="deleteCategory(' . $category['id'] . ', \'' . htmlspecialchars($category['name']) . '\')">';
    echo '<i class="ri-delete-bin-line"></i>';
    echo '</button>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
    
    // Renderizar subcategorias
    if ($hasChildren) {
        foreach ($category['children'] as $child) {
            renderCategory($child, $level + 1);
        }
    }
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
                        <h1 class="display-4 fw-bold mb-24">Gerenciar Categorias</h1>
                        <p class="text-lg text-secondary-light mb-0">
                            Organize seus produtos em categorias e subcategorias
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

        <!-- Lista de Categorias -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
                                <div class="d-flex align-items-center flex-wrap gap-3">
                                    <span class="text-md fw-medium text-secondary-light mb-0">Total de Categorias: <?= count($categories) ?></span>
                                </div>
                                
                                <button type="button" class="btn btn-primary text-sm btn-sm px-12 py-12 radius-8 d-flex align-items-center gap-2" 
                                        data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                                    <i class="ri-add-line"></i>
                                    Adicionar Categoria
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
                                                <th scope="col">Nome</th>
                                                <th scope="col">Slug</th>
                                                <th scope="col">Descrição</th>
                                                <th scope="col">Criado em</th>
                                                <th scope="col" class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($categoryTree)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-40">
                                                    <i class="ri-folder-line text-muted" style="font-size: 3rem;"></i>
                                                    <h5 class="mt-16 mb-8">Nenhuma categoria encontrada</h5>
                                                    <p class="text-secondary-light">Crie sua primeira categoria para começar a organizar os produtos.</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($categoryTree as $category): ?>
                                                    <?php renderCategory($category); ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php include '../partials/footer.php' ?>
    </main>

    <!-- Modal Criar Categoria -->
    <div class="modal fade" id="createCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Nova Categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_category">
                    <div class="modal-body">
                        <div class="mb-24">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nome da Categoria <span class="text-danger-600">*</span></label>
                            <input type="text" name="name" class="form-control radius-8" required>
                        </div>
                        <div class="mb-24">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Categoria Pai</label>
                            <select name="parent_id" class="form-select radius-8">
                                <option value="">Categoria Principal</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-24">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Descrição</label>
                            <textarea name="description" class="form-control radius-8" rows="3" placeholder="Descrição da categoria..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar Categoria</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Categoria -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_category">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="modal-body">
                        <div class="mb-24">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nome da Categoria <span class="text-danger-600">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control radius-8" required>
                        </div>
                        <div class="mb-24">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Categoria Pai</label>
                            <select name="parent_id" id="edit_parent_id" class="form-select radius-8">
                                <option value="">Categoria Principal</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-24">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Descrição</label>
                            <textarea name="description" id="edit_description" class="form-control radius-8" rows="3"></textarea>
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

    <!-- Modal Visualizar Categoria -->
    <div class="modal fade" id="viewCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes da Categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nome da Categoria</label>
                            <p class="text-secondary-light" id="view_name"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Slug</label>
                            <p class="text-secondary-light" id="view_slug"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Categoria Pai</label>
                            <p class="text-secondary-light" id="view_parent"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Criado em</label>
                            <p class="text-secondary-light" id="view_created_at"></p>
                        </div>
                        <div class="col-md-12 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Descrição</label>
                            <p class="text-secondary-light" id="view_description"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Exclusão -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
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
                    <p>Tem certeza que deseja excluir a categoria <strong id="delete_category_name"></strong>?</p>
                    <p class="text-danger">Esta ação não pode ser desfeita!</p>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Excluir Categoria</button>
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

    // Visualizar categoria
    function viewCategory(category) {
        document.getElementById('view_name').textContent = category.name;
        document.getElementById('view_slug').textContent = category.slug;
        document.getElementById('view_parent').textContent = category.parent_id ? 'Subcategoria' : 'Categoria Principal';
        document.getElementById('view_created_at').textContent = new Date(category.created_at).toLocaleString('pt-BR');
        document.getElementById('view_description').textContent = category.description || 'Sem descrição';
        
        new bootstrap.Modal(document.getElementById('viewCategoryModal')).show();
    }

    // Editar categoria
    function editCategory(category) {
        document.getElementById('edit_category_id').value = category.id;
        document.getElementById('edit_name').value = category.name;
        document.getElementById('edit_parent_id').value = category.parent_id || '';
        document.getElementById('edit_description').value = category.description || '';
        
        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    }

    // Excluir categoria
    function deleteCategory(categoryId, categoryName) {
        document.getElementById('delete_category_id').value = categoryId;
        document.getElementById('delete_category_name').textContent = categoryName;
        
        new bootstrap.Modal(document.getElementById('deleteCategoryModal')).show();
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
