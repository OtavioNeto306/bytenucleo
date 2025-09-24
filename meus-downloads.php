<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';

$auth = new Auth($pdo);
$product = new Product($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Obter parâmetros de paginação e filtros
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;

$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = trim($_GET['search']);
}
if (!empty($_GET['category_id'])) {
    $filters['category_id'] = $_GET['category_id'];
}
if (!empty($_GET['product_type'])) {
    $filters['product_type'] = $_GET['product_type'];
}
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Obter downloads do usuário
$result = $product->getUserDownloadsPaginated($_SESSION['user_id'], $page, $limit, $filters);
$downloads = $result['downloads'];
$total_records = $result['total_records'];
$total_pages = $result['total_pages'];

// Obter categorias para filtro
$categories = $product->getAllCategories();

// Removido DataTables para evitar paginação duplicada
?>

<?php include './partials/layouts/layoutTop.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Meus Downloads</h1>
                        <p class="text-lg text-secondary-light mb-0">
                            Visualize e gerencie seu histórico de downloads de produtos
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div class="dashboard-main-body">

            <!-- Filtros -->
            <div class="card mb-24">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                                   placeholder="Buscar produtos...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" name="category_id">
                                <option value="">Todas as Categorias</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= (isset($_GET['category_id']) && $_GET['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="product_type">
                                <option value="">Todos os Tipos</option>
                                <option value="free" <?= (isset($_GET['product_type']) && $_GET['product_type'] == 'free') ? 'selected' : '' ?>>Gratuito</option>
                                <option value="premium" <?= (isset($_GET['product_type']) && $_GET['product_type'] == 'premium') ? 'selected' : '' ?>>Premium</option>
                                <option value="exclusive" <?= (isset($_GET['product_type']) && $_GET['product_type'] == 'exclusive') ? 'selected' : '' ?>>Exclusivo</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Final</label>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ri-search-line"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabela de Downloads -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Histórico de Downloads</h5>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-sm text-secondary-light d-flex align-items-center">Total: <?= number_format($total_records) ?> downloads</span>
                            <select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href=this.value">
                                <option value="?<?= http_build_query(array_merge($_GET, ['limit' => 10])) ?>" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                <option value="?<?= http_build_query(array_merge($_GET, ['limit' => 25])) ?>" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                <option value="?<?= http_build_query(array_merge($_GET, ['limit' => 50])) ?>" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($downloads)): ?>
                        <div class="text-center py-5">
                            <i class="ri-download-line" style="font-size: 4rem; color: #6c757d;"></i>
                            <h5 class="mt-3">Nenhum download encontrado</h5>
                            <p class="mt-3">Você ainda não fez nenhum download ou os filtros aplicados não retornaram resultados.</p>
                            <a href="produtos.php" class="btn btn-primary">Ver Produtos</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive scroll-sm">
                            <table class="table bordered-table sm-table mb-0" id="downloadsTable">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Produto</th>
                                    <th scope="col">Categoria</th>
                                    <th scope="col">Tipo</th>
                                    <th scope="col">Data do Download</th>
                                    <th scope="col">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($downloads as $index => $download): ?>
                                    <tr>
                                        <td>
                                            <span class="text-md fw-medium text-secondary-light">
                                                <?= ($page - 1) * $limit + $index + 1 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($download['image_path']) && file_exists($download['image_path'])): ?>
                                                    <img src="<?= htmlspecialchars($download['image_path']) ?>" 
                                                         alt="<?= htmlspecialchars($download['product_name']) ?>" 
                                                         class="flex-shrink-0 me-12 radius-8" 
                                                         style="width: 40px; height: 40px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="flex-shrink-0 me-12 radius-8 bg-neutral-200 d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="ri-image-line text-neutral-400"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <h6 class="text-md mb-0 fw-medium"><?= htmlspecialchars($download['product_name']) ?></h6>
                                                    <p class="text-sm text-secondary-light mb-0">ID: <?= $download['product_id'] ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-md mb-0 fw-normal text-secondary-light">
                                                <?= htmlspecialchars($download['category_name'] ?? 'Sem categoria') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $type_colors = [
                                                'free' => 'success',
                                                'premium' => 'warning',
                                                'exclusive' => 'danger',
                                                'individual' => 'primary'
                                            ];
                                            $type_labels = [
                                                'free' => 'Gratuito',
                                                'premium' => 'Premium',
                                                'exclusive' => 'Exclusivo',
                                                'individual' => 'Venda Individual'
                                            ];
                                            
                                            // Determinar o tipo baseado no produto
                                            $productType = $download['product_type'] ?? '';
                                            
                                            // Se não tem tipo definido, determinar baseado nas características do produto
                                            if (empty($productType)) {
                                                // Buscar dados do produto para verificar se é gratuito, pago ou venda individual
                                                $stmt = $pdo->prepare("SELECT individual_sale, individual_price, plan_id FROM products WHERE id = ?");
                                                $stmt->execute([$download['product_id']]);
                                                $productData = $stmt->fetch(PDO::FETCH_ASSOC);
                                                
                                                if ($productData) {
                                                    // Verificar se tem plano associado
                                                    if ($productData['plan_id']) {
                                                        // Produto atrelado a plano
                                                        if (!$productData['individual_sale'] || $productData['individual_price'] == 0) {
                                                            $productType = 'free';
                                                        } else {
                                                            $productType = 'premium';
                                                        }
                                                    } else {
                                                        // Produto sem plano = venda individual
                                                        if (!$productData['individual_sale'] || $productData['individual_price'] == 0) {
                                                            $productType = 'free';
                                                        } else {
                                                            $productType = 'individual'; // Venda individual
                                                        }
                                                    }
                                                } else {
                                                    $productType = 'individual'; // Default para venda individual
                                                }
                                            }
                                            
                                            $color = $type_colors[$productType] ?? 'info';
                                            $label = $type_labels[$productType] ?? 'Premium';
                                            ?>
                                            <span class="bg-<?= $color ?>-focus text-<?= $color ?>-600 border border-<?= $color ?>-main px-24 py-4 radius-4 fw-medium text-sm">
                                                <?= $label ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-md mb-0 fw-normal text-secondary-light">
                                                <?= date('d/m/Y H:i', strtotime($download['downloaded_at'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-10">
                                                                                                 <a href="produto.php?slug=<?= htmlspecialchars($download['slug']) ?>" 
                                                    class="w-32-px h-32-px bg-primary-light text-primary-600 rounded-circle d-inline-flex align-items-center justify-content-center"
                                                    title="Ver Produto">
                                                     <i class="ri-eye-line"></i>
                                                 </a>
                                                 <a href="download.php?id=<?= $download['product_id'] ?>" 
                                                    class="w-32-px h-32-px bg-success-focus text-success-main rounded-circle d-inline-flex align-items-center justify-content-center"
                                                    title="Baixar Novamente">
                                                     <i class="ri-download-line"></i>
                                                 </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div class="text-sm text-muted">
                                    Mostrando <?= (($page - 1) * $limit) + 1 ?> a <?= min($page * $limit, $total_records) ?> de <?= number_format($total_records) ?> downloads
                                </div>
                                <nav aria-label="Paginação">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                                                             <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                 <i class="ri-arrow-left-line"></i>
                                             </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                                                             <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                 <i class="ri-arrow-right-line"></i>
                                             </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<?php include './partials/layouts/layoutBottom.php' ?>
