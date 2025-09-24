<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/SiteConfig.php';

$auth = new Auth($pdo);
$product = new Product($pdo);
$siteConfig = new SiteConfig($pdo);

// Verificar se usuário está logado
$isLoggedIn = $auth->isLoggedIn();
$hasSubscription = $auth->hasActiveSubscription();

// Obter dados para o dashboard
$featuredProducts = $product->getFeaturedProducts(4); // Produtos em destaque
$freeProducts = $product->getFreeProducts(4); // Produtos gratuitos
$recentProducts = $product->getRecentProducts(4); // Produtos recentes

$categoriesResult = $product->getCategoriesWithProductCount(1, 6); // Categorias com contagem
$categories = $categoriesResult['categories'];

// Se não há produtos com downloads, pegar produtos aleatórios
if (empty($topDownloads)) {
    $topDownloads = $product->getRandomProducts(4);
}

// Estatísticas
$totalCategories = count($categories);
$totalProducts = $product->getTotalProducts();
$userDownloads = $isLoggedIn ? $product->getUserTotalDownloads($_SESSION['user_id']) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include './partials/head.php' ?>

<body>

    <?php include './partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include './partials/navbar.php' ?>

        <!-- Banner/Hero Section -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-12">
                        <h1 class="display-4 fw-bold mb-24"><?= htmlspecialchars($siteConfig->getSiteName()) ?></h1>
                        <p class="text-lg text-neutral-600 mb-32">
                            <?= htmlspecialchars($siteConfig->getSiteDescription()) ?>
                        </p>
                        <div class="d-flex gap-3">
                            <a href="produtos" class="btn btn-primary btn-lg">
                                <i class="ri-box-line me-2"></i>Produtos
                            </a>
                            <a href="perfil" class="btn btn-primary btn-lg">
                                <i class="ri-user-line me-2"></i>Meu Perfil
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Estatísticas -->
        <section class="py-40 border-bottom">
            <div class="container">
                <div class="row gy-4">
                    <div class="col-lg-4 col-sm-6">
                        <div class="card px-24 py-16 shadow-none radius-12 border h-100 bg-gradient-start-3">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center gap-16">
                                    <span class="w-40-px h-40-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
                                        <i class="ri-folder-line" style="font-size: 1.2rem;"></i>
                                    </span>
                                    <div class="flex-grow-1">
                                        <h4 class="fw-bold mb-0 text-primary-light"><?= $totalCategories ?></h4>
                                        <span class="fw-medium text-neutral-600 text-sm">Categorias</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-sm-6">
                        <div class="card px-24 py-16 shadow-none radius-12 border h-100 bg-gradient-start-5">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center gap-16">
                                    <span class="w-40-px h-40-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
                                        <i class="ri-shopping-bag-line" style="font-size: 1.2rem;"></i>
                                    </span>
                                    <div class="flex-grow-1">
                                        <h4 class="fw-bold mb-0 text-primary-light"><?= $totalProducts ?></h4>
                                        <span class="fw-medium text-neutral-600 text-sm">Total Produtos</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-sm-6">
                        <div class="card px-24 py-16 shadow-none radius-12 border h-100 bg-gradient-start-2">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center gap-16">
                                    <span class="w-40-px h-40-px bg-primary-600 flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle">
                                        <i class="ri-download-line" style="font-size: 1.2rem;"></i>
                                    </span>
                                    <div class="flex-grow-1">
                                        <h4 class="fw-bold mb-0 text-primary-light"><?= $userDownloads ?></h4>
                                        <span class="fw-medium text-neutral-600 text-sm">Meus Downloads</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Produtos em Destaque -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h6 class="mb-16">produtos em destaque</h6>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($featuredProducts as $prod): ?>
                    <div class="col-xxl-3 col-sm-6 col-xs-6">
                        <div class="nft-card bg-base radius-16 overflow-hidden">
                            <div class="radius-16 overflow-hidden">
                                <?php if ($prod['image_path']): ?>
                                    <img src="<?= htmlspecialchars($prod['image_path']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <div class="w-100 h-100 bg-neutral-200 d-flex align-items-center justify-content-center">
                                        <i class="ri-file-text-line text-neutral-600" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-10">
                                <h6 class="text-md fw-bold text-primary-light"><?= htmlspecialchars($prod['name']) ?></h6>
                                <div class="d-flex align-items-center gap-8">
                                    <i class="ri-folder-line text-neutral-600"></i>
                                    <span class="text-sm text-neutral-600 fw-medium"><?= htmlspecialchars($prod['category_name'] ?? 'Sem categoria') ?></span>
                                </div>
                                <div class="mt-10 d-flex align-items-center justify-content-between gap-8 flex-wrap">
                                    <div class="d-flex flex-wrap gap-4">
                                        <?php 
                                        // Mostrar planos associados ao produto
                                        $plan_names = $prod['plan_names'] ?? '';
                                        if ($prod['individual_sale'] && $prod['individual_price'] > 0) {
                                            ?>
                                            <span class="text-sm text-warning fw-semibold">Venda Individual</span>
                                            <?php
                                        } elseif (!empty($plan_names)) {
                                            $plans_array = explode(', ', $plan_names);
                                            foreach ($plans_array as $plan_name) {
                                                $plan_name = trim($plan_name);
                                                $text_class = 'text-primary-light';
                                                if (stripos($plan_name, 'básico') !== false) {
                                                    $text_class = 'text-success';
                                                } elseif (stripos($plan_name, 'premium') !== false) {
                                                    $text_class = 'text-warning';
                                                } elseif (stripos($plan_name, 'exclusivo') !== false) {
                                                    $text_class = 'text-danger';
                                                }
                                                ?>
                                                <span class="text-sm <?= $text_class ?> fw-semibold"><?= htmlspecialchars($plan_name) ?></span>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <span class="text-sm text-success fw-semibold">Gratuito</span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <span class="text-sm fw-semibold text-primary-600"><?= $prod['downloads_count'] ?> downloads</span>
                                </div>
                                <div class="d-flex align-items-center flex-wrap mt-12 gap-8">
                                    <a href="produto.php?id=<?= $prod['id'] ?>" class="btn rounded-pill border text-neutral-500 border-neutral-500 radius-8 px-12 py-6 bg-hover-neutral-500 text-hover-white flex-grow-1">Ver Detalhes</a>
                                    <?php 
                                    // Verificar se o usuário pode baixar o produto
                                    $canDownload = false;
                                    if ($isLoggedIn) {
                                        $canDownload = $product->canUserDownload($prod['id'], $_SESSION['user_id']);
                                    }
                                    
                                    if ($canDownload): ?>
                                        <a href="download.php?id=<?= $prod['id'] ?>" class="btn rounded-pill btn-primary-600 radius-8 px-12 py-6 flex-grow-1">Baixar</a>
                                    <?php elseif ($prod['individual_sale'] && $prod['individual_price'] > 0): ?>
                                        <a href="checkout.php?id=<?= $prod['id'] ?>" class="btn rounded-pill btn-warning radius-8 px-12 py-6 flex-grow-1">Comprar</a>
                                    <?php else: ?>
                                        <a href="planos.php" class="btn rounded-pill btn-warning radius-8 px-12 py-6 flex-grow-1">Assinar</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Produtos Recentes -->
        <?php if (!empty($recentProducts)): ?>
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h6 class="mb-16">produtos recentes</h6>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($recentProducts as $prod): ?>
                    <div class="col-xxl-3 col-sm-6 col-xs-6">
                        <div class="nft-card bg-base radius-16 overflow-hidden">
                            <div class="radius-16 overflow-hidden">
                                <?php if ($prod['image_path']): ?>
                                    <img src="<?= htmlspecialchars($prod['image_path']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <div class="w-100 h-100 bg-neutral-200 d-flex align-items-center justify-content-center">
                                        <i class="ri-file-text-line text-neutral-600" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-10">
                                <h6 class="text-md fw-bold text-primary-light"><?= htmlspecialchars($prod['name']) ?></h6>
                                <div class="d-flex align-items-center gap-8">
                                    <i class="ri-folder-line text-neutral-600"></i>
                                    <span class="text-sm text-neutral-600 fw-medium"><?= htmlspecialchars($prod['category_name'] ?? 'Sem categoria') ?></span>
                                </div>
                                <div class="mt-10 d-flex align-items-center justify-content-between gap-8 flex-wrap">
                                    <div class="d-flex flex-wrap gap-4">
                                        <?php 
                                        // Mostrar planos associados ao produto
                                        $plan_names = $prod['plan_names'] ?? '';
                                        if ($prod['individual_sale'] && $prod['individual_price'] > 0) {
                                            ?>
                                            <span class="text-sm text-warning fw-semibold">Venda Individual</span>
                                            <?php
                                        } elseif (!empty($plan_names)) {
                                            $plans_array = explode(', ', $plan_names);
                                            foreach ($plans_array as $plan_name) {
                                                $plan_name = trim($plan_name);
                                                $text_class = 'text-primary-light';
                                                if (stripos($plan_name, 'básico') !== false) {
                                                    $text_class = 'text-success';
                                                } elseif (stripos($plan_name, 'premium') !== false) {
                                                    $text_class = 'text-warning';
                                                } elseif (stripos($plan_name, 'exclusivo') !== false) {
                                                    $text_class = 'text-danger';
                                                }
                                                ?>
                                                <span class="text-sm <?= $text_class ?> fw-semibold"><?= htmlspecialchars($plan_name) ?></span>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <span class="text-sm text-success fw-semibold">Gratuito</span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <span class="text-sm fw-semibold text-primary-600"><?= $prod['downloads_count'] ?> downloads</span>
                                </div>
                                <div class="d-flex align-items-center flex-wrap mt-12 gap-8">
                                    <a href="produto.php?id=<?= $prod['id'] ?>" class="btn rounded-pill border text-neutral-500 border-neutral-500 radius-8 px-12 py-6 bg-hover-neutral-500 text-hover-white flex-grow-1">Ver Detalhes</a>
                                    <?php 
                                    // Verificar se usuário pode baixar
                                    $canDownload = false;
                                    if ($isLoggedIn) {
                                        $canDownload = $product->canUserDownload($prod['id'], $_SESSION['user_id']);
                                    }
                                    
                                    if ($canDownload): ?>
                                        <a href="download.php?id=<?= $prod['id'] ?>" class="btn rounded-pill btn-primary-600 radius-8 px-12 py-6 flex-grow-1">Baixar</a>
                                    <?php elseif ($prod['individual_sale'] && $prod['individual_price'] > 0): ?>
                                        <a href="checkout.php?id=<?= $prod['id'] ?>" class="btn rounded-pill btn-warning radius-8 px-12 py-6 flex-grow-1">Comprar</a>
                                    <?php else: ?>
                                        <a href="planos.php" class="btn rounded-pill btn-warning radius-8 px-12 py-6 flex-grow-1">Assinar</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Produtos Gratuitos -->
        <?php if (!empty($freeProducts)): ?>
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h6 class="mb-16">produtos gratuitos</h6>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($freeProducts as $prod): ?>
                    <div class="col-xxl-3 col-sm-6 col-xs-6">
                        <div class="nft-card bg-base radius-16 overflow-hidden">
                            <div class="radius-16 overflow-hidden">
                                <?php if ($prod['image_path']): ?>
                                    <img src="<?= htmlspecialchars($prod['image_path']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <div class="w-100 h-100 bg-neutral-200 d-flex align-items-center justify-content-center">
                                        <i class="ri-file-text-line text-neutral-600" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-10">
                                <h6 class="text-md fw-bold text-primary-light"><?= htmlspecialchars($prod['name']) ?></h6>
                                <div class="d-flex align-items-center gap-8">
                                    <i class="ri-folder-line text-neutral-600"></i>
                                    <span class="text-sm text-neutral-600 fw-medium"><?= htmlspecialchars($prod['category_name'] ?? 'Sem categoria') ?></span>
                                </div>
                                <div class="mt-10 d-flex align-items-center justify-content-between gap-8 flex-wrap">
                                    <div class="d-flex flex-wrap gap-4">
                                        <?php 
                                        // Mostrar planos associados ao produto
                                        $plan_names = $prod['plan_names'] ?? '';
                                        if ($prod['individual_sale'] && $prod['individual_price'] > 0) {
                                            ?>
                                            <span class="text-sm text-warning fw-semibold">Venda Individual</span>
                                            <?php
                                        } elseif (!empty($plan_names)) {
                                            $plans_array = explode(', ', $plan_names);
                                            foreach ($plans_array as $plan_name) {
                                                $plan_name = trim($plan_name);
                                                $text_class = 'text-primary-light';
                                                if (stripos($plan_name, 'básico') !== false) {
                                                    $text_class = 'text-success';
                                                } elseif (stripos($plan_name, 'premium') !== false) {
                                                    $text_class = 'text-warning';
                                                } elseif (stripos($plan_name, 'exclusivo') !== false) {
                                                    $text_class = 'text-danger';
                                                }
                                                ?>
                                                <span class="text-sm <?= $text_class ?> fw-semibold"><?= htmlspecialchars($plan_name) ?></span>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <span class="text-sm text-success fw-semibold">Gratuito</span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <span class="text-sm fw-semibold text-primary-600"><?= $prod['downloads_count'] ?> downloads</span>
                                </div>
                                <div class="d-flex align-items-center flex-wrap mt-12 gap-8">
                                    <a href="produto.php?id=<?= $prod['id'] ?>" class="btn rounded-pill border text-neutral-500 border-neutral-500 radius-8 px-12 py-6 bg-hover-neutral-500 text-hover-white flex-grow-1">Ver Detalhes</a>
                                    <?php 
                                    // Verificar se usuário pode baixar
                                    $canDownload = false;
                                    if ($isLoggedIn) {
                                        $canDownload = $product->canUserDownload($prod['id'], $_SESSION['user_id']);
                                    }
                                    
                                    if ($canDownload): ?>
                                        <a href="download.php?id=<?= $prod['id'] ?>" class="btn rounded-pill btn-primary-600 radius-8 px-12 py-6 flex-grow-1">Baixar</a>
                                    <?php elseif ($prod['individual_sale'] && $prod['individual_price'] > 0): ?>
                                        <a href="checkout.php?id=<?= $prod['id'] ?>" class="btn rounded-pill btn-warning radius-8 px-12 py-6 flex-grow-1">Comprar</a>
                                    <?php else: ?>
                                        <a href="planos.php" class="btn rounded-pill btn-warning radius-8 px-12 py-6 flex-grow-1">Assinar</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Categorias e Produtos Mais Baixados -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <!-- Categorias -->
                    <div class="col-lg-6 mb-40">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-base py-16 px-24">
                                <h3 class="mb-0">categorias</h3>
                            </div>
                            <div class="card-body p-24">
                                <div class="row g-3">
                                    <?php foreach ($categories as $category): ?>
                                    <div class="col-md-6">
                                        <a href="produtos.php?category=<?= $category['id'] ?>" class="d-block p-16 border radius-12 text-decoration-none hover-bg-neutral-50">
                                            <div class="d-flex align-items-center">
                                                <i class="ri-folder-line text-primary me-12" style="font-size: 1.5rem;"></i>
                                                <div>
                                                    <h6 class="mb-4 text-neutral-900"><?= htmlspecialchars($category['name']) ?></h6>
                                                    <small class="text-neutral-600"><?= $category['product_count'] ?> produtos</small>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Produtos Mais Baixados -->
                    <div class="col-lg-6 mb-40">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-base py-16 px-24">
                                <h3 class="mb-0">produtos mais baixados</h3>
                            </div>
                            <div class="card-body p-24">
                                <?php foreach ($topDownloads as $prod): ?>
                                <div class="d-flex align-items-center mb-16 pb-16 border-bottom">
                                    <div class="me-16">
                                        <?php if ($prod['image_path']): ?>
                                            <img src="<?= htmlspecialchars($prod['image_path']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="w-48-px h-48-px rounded object-fit-cover">
                                        <?php else: ?>
                                            <div class="w-48-px h-48-px bg-neutral-200 rounded d-flex align-items-center justify-content-center">
                                                <i class="ri-file-text-line text-neutral-600"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-4 text-neutral-900"><?= htmlspecialchars($prod['name']) ?></h6>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php 
                                            // Mostrar planos associados ao produto
                                            $plan_names = $prod['plan_names'] ?? '';
                                            if ($prod['individual_sale'] && $prod['individual_price'] > 0) {
                                                ?>
                                                <span class="badge bg-warning">Venda Individual</span>
                                                <?php
                                            } elseif (!empty($plan_names)) {
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
                                                    <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($plan_name) ?></span>
                                                    <?php
                                                }
                                            } else {
                                                ?>
                                                <span class="badge bg-success">Gratuito</span>
                                                <?php
                                            }
                                            ?>
                                            <small class="text-neutral-600">
                                                <i class="ri-download-line me-1"></i>
                                                <?= $prod['downloads_count'] ?> downloads
                                            </small>
                                        </div>
                                    </div>
                                    <a href="produto.php?id=<?= $prod['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php include './partials/footer.php' ?>
    </main>

    <?php include './partials/scripts.php' ?>

</body>
</html>
