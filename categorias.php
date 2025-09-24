<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';

$auth = new Auth($pdo);
$product = new Product($pdo);

// Obter parâmetros de paginação
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 12;

// Obter categorias com contagem de produtos
$result = $product->getCategoriesWithProductCount($page, $limit);
$categories = $result['categories'];
$total_records = $result['total_records'];
$total_pages = $result['total_pages'];

$script = '<script>
    // Script adicional se necessário
</script>';
?>

<?php include './partials/layouts/layoutTop.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Categorias</h1>
                        <p class="text-lg text-secondary-light mb-0">
                            Explore todas as categorias de produtos disponíveis em nossa plataforma
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div class="dashboard-main-body">

            <!-- Header com estatísticas -->
            <div class="row mb-24">
                <div class="col-md-4">
                    <div class="card radius-12">
                        <div class="card-body p-24">
                            <div class="d-flex align-items-center gap-3">
                                <div class="w-64-px h-64-px d-inline-flex align-items-center justify-content-center bg-gradient-primary text-primary-600 radius-12">
                                    <i class="ri-folder-line h5 mb-0"></i>
                                </div>
                                <div>
                                    <h6 class="mb-4">Total de Categorias</h6>
                                    <h4 class="mb-0 fw-bold"><?= number_format($total_records) ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card radius-12">
                        <div class="card-body p-24">
                            <div class="d-flex align-items-center gap-3">
                                <div class="w-64-px h-64-px d-inline-flex align-items-center justify-content-center bg-gradient-success text-success-600 radius-12">
                                    <i class="ri-shopping-bag-line h5 mb-0"></i>
                                </div>
                                <div>
                                    <h6 class="mb-4">Produtos Disponíveis</h6>
                                    <h4 class="mb-0 fw-bold">
                                        <?php
                                        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
                                        echo number_format($stmt->fetchColumn());
                                        ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card radius-12">
                        <div class="card-body p-24">
                            <div class="d-flex align-items-center gap-3">
                                <div class="w-64-px h-64-px d-inline-flex align-items-center justify-content-center bg-gradient-warning text-warning-600 radius-12">
                                    <i class="ri-download-line h5 mb-0"></i>
                                </div>
                                <div>
                                    <h6 class="mb-4">Downloads Totais</h6>
                                    <h4 class="mb-0 fw-bold">
                                        <?php
                                        $stmt = $pdo->query("SELECT COUNT(*) FROM product_downloads");
                                        echo number_format($stmt->fetchColumn());
                                        ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controles de paginação -->
            <div class="d-flex justify-content-between align-items-center mb-24">
                <div class="text-sm text-muted">
                    Mostrando <?= (($page - 1) * $limit) + 1 ?> a <?= min($page * $limit, $total_records) ?> de <?= number_format($total_records) ?> categorias
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-sm text-muted">Itens por página:</span>
                    <select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href=this.value">
                        <option value="?<?= http_build_query(array_merge($_GET, ['limit' => 12])) ?>" <?= $limit == 12 ? 'selected' : '' ?>>12</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['limit' => 24])) ?>" <?= $limit == 24 ? 'selected' : '' ?>>24</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['limit' => 48])) ?>" <?= $limit == 48 ? 'selected' : '' ?>>48</option>
                    </select>
                </div>
            </div>

            <!-- Grid de Categorias -->
            <?php if (empty($categories)): ?>
                <div class="text-center py-5">
                    <i class="ri-folder-line" style="font-size: 4rem; color: #6c757d;"></i>
                    <h5 class="mt-3">Nenhuma categoria encontrada</h5>
                    <p class="text-muted">Não há categorias cadastradas no sistema.</p>
                </div>
            <?php else: ?>
                <div class="row gy-4">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-xxl-3 col-lg-4 col-md-6">
                            <div class="card h-100 radius-12">
                                <div class="card-body p-24 text-center">
                                    <div class="w-64-px h-64-px d-inline-flex align-items-center justify-content-center bg-gradient-primary text-primary-600 mb-16 radius-12 mx-auto">
                                        <i class="ri-folder-line h5 mb-0"></i>
                                    </div>
                                    <h6 class="mb-8"><?= htmlspecialchars($category['name']) ?></h6>
                                    <p class="card-text mb-8 text-secondary-light">
                                        <?= htmlspecialchars($category['description'] ?? 'Descrição não disponível') ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mb-16">
                                        <span class="text-sm text-secondary-light d-flex align-items-center">
                                            <i class="ri-shopping-bag-line me-1"></i>
                                            <?= number_format($category['product_count']) ?> produtos
                                        </span>
                                       
                                        <span class="text-sm text-secondary-light d-flex align-items-center">
                                            <i class="ri-calendar-line me-1"></i>
                                            <?= date('d/m/Y', strtotime($category['created_at'])) ?>
                                        </span>
                                    </div>
                                                                         <a href="produtos.php?category=<?= $category['id'] ?>" 
                                        class="btn btn-primary-600 px-12 py-10 d-inline-flex align-items-center gap-2 w-100 justify-content-center">
                                         Ver Produtos <i class="ri-arrow-right-line"></i>
                                     </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
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

<?php include './partials/layouts/layoutBottom.php' ?>
