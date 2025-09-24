<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';

$auth = new Auth($pdo);
$product = new Product($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login');
    exit;
}

// Obter parâmetros de paginação e filtros
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 12;

$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = trim($_GET['search']);
}
if (!empty($_GET['category_id'])) {
    $filters['category_id'] = $_GET['category_id'];
}

// Obter favoritos do usuário
$result = $product->getUserFavoritesPaginated($_SESSION['user_id'], $page, $limit, $filters);
$favorites = $result['favorites'];
$total_records = $result['total_records'];
$total_pages = $result['total_pages'];

// Obter categorias para filtro
$categories = $product->getAllCategories();
?>

<?php include './partials/layouts/layoutTop.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="pe-lg-4">
                            <h1 class="display-4 fw-bold mb-24">Meus Favoritos</h1>
                            <p class="text-lg text-secondary-light mb-0">
                                Produtos que você marcou como favoritos
                            </p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="text-lg-end">
                            <div class="d-inline-flex align-items-center bg-primary-100 rounded-pill px-4 py-2">
                                <i class="ri-heart-fill text-primary-600 me-2"></i>
                                <span class="text-primary-600 fw-medium"><?= number_format($total_records) ?> favoritos</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filtros -->
        <section class="py-40">
            <div class="container">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-24">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label text-primary-light fw-medium">Buscar</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                                       placeholder="Nome do produto...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-primary-light fw-medium">Categoria</label>
                                <select class="form-select" name="category_id">
                                    <option value="">Todas as categorias</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= ($_GET['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-primary-light fw-medium">Por página</label>
                                <select class="form-select" name="limit">
                                    <option value="12" <?= $limit == 12 ? 'selected' : '' ?>>12</option>
                                    <option value="24" <?= $limit == 24 ? 'selected' : '' ?>>24</option>
                                    <option value="48" <?= $limit == 48 ? 'selected' : '' ?>>48</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ri-search-line me-2"></i>Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Lista de Favoritos -->
        <section class="pb-80">
            <div class="container">
                <?php if (empty($favorites)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-80 text-center">
                            <i class="ri-heart-line" style="font-size: 4rem; color: #6c757d;"></i>
                            <h5 class="mt-3 text-primary-light">Nenhum favorito encontrado</h5>
                            <p class="text-secondary-light">Você ainda não favoritou nenhum produto ou os filtros aplicados não retornaram resultados.</p>
                            <a href="produtos.php" class="btn btn-primary">Ver Produtos</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($favorites as $favorite): ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="position-relative">
                                        <?php if (!empty($favorite['image_path']) && file_exists($favorite['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($favorite['image_path']) ?>" 
                                                 class="card-img-top" 
                                                 alt="<?= htmlspecialchars($favorite['name']) ?>"
                                                 style="height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-neutral-200 d-flex align-items-center justify-content-center" 
                                                 style="height: 200px;">
                                                <i class="ri-image-line text-neutral-400" style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Badge de Favorito -->
                                        <div class="position-absolute top-0 end-0 m-3">
                                            <span class="badge bg-danger">
                                                <i class="ri-heart-fill me-1"></i>Favorito
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title text-primary-light fw-bold mb-2">
                                            <?= htmlspecialchars($favorite['name']) ?>
                                        </h5>
                                        
                                        <p class="card-text text-secondary-light mb-3 flex-grow-1">
                                            <?= htmlspecialchars($favorite['short_description']) ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="badge bg-primary-subtle text-primary-600">
                                                <?= htmlspecialchars($favorite['category_name'] ?? 'Sem categoria') ?>
                                            </span>
                                            
                                            <?php if ($favorite['individual_sale'] && $favorite['individual_price'] > 0): ?>
                                                <span class="text-success fw-bold">
                                                    R$ <?= number_format($favorite['individual_price'], 2, ',', '.') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-success fw-bold">Gratuito</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <a href="produto.php?slug=<?= htmlspecialchars($favorite['slug']) ?>" 
                                               class="btn btn-primary flex-grow-1">
                                                <i class="ri-eye-line me-2"></i>Ver Produto
                                            </a>
                                            
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="removeFavorite(<?= $favorite['id'] ?>)">
                                                <i class="ri-heart-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-5">
                            <div class="text-sm text-secondary-light">
                                Mostrando <?= (($page - 1) * $limit) + 1 ?> a <?= min($page * $limit, $total_records) ?> de <?= number_format($total_records) ?> favoritos
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
        </section>

    <script>
        function removeFavorite(productId) {
            if (!confirm('Tem certeza que deseja remover este produto dos favoritos?')) {
                return;
            }
            
            fetch('ajax/favorite_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'remove',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Recarregar a página após 1 segundo
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Erro ao remover favorito', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao remover favorito:', error);
                showNotification('Erro ao remover favorito', 'error');
            });
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
    </script>

<?php include './partials/layouts/layoutBottom.php' ?>

require_once 'includes/Auth.php';
require_once 'includes/Product.php';

$auth = new Auth($pdo);
$product = new Product($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login');
    exit;
}

// Obter parâmetros de paginação e filtros
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 12;

$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = trim($_GET['search']);
}
if (!empty($_GET['category_id'])) {
    $filters['category_id'] = $_GET['category_id'];
}

// Obter favoritos do usuário
$result = $product->getUserFavoritesPaginated($_SESSION['user_id'], $page, $limit, $filters);
$favorites = $result['favorites'];
$total_records = $result['total_records'];
$total_pages = $result['total_pages'];

// Obter categorias para filtro
$categories = $product->getAllCategories();
?>

<?php include './partials/layouts/layoutTop.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="pe-lg-4">
                            <h1 class="display-4 fw-bold mb-24">Meus Favoritos</h1>
                            <p class="text-lg text-secondary-light mb-0">
                                Produtos que você marcou como favoritos
                            </p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="text-lg-end">
                            <div class="d-inline-flex align-items-center bg-primary-100 rounded-pill px-4 py-2">
                                <i class="ri-heart-fill text-primary-600 me-2"></i>
                                <span class="text-primary-600 fw-medium"><?= number_format($total_records) ?> favoritos</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filtros -->
        <section class="py-40">
            <div class="container">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-24">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label text-primary-light fw-medium">Buscar</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                                       placeholder="Nome do produto...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-primary-light fw-medium">Categoria</label>
                                <select class="form-select" name="category_id">
                                    <option value="">Todas as categorias</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= ($_GET['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-primary-light fw-medium">Por página</label>
                                <select class="form-select" name="limit">
                                    <option value="12" <?= $limit == 12 ? 'selected' : '' ?>>12</option>
                                    <option value="24" <?= $limit == 24 ? 'selected' : '' ?>>24</option>
                                    <option value="48" <?= $limit == 48 ? 'selected' : '' ?>>48</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ri-search-line me-2"></i>Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Lista de Favoritos -->
        <section class="pb-80">
            <div class="container">
                <?php if (empty($favorites)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-80 text-center">
                            <i class="ri-heart-line" style="font-size: 4rem; color: #6c757d;"></i>
                            <h5 class="mt-3 text-primary-light">Nenhum favorito encontrado</h5>
                            <p class="text-secondary-light">Você ainda não favoritou nenhum produto ou os filtros aplicados não retornaram resultados.</p>
                            <a href="produtos.php" class="btn btn-primary">Ver Produtos</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($favorites as $favorite): ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="position-relative">
                                        <?php if (!empty($favorite['image_path']) && file_exists($favorite['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($favorite['image_path']) ?>" 
                                                 class="card-img-top" 
                                                 alt="<?= htmlspecialchars($favorite['name']) ?>"
                                                 style="height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-neutral-200 d-flex align-items-center justify-content-center" 
                                                 style="height: 200px;">
                                                <i class="ri-image-line text-neutral-400" style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Badge de Favorito -->
                                        <div class="position-absolute top-0 end-0 m-3">
                                            <span class="badge bg-danger">
                                                <i class="ri-heart-fill me-1"></i>Favorito
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title text-primary-light fw-bold mb-2">
                                            <?= htmlspecialchars($favorite['name']) ?>
                                        </h5>
                                        
                                        <p class="card-text text-secondary-light mb-3 flex-grow-1">
                                            <?= htmlspecialchars($favorite['short_description']) ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="badge bg-primary-subtle text-primary-600">
                                                <?= htmlspecialchars($favorite['category_name'] ?? 'Sem categoria') ?>
                                            </span>
                                            
                                            <?php if ($favorite['individual_sale'] && $favorite['individual_price'] > 0): ?>
                                                <span class="text-success fw-bold">
                                                    R$ <?= number_format($favorite['individual_price'], 2, ',', '.') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-success fw-bold">Gratuito</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <a href="produto.php?slug=<?= htmlspecialchars($favorite['slug']) ?>" 
                                               class="btn btn-primary flex-grow-1">
                                                <i class="ri-eye-line me-2"></i>Ver Produto
                                            </a>
                                            
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="removeFavorite(<?= $favorite['id'] ?>)">
                                                <i class="ri-heart-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-5">
                            <div class="text-sm text-secondary-light">
                                Mostrando <?= (($page - 1) * $limit) + 1 ?> a <?= min($page * $limit, $total_records) ?> de <?= number_format($total_records) ?> favoritos
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
        </section>

    <script>
        function removeFavorite(productId) {
            if (!confirm('Tem certeza que deseja remover este produto dos favoritos?')) {
                return;
            }
            
            fetch('ajax/favorite_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'remove',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Recarregar a página após 1 segundo
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Erro ao remover favorito', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao remover favorito:', error);
                showNotification('Erro ao remover favorito', 'error');
            });
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
    </script>

<?php include './partials/layouts/layoutBottom.php' ?>
