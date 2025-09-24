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

// Parâmetros de filtro
$categoryId = $_GET['category'] ?? null;
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? 'all'; // all, free, premium

// Obter produtos baseado nos filtros
if (!empty($search)) {
    $products = $product->searchProducts($search);
} elseif ($type === 'free') {
    $products = $product->getFreeProducts();
} elseif ($type === 'premium') {
    $products = $product->getPremiumProducts();
} elseif ($categoryId) {
    $products = $product->getProductsByCategory($categoryId);
} else {
    // Se não há filtros, mostrar todos os produtos ativos
    $result = $product->getAllProducts(1, 50, ['status' => 'active']);
    $products = $result['products'];
}

$categories = $product->getCategories();
$hasSubscription = $auth->hasActiveSubscription();
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include './partials/head.php' ?>

<body>

    <?php include './partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include './partials/navbar.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Produtos</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Explore nossa biblioteca de conteúdos exclusivos
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filtros -->
        <section class="py-40 border-bottom">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Buscar produtos..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="category" class="form-select">
                                    <option value="">Todas as categorias</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= $categoryId == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="type" class="form-select">
                                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Todos os produtos</option>
                                    <option value="free" <?= $type === 'free' ? 'selected' : '' ?>>Apenas gratuitos</option>
                                    <option value="premium" <?= $type === 'premium' ? 'selected' : '' ?>>Apenas premium</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Produtos -->
        <section class="py-80">
            <div class="container">
                <?php if (empty($products)): ?>
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="py-80">
                            <i class="ri-search-line text-neutral-600" style="font-size: 4rem;"></i>
                            <h4 class="mt-24 mb-16">Nenhum produto encontrado</h4>
                            <p class="text-neutral-600 mb-32">
                                Tente ajustar os filtros ou fazer uma nova busca
                            </p>
                            <a href="produtos.php" class="btn btn-primary">Ver todos os produtos</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                
                <div class="row">
                    <div class="col-12 mb-40">
                        <h3 class="mb-0">
                            <?= count($products) ?> produto<?= count($products) !== 1 ? 's' : '' ?> encontrado<?= count($products) !== 1 ? 's' : '' ?>
                        </h3>
                    </div>
                </div>
                
                <div class="row">
                    <?php foreach ($products as $prod): ?>
                    <div class="col-lg-4 col-md-6 mb-32">
                        <div class="card h-100 border-0 shadow-sm">
                            <?php if ($prod['image_path']): ?>
                            <img src="<?= htmlspecialchars($prod['image_path']) ?>" class="card-img-top" alt="<?= htmlspecialchars($prod['name']) ?>">
                            <?php endif; ?>
                            
                            <div class="card-body p-24">
                                <div class="d-flex align-items-center mb-16">
                                    <?php 
                                    // Mostrar planos associados ao produto
                                    $plan_names = $prod['plan_names'] ?? '';
                                    if (!empty($plan_names)) {
                                        $plans_array = explode(', ', $plan_names);
                                        foreach ($plans_array as $plan_name) {
                                            $plan_name = trim($plan_name);
                                            $badge_class = 'bg-primary';
                                            if (stripos($plan_name, 'básico') !== false) {
                                                $badge_class = 'bg-success';
                                            } elseif (stripos($plan_name, 'premium') !== false) {
                                                $badge_class = 'bg-warning';
                                            } elseif (stripos($plan_name, 'exclusivo') !== false) {
                                                $badge_class = 'bg-danger';
                                            }
                                            ?>
                                            <div class="badge <?= $badge_class ?> text-white me-8 mb-8"><?= htmlspecialchars($plan_name) ?></div>
                                            <?php
                                        }
                                    } elseif ($prod['individual_sale'] && $prod['individual_price'] > 0) {
                                        ?>
                                        <div class="badge bg-warning text-white me-12">Venda Individual</div>
                                        <?php
                                    } else {
                                        ?>
                                        <div class="badge bg-success text-white me-12">Gratuito</div>
                                        <?php
                                    }
                                    ?>
                                    
                                    <?php if ($prod['category_name']): ?>
                                        <small class="text-neutral-600"><?= htmlspecialchars($prod['category_name']) ?></small>
                                    <?php endif; ?>
                                </div>
                                
                                <h5 class="card-title mb-12"><?= htmlspecialchars($prod['name']) ?></h5>
                                <p class="card-text text-neutral-600 mb-16">
                                    <?= htmlspecialchars(substr($prod['short_description'], 0, 120)) ?>...
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-16">
                                    <small class="text-neutral-600">
                                        <i class="ri-download-line me-8"></i>
                                        <?= $prod['downloads_count'] ?> downloads
                                    </small>
                                    
                                    <?php if ($prod['individual_sale'] && $prod['individual_price'] > 0): ?>
                                        <span class="text-primary fw-bold">
                                            R$ <?= number_format($prod['individual_price'], 2, ',', '.') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="produto.php?id=<?= $prod['id'] ?>" class="btn btn-outline-primary flex-fill">
                                        Ver Detalhes
                                    </a>
                                    
                                    <?php 
                                    // Verificar se o usuário pode baixar o produto
                                    $canDownload = $product->canUserDownload($prod['id'], $_SESSION['user_id']);
                                    
                                    if ($canDownload): ?>
                                        <a href="download.php?id=<?= $prod['id'] ?>" class="btn btn-primary">
                                            <i class="ri-download-line"></i>
                                        </a>
                                    <?php elseif ($prod['individual_sale'] && $prod['individual_price'] > 0): ?>
                                        <a href="checkout.php?id=<?= $prod['id'] ?>" class="btn btn-warning">
                                            <i class="ri-shopping-cart-line"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="planos.php" class="btn btn-warning">
                                            <i class="ri-lock-line"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php include './partials/footer.php' ?>
    </main>

    <?php include './partials/scripts.php' ?>

</body>
</html>
