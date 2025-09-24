<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Product.php';

$auth = new Auth($pdo);
$product = new Product($pdo);

// Verificar se usuário está logado e tem permissão
if (!$auth->isLoggedIn() || !$auth->hasPermission('manage_products')) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Processar ações
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_product':
            $name = trim($_POST['name'] ?? '');
            $short_description = trim($_POST['short_description'] ?? '');
            $full_description = trim($_POST['full_description'] ?? '');
            $category_id = $_POST['category_id'] ?? null;
            $price = floatval($_POST['price'] ?? 0);
            $product_type = $_POST['product_type'] ?? 'free';
            $status = $_POST['status'] ?? 'draft';
            $featured = isset($_POST['featured']) ? 1 : 0;
            $max_downloads = intval($_POST['max_downloads_per_user'] ?? -1);
            
            if (empty($name) || empty($short_description)) {
                $error = 'Nome e descrição curta são obrigatórios';
            } else {
                $data = [
                    'name' => $name,
                    'short_description' => $short_description,
                    'full_description' => $full_description,
                    'category_id' => $category_id,
                    'price' => $price,
                    'product_type' => $product_type,
                    'status' => $status,
                    'featured' => $featured,
                    'max_downloads_per_user' => $max_downloads
                ];
                
                if ($product->createProduct($data)) {
                    $message = 'Produto criado com sucesso!';
                } else {
                    $error = 'Erro ao criar produto';
                }
            }
            break;
            
        case 'update_product':
            $product_id = $_POST['product_id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $short_description = trim($_POST['short_description'] ?? '');
            $full_description = trim($_POST['full_description'] ?? '');
            $category_id = $_POST['category_id'] ?? null;
            $price = floatval($_POST['price'] ?? 0);
            $product_type = $_POST['product_type'] ?? 'free';
            $status = $_POST['status'] ?? 'draft';
            $featured = isset($_POST['featured']) ? 1 : 0;
            $max_downloads = intval($_POST['max_downloads_per_user'] ?? -1);
            
            if (empty($product_id) || empty($name) || empty($short_description)) {
                $error = 'Dados obrigatórios não fornecidos';
            } else {
                $data = [
                    'name' => $name,
                    'short_description' => $short_description,
                    'full_description' => $full_description,
                    'category_id' => $category_id,
                    'price' => $price,
                    'product_type' => $product_type,
                    'status' => $status,
                    'featured' => $featured,
                    'max_downloads_per_user' => $max_downloads
                ];
                
                if ($product->updateProduct($product_id, $data)) {
                    $message = 'Produto atualizado com sucesso!';
                } else {
                    $error = 'Erro ao atualizar produto';
                }
            }
            break;
            
        case 'delete_product':
            $product_id = $_POST['product_id'] ?? '';
            
            if (empty($product_id)) {
                $error = 'ID do produto não fornecido';
            } else {
                if ($product->deleteProduct($product_id)) {
                    $message = 'Produto excluído com sucesso!';
                } else {
                    $error = 'Erro ao excluir produto';
                }
            }
            break;
    }
}

// Obter produtos com paginação e filtros
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;

$filters = [
    'search' => $_GET['search'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'product_type' => $_GET['product_type'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$result = $product->getAllProducts($page, $limit, $filters);
$products = $result['products'];
$total_pages = $result['total_pages'];
$total_records = $result['total_records'];

// Obter categorias
$categories = $product->getAllCategories();
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
                        <h1 class="display-4 fw-bold mb-24">Gerenciar Produtos</h1>
                        <p class="text-lg text-secondary-light mb-0">
                            Administre produtos, categorias e downloads do sistema
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

        <!-- Lista de Produtos -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24 d-flex align-items-center flex-wrap gap-3 justify-content-between">
                                <div class="d-flex align-items-center flex-wrap gap-3">
                                    <span class="text-md fw-medium text-secondary-light mb-0">Mostrar</span>
                                    <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" id="limitSelect">
                                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                    </select>
                                    
                                    <!-- Busca -->
                                    <form class="navbar-search" method="GET">
                                        <input type="text" class="bg-base h-40-px w-auto" name="search" placeholder="Buscar produtos..." value="<?= htmlspecialchars($filters['search']) ?>">
                                        <i class="ri-search-line"></i>
                                    </form>
                                    
                                    <!-- Filtro de Categoria -->
                                    <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" name="category_id" onchange="this.form.submit()">
                                        <option value="">Todas as Categorias</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= $filters['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <!-- Filtro de Tipo -->
                                    <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" name="product_type" onchange="this.form.submit()">
                                        <option value="">Todos os Tipos</option>
                                        <option value="free" <?= $filters['product_type'] === 'free' ? 'selected' : '' ?>>Gratuito</option>
                                        <option value="premium" <?= $filters['product_type'] === 'premium' ? 'selected' : '' ?>>Premium</option>
                                        <option value="exclusive" <?= $filters['product_type'] === 'exclusive' ? 'selected' : '' ?>>Exclusivo</option>
                                    </select>
                                    
                                    <!-- Filtro de Status -->
                                    <select class="form-select form-select-sm w-auto ps-12 py-6 radius-12 h-40-px" name="status" onchange="this.form.submit()">
                                        <option value="">Todos os Status</option>
                                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                                        <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                                        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                                    </select>
                                </div>
                                
                                <a href="adicionar-produto.php" class="btn btn-primary text-sm btn-sm px-12 py-12 radius-8 d-flex align-items-center gap-2 text-decoration-none">
                                    <i class="ri-add-line"></i>
                                    Adicionar Produto
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
                                                <th scope="col">Imagem</th>
                                                <th scope="col">Nome</th>
                                                <th scope="col">Categoria</th>
                                                <th scope="col">Tipo</th>
                                                <th scope="col">Preço</th>
                                                <th scope="col">Downloads</th>
                                                <th scope="col" class="text-center">Status</th>
                                                <th scope="col" class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($products)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-40">
                                                    <i class="ri-shopping-bag-line text-muted" style="font-size: 3rem;"></i>
                                                    <h5 class="mt-16 mb-8">Nenhum produto encontrado</h5>
                                                    <p class="text-secondary-light">Não há produtos que correspondam aos filtros aplicados.</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($products as $index => $product_item): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-10">
                                                            <div class="form-check style-check d-flex align-items-center">
                                                                <input class="form-check-input radius-4 border border-neutral-400" type="checkbox" name="checkbox">
                                                            </div>
                                                            <?= $product_item['id'] ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($product_item['image_path']) && file_exists('../' . $product_item['image_path'])): ?>
                                                            <div style="background-image: url('../<?= htmlspecialchars($product_item['image_path']) ?>'); width: 50px; height: 50px; border-radius: 8px; background-size: cover; background-repeat: no-repeat; background-position: center;" class="flex-shrink-0"></div>
                                                        <?php else: ?>
                                                            <div class="w-50-px h-50-px rounded-8 flex-shrink-0 bg-neutral-200 d-flex align-items-center justify-content-center">
                                                                <i class="ri-image-line text-neutral-400" style="font-size: 1.5rem;"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-4 fw-semibold"><?= htmlspecialchars($product_item['name']) ?></h6>
                                                            <p class="text-sm text-secondary-light mb-0"><?= htmlspecialchars(substr($product_item['short_description'], 0, 50)) ?>...</p>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="text-md mb-0 fw-normal text-secondary-light">
                                                            <?= htmlspecialchars($product_item['category_name'] ?? 'Sem categoria') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        // Mostrar planos associados ao produto
                                                        $plan_names = $product_item['plan_names'] ?? '';
                                                        if (!empty($plan_names)) {
                                                            $plans_array = explode(', ', $plan_names);
                                                            foreach ($plans_array as $plan_name) {
                                                                $plan_name = trim($plan_name);
                                                                $color = 'primary';
                                                                if (stripos($plan_name, 'básico') !== false) {
                                                                    $color = 'success';
                                                                } elseif (stripos($plan_name, 'premium') !== false) {
                                                                    $color = 'warning';
                                                                } elseif (stripos($plan_name, 'exclusivo') !== false) {
                                                                    $color = 'danger';
                                                                }
                                                                ?>
                                                                <span class="bg-<?= $color ?>-focus text-<?= $color ?>-600 border border-<?= $color ?>-main px-12 py-2 radius-4 fw-medium text-xs me-2 mb-1 d-inline-block">
                                                                    <?= htmlspecialchars($plan_name) ?>
                                                                </span>
                                                                <?php
                                                            }
                                                        } else {
                                                            ?>
                                                            <span class="bg-neutral-200 text-neutral-600 border border-neutral-300 px-12 py-2 radius-4 fw-medium text-xs">
                                                                Sem planos
                                                            </span>
                                                            <?php
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($product_item['individual_sale'] && $product_item['individual_price'] > 0): ?>
                                                            <span class="text-primary-600 fw-semibold">R$ <?= number_format($product_item['individual_price'], 2, ',', '.') ?></span>
                                                        <?php else: ?>
                                                            <span class="text-success-600 fw-semibold">Grátis</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="text-md mb-0 fw-normal text-secondary-light">
                                                            <?= number_format($product_item['downloads_count']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php
                                                        $status_colors = [
                                                            'active' => 'success',
                                                            'inactive' => 'neutral',
                                                            'draft' => 'warning'
                                                        ];
                                                        $status_labels = [
                                                            'active' => 'Ativo',
                                                            'inactive' => 'Inativo',
                                                            'draft' => 'Rascunho'
                                                        ];
                                                        $color = $status_colors[$product_item['status']] ?? 'info';
                                                        $label = $status_labels[$product_item['status']] ?? 'Desconhecido';
                                                        ?>
                                                        <span class="bg-<?= $color ?>-focus text-<?= $color ?>-600 border border-<?= $color ?>-main px-24 py-4 radius-4 fw-medium text-sm">
                                                            <?= $label ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex align-items-center gap-10 justify-content-center">
                                                            <button type="button" class="bg-info-focus bg-hover-info-200 text-info-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"
                                                                    onclick="viewProduct(<?= htmlspecialchars(json_encode($product_item)) ?>)">
                                                                <i class="ri-eye-line"></i>
                                                            </button>
                                                            <a href="/admin/editar-produto?id=<?= $product_item['id'] ?>" 
                                                               class="bg-success-focus text-success-600 bg-hover-success-200 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle text-decoration-none">
                                                                <i class="ri-edit-line"></i>
                                                            </a>
                                                            <button type="button" class="remove-item-btn bg-danger-focus bg-hover-danger-200 text-danger-600 fw-medium w-40-px h-40-px d-flex justify-content-center align-items-center rounded-circle"
                                                                    onclick="deleteProduct(<?= $product_item['id'] ?>, '<?= htmlspecialchars($product_item['name']) ?>')">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
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
                                    <span>Mostrando <?= ($page - 1) * $limit + 1 ?> a <?= min($page * $limit, $total_records) ?> de <?= $total_records ?> registros</span>
                                    <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
                                        <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link bg-neutral-200 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" 
                                               href="?page=<?= $page - 1 ?>&search=<?= urlencode($filters['search']) ?>&category_id=<?= urlencode($filters['category_id']) ?>&product_type=<?= urlencode($filters['product_type']) ?>&status=<?= urlencode($filters['status']) ?>">
                                                <i class="ri-arrow-left-s-line"></i>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item">
                                            <a class="page-link <?= $i == $page ? 'bg-primary-600 text-white' : 'bg-neutral-200 text-secondary-light' ?> fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px" 
                                               href="?page=<?= $i ?>&search=<?= urlencode($filters['search']) ?>&category_id=<?= urlencode($filters['category_id']) ?>&product_type=<?= urlencode($filters['product_type']) ?>&status=<?= urlencode($filters['status']) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link bg-neutral-200 text-secondary-light fw-semibold radius-8 border-0 d-flex align-items-center justify-content-center h-32-px w-32-px text-md" 
                                               href="?page=<?= $page + 1 ?>&search=<?= urlencode($filters['search']) ?>&category_id=<?= urlencode($filters['category_id']) ?>&product_type=<?= urlencode($filters['product_type']) ?>&status=<?= urlencode($filters['status']) ?>">
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





    <!-- Modal Visualizar Produto -->
    <div class="modal fade" id="viewProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-24 text-center">
                            <div id="view_product_image" style="width: 200px; height: 200px; margin: 0 auto; border-radius: 12px; background-size: cover; background-repeat: no-repeat; background-position: center; border: 1px solid var(--primary-600);"></div>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Nome do Produto</label>
                            <p class="text-secondary-light" id="view_name"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Categoria</label>
                            <p class="text-secondary-light" id="view_category"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Tipo</label>
                            <p class="text-secondary-light" id="view_type"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Preço</label>
                            <p class="text-secondary-light" id="view_price"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Status</label>
                            <p class="text-secondary-light" id="view_status"></p>
                        </div>
                        <div class="col-md-6 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Downloads</label>
                            <p class="text-secondary-light" id="view_downloads"></p>
                        </div>
                        <div class="col-md-12 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Descrição Curta</label>
                            <p class="text-secondary-light" id="view_short_description"></p>
                        </div>
                        <div class="col-md-12 mb-16">
                            <label class="form-label fw-semibold text-primary-light text-sm mb-8">Descrição Completa</label>
                            <p class="text-secondary-light" id="view_full_description"></p>
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
    <div class="modal fade" id="deleteProductModal" tabindex="-1">
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
                    <p>Tem certeza que deseja excluir o produto <strong id="delete_product_name"></strong>?</p>
                    <p class="text-danger">Esta ação não pode ser desfeita!</p>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" id="delete_product_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Excluir Produto</button>
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

    // Visualizar produto
    function viewProduct(product) {
        document.getElementById('view_name').textContent = product.name;
        document.getElementById('view_category').textContent = product.category_name || 'Sem categoria';
        document.getElementById('view_type').textContent = product.product_type === 'free' ? 'Gratuito' : (product.product_type === 'premium' ? 'Premium' : 'Exclusivo');
        document.getElementById('view_price').textContent = product.product_type === 'free' ? 'Grátis' : `R$ ${parseFloat(product.price).toFixed(2).replace('.', ',')}`;
        document.getElementById('view_status').textContent = product.status === 'active' ? 'Ativo' : (product.status === 'inactive' ? 'Inativo' : 'Rascunho');
        document.getElementById('view_downloads').textContent = parseInt(product.downloads_count).toLocaleString('pt-BR');
        document.getElementById('view_short_description').textContent = product.short_description;
        document.getElementById('view_full_description').textContent = product.full_description || 'Nenhuma descrição completa disponível.';
        
        const imageDiv = document.getElementById('view_product_image');
        if (product.image_path && product.image_path.trim() !== '') {
            // Verificar se a imagem existe antes de tentar carregá-la
            const img = new Image();
            img.onload = function() {
                imageDiv.style.backgroundImage = `url('../${product.image_path}')`;
            };
            img.onerror = function() {
                imageDiv.style.backgroundImage = 'none';
                imageDiv.style.backgroundColor = '#f8f9fa';
                imageDiv.innerHTML = '<i class="ri-image-line" style="font-size: 4rem; color: #667eea; line-height: 200px;"></i>';
            };
            img.src = '../' + product.image_path;
        } else {
            imageDiv.style.backgroundImage = 'none';
            imageDiv.style.backgroundColor = '#f8f9fa';
            imageDiv.innerHTML = '<i class="ri-image-line" style="font-size: 4rem; color: #667eea; line-height: 200px;"></i>';
        }
        
        new bootstrap.Modal(document.getElementById('viewProductModal')).show();
    }



    // Excluir produto
    function deleteProduct(productId, productName) {
        document.getElementById('delete_product_id').value = productId;
        document.getElementById('delete_product_name').textContent = productName;
        
        new bootstrap.Modal(document.getElementById('deleteProductModal')).show();
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
