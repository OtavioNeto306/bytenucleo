<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';

// ===== FUNÇÃO PARA SALVAR LOGS LOCAIS =====
function saveLog($message) {
    $logFile = __DIR__ . '/produto_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ===== DEBUG COMPLETO DA SESSÃO =====
saveLog("=== DEBUG COMPLETO DA SESSÃO ===");
saveLog("session_status(): " . session_status());
saveLog("session_id(): " . (session_id() ?? 'NÃO DEFINIDO'));
saveLog("session_name(): " . (session_name() ?? 'NÃO DEFINIDO'));
saveLog("Todas as variáveis de sessão: " . json_encode($_SESSION));
saveLog("=== FIM DEBUG SESSÃO ===");

// ===== LOGS DETALHADOS PARA DEBUG =====
saveLog("=== PRODUTO DEBUG INICIADO ===");
saveLog("Script iniciado - produto.php");
saveLog("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
saveLog("QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'N/A'));
saveLog("HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'N/A'));

$auth = new Auth($pdo);
$product = new Product($pdo);

// Verificar se usuário está logado
saveLog("Verificando se usuário está logado...");
if (!$auth->isLoggedIn()) {
    saveLog("Usuário não logado - redirecionando para login.php");
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

saveLog("Usuário logado com sucesso");
saveLog("SESSION user_id: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO'));

// Obter ID do produto
saveLog("Verificando ID do produto...");
$productId = $_GET['id'] ?? null;
saveLog("GET['id'] recebido: " . ($productId ?? 'NÃO DEFINIDO'));

if (!$productId) {
    saveLog("ID do produto não fornecido - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

saveLog("ID do produto válido: $productId");

// Obter dados do produto
saveLog("Buscando dados do produto com ID: $productId");
$productData = $product->getProductById($productId);

if (!$productData) {
    saveLog("Produto não encontrado - redirecionando para produtos.php");
    header('Location: produtos.php');
    exit;
}

saveLog("Produto encontrado: " . $productData['name']);
saveLog("Dados do produto: " . json_encode($productData));

saveLog("Verificando permissões do usuário...");
$hasSubscription = $auth->hasActiveSubscription();
saveLog("Usuário tem assinatura ativa: " . ($hasSubscription ? 'true' : 'false'));

$canDownload = $product->canDownload($productId, $_SESSION['user_id']);
saveLog("Usuário pode baixar: " . ($canDownload ? 'true' : 'false'));

$canViewContent = $product->canViewContent($productId, $_SESSION['user_id']);
saveLog("Usuário pode visualizar conteúdo: " . ($canViewContent ? 'true' : 'false'));

$userDownloads = $product->getUserDownloads($_SESSION['user_id'], 5);
saveLog("Downloads do usuário obtidos: " . count($userDownloads) . " registros");

// Obter vídeos e materiais do produto
$productVideos = $product->getProductVideos($productId);
$productMaterials = $product->getProductMaterialsWithReleaseStatus($productId, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include './partials/head.php' ?>

<body>

    <?php include './partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include './partials/navbar.php' ?>

        <!-- Breadcrumb -->
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0"><?= htmlspecialchars($productData['name']) ?></h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="produtos.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Produtos
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium"><?= htmlspecialchars($productData['name']) ?></li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24"><?= htmlspecialchars($productData['name']) ?></h1>
                        <p class="text-lg text-secondary-light mb-0">
                            <?= htmlspecialchars($productData['short_description']) ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Produto -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <!-- Vídeo/Imagem do produto -->
                    <div class="col-lg-6 mb-40">
                        <?php if (!empty($productData['video_apresentacao'])): ?>
                            <?php 
                            // Usar thumbnail personalizado se disponível, senão usar thumbnail padrão do YouTube
                            $video_code = $productData['video_apresentacao'];
                            $thumbnail_url = !empty($productData['video_thumbnail']) ? $productData['video_thumbnail'] : "https://img.youtube.com/vi/" . explode('?', $video_code)[0] . "/maxresdefault.jpg";
                            ?>
                            <div class="position-relative rounded overflow-hidden shadow-sm">
                                <img src="<?= htmlspecialchars($thumbnail_url) ?>" 
                                     class="img-fluid w-100" alt="<?= htmlspecialchars($productData['name']) ?>"
                                     style="height: 400px; object-fit: cover;">
                                <div class="position-absolute top-50 start-50 translate-middle">
                                    <button type="button" class="btn btn-primary btn-lg rounded-circle shadow" 
                                            onclick="openMainVideoModal('<?= htmlspecialchars($productData['video_apresentacao']) ?>', '<?= htmlspecialchars($productData['name']) ?>')"
                                            style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                                        <i class="ri-play-fill" style="font-size: 2rem;"></i>
                                    </button>
                                </div>
                            </div>
                        <?php elseif (!empty($productData['image_path']) && file_exists($productData['image_path'])): ?>
                            <div class="rounded overflow-hidden shadow-sm">
                                <img src="<?= htmlspecialchars($productData['image_path']) ?>" 
                                     class="img-fluid w-100" alt="<?= htmlspecialchars($productData['name']) ?>"
                                     style="height: 400px; object-fit: cover;">
                            </div>
                        <?php else: ?>
                            <div class="bg-neutral-100 rounded d-flex align-items-center justify-content-center shadow-sm" style="height: 400px;">
                                <div class="text-center">
                                                                    <i class="ri-file-text-line text-secondary-light mb-16" style="font-size: 4rem;"></i>
                                <p class="text-secondary-light mb-0">Sem imagem disponível</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informações do produto -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-40">
                                <div class="d-flex flex-wrap gap-8 mb-16">
                                    <?php
                                    // Verificar se o produto tem planos associados
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_plans WHERE product_id = ?");
                                    $stmt->execute([$productData['id']]);
                                    $hasPlans = $stmt->fetchColumn() > 0;
                                    
                                    if ($hasPlans): ?>
                                        <span class="badge bg-primary-600 text-white px-12 py-6 radius-8 text-sm fw-semibold">
                                            <i class="ri-star-line me-1"></i>
                                            <?= htmlspecialchars($productData['plan_names'] ?: 'Premium') ?>
                                        </span>
                                    <?php elseif ($productData['individual_sale'] && $productData['individual_price'] > 0): ?>
                                        <span class="badge bg-warning text-white px-12 py-6 radius-8 text-sm fw-semibold">
                                            <i class="ri-shopping-cart-line me-1"></i>
                                            Venda Individual
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success text-white px-12 py-6 radius-8 text-sm fw-semibold">
                                            <i class="ri-check-line me-1"></i>
                                            Gratuito
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($productData['featured']): ?>
                                        <span class="badge bg-warning text-body px-12 py-6 radius-8 text-sm fw-semibold">
                                            <i class="ri-fire-line me-1"></i>
                                            Destaque
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($productData['version'])): ?>
                                        <span class="badge bg-info text-white px-12 py-6 radius-8 text-sm fw-semibold">
                                            <i class="ri-code-line me-1"></i>
                                            v<?= htmlspecialchars($productData['version']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h1 class="h2 mb-16"><?= htmlspecialchars($productData['name']) ?></h1>
                                
                                <?php if ($productData['category_name']): ?>
                                    <div class="mb-16">
                                        <small class="text-secondary-light">
                                            <i class="ri-folder-line me-1"></i>
                                            <?= htmlspecialchars($productData['category_name']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex align-items-center mb-24">
                                    <div class="me-24">
                                        <small class="text-secondary-light d-block">
                                            <i class="ri-download-line me-1"></i>
                                            <?= number_format($productData['downloads_count']) ?> downloads
                                        </small>
                                    </div>
                                    <div>
                                        <small class="text-secondary-light d-block">
                                            <i class="ri-time-line me-1"></i>
                                            Adicionado em <?= date('d/m/Y', strtotime($productData['created_at'])) ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($productData['last_updated'])): ?>
                                    <div class="ms-3">
                                        <small class="text-secondary-light d-block">
                                            <i class="ri-refresh-line me-1"></i>
                                            Atualizado em <?= date('d/m/Y', strtotime($productData['last_updated'])) ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($productData['individual_sale'] && $productData['individual_price'] > 0): ?>
                                    <div class="mb-24">
                                        <h3 class="text-primary mb-0">
                                            <i class="ri-money-dollar-circle-line me-1"></i>
                                            R$ <?= number_format($productData['individual_price'], 2, ',', '.') ?>
                                        </h3>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="text-lg text-secondary-light mb-32">
                                    <?= htmlspecialchars($productData['full_description'] ?: $productData['short_description']) ?>
                                </p>
                                
                                <?php
                                // Logs específicos para debug dos botões
                                saveLog("Renderizando botões de ação...");
                                saveLog("canDownload: " . ($canDownload ? 'true' : 'false'));
                                saveLog("individual_sale: " . ($productData['individual_sale'] ? 'true' : 'false'));
                                saveLog("individual_price: " . ($productData['individual_price'] ?? 'NÃO DEFINIDO'));
                                ?>
                                
                                <div class="d-flex flex-wrap gap-3 mb-32">
                                    <?php if ($canDownload): ?>
                                        <a href="download.php?id=<?= $productData['id'] ?>" class="btn btn-primary-600 radius-8 px-20 py-11 flex-fill d-flex align-items-center justify-content-center gap-2">
                                            <i class="ri-download-line"></i>
                                            Baixar Produto
                                        </a>
                                    <?php elseif ($productData['individual_sale'] && $productData['individual_price'] > 0): ?>
                                        <?php
                                        saveLog("Renderizando botão de compra para checkout.php?id=" . $productData['id']);
                                        ?>
                                        <a href="checkout.php?id=<?= $productData['id'] ?>" class="btn btn-warning-600 radius-8 px-20 py-11 flex-fill d-flex align-items-center justify-content-center gap-2">
                                            <i class="ri-shopping-cart-line"></i>
                                            Comprar por R$ <?= number_format($productData['individual_price'], 2, ',', '.') ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="planos.php" class="btn btn-warning-600 radius-8 px-20 py-11 flex-fill d-flex align-items-center justify-content-center gap-2">
                                            <i class="ri-lock-line"></i>
                                            Assinar para Baixar
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($productData['demo_url'])): ?>
                                    <a href="<?= htmlspecialchars($productData['demo_url']) ?>" 
                                       target="_blank" 
                                       class="btn btn-outline-info-600 radius-8 px-20 py-11 d-flex align-items-center gap-2">
                                        <i class="ri-external-link-line"></i>
                                        Ver Demonstração
                                    </a>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-outline-danger-600 radius-8 px-20 py-11 d-flex align-items-center gap-2" id="favoriteBtn" data-product-id="<?= $productData['id'] ?>">
                                        <i class="ri-heart-line" id="favoriteIcon"></i>
                                        <span id="favoriteText">Favoritar</span>
                                    </button>
                                </div>
                                
                                <!-- Bloqueio de Conteúdo -->
                                <?php if (!$canViewContent && $productData['plan_name']): ?>
                                <div class="card border-0 shadow-sm bg-gradient-primary mb-32">
                                    <div class="card-body p-32 text-center">
                                        <div class="mb-16">
                                            <i class="ri-lock-line text-white" style="font-size: 3rem;"></i>
                                        </div>
                                        <h4 class="text-white mb-12">Conteúdo Bloqueado</h4>
                                        <p class="text-white-50 mb-24">
                                            Você precisa do plano <strong><?= htmlspecialchars($productData['plan_name']) ?></strong> para acessar este conteúdo completo.
                                        </p>
                                        <a href="planos.php" class="btn btn-light btn-lg">
                                            <i class="ri-star-line me-2"></i>
                                            Assinar Plano <?= htmlspecialchars($productData['plan_name']) ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($productData['tags'])): ?>
                                    <div class="mb-24">
                                        <h6 class="mb-12">Tags:</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach (explode(',', $productData['tags']) as $tag): ?>
                                                <span class="badge bg-light text-body px-12 py-6">
                                                    <?= htmlspecialchars(trim($tag)) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Descrição completa -->
                <?php if ($productData['full_description'] && $canViewContent): ?>
                <div class="row mt-80">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24">
                                <h3 class="mb-0">
                                    <i class="ri-file-text-line me-2"></i>
                                    Descrição Completa
                                </h3>
                            </div>
                            <div class="card-body p-40">
                                <div class="text-secondary-light">
                                    <?= nl2br(htmlspecialchars($productData['full_description'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Requisitos para Instalação -->
                <?php if (!empty($productData['requirements'])): ?>
                <div class="row mt-40">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24">
                                <h3 class="mb-0">
                                    <i class="ri-settings-3-line me-2"></i>
                                    Requisitos para Instalação
                                </h3>
                            </div>
                            <div class="card-body p-40">
                                <div class="text-secondary-light">
                                    <?= nl2br(htmlspecialchars($productData['requirements'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Vídeos do Curso -->
                <?php if (!empty($productVideos) && $canViewContent): ?>
                <div class="row mt-80">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24">
                                <div class="d-flex align-items-center justify-content-between">
                                <h3 class="mb-0">
                                    <i class="ri-play-circle-line me-2"></i>
                                    Conteúdo do Curso (<?= count($productVideos) ?> aulas)
                                </h3>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="text-center">
                                            <div class="progress" style="width: 100px; height: 8px;">
                                                <div class="progress-bar bg-success" role="progressbar" id="courseProgressBar" style="width: 0%"></div>
                                            </div>
                                            <small class="text-secondary-light" id="courseProgressText">0% concluído</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush" id="videoList">
                                    <?php foreach ($productVideos as $index => $video): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-20 px-24 border-0 bg-base video-item" 
                                             data-video-id="<?= $video['id'] ?>" data-video-index="<?= $index ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="me-16 position-relative">
                                                    <span class="badge bg-primary rounded-circle video-number" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                                        <?= $index + 1 ?>
                                                    </span>
                                                    <div class="video-completed-indicator position-absolute" style="display: none; top: -2px; right: -2px; width: 16px; height: 16px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white;">
                                                        <i class="ri-check-line text-white" style="font-size: 8px; line-height: 1;"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-4 fw-semibold video-title"><?= htmlspecialchars($video['title']) ?></h6>
                                                    <?php if ($video['description']): ?>
                                                        <small class="text-secondary-light"><?= htmlspecialchars($video['description']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if ($video['duration']): ?>
                                                    <span class="text-secondary-light small">
                                                        <i class="ri-time-line me-1"></i>
                                                        <?= htmlspecialchars($video['duration']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-outline-primary btn-sm watch-btn"
                                                        onclick="openVideoModal('<?= htmlspecialchars($video['youtube_url']) ?>', '<?= htmlspecialchars($video['title']) ?>', <?= $video['id'] ?>, <?= $index ?>)">
                                                    <i class="ri-play-line me-1"></i>
                                                    <span class="btn-text">Assistir</span>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Materiais de Apoio -->
                <?php if (!empty($productMaterials) && $canViewContent): ?>
                <div class="row mt-80">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24">
                                <h3 class="mb-0">
                                    <i class="ri-folder-line me-2"></i>
                                    Materiais Inclusos (<?= count($productMaterials) ?> itens)
                                </h3>
                            </div>
                            <div class="card-body p-24">
                                <div class="row">
                                    <?php foreach ($productMaterials as $material): ?>
                                        <div class="col-md-6 col-lg-4 mb-16">
                                            <div class="card border h-100 bg-base">
                                                <div class="card-body p-20">
                                                    <div class="d-flex align-items-start">
                                                        <div class="me-12 mt-2">
                                                            <?php if ($material['type'] === 'file'): ?>
                                                                <i class="ri-file-line text-primary" style="font-size: 1.5rem;"></i>
                                                            <?php else: ?>
                                                                <i class="ri-link text-success" style="font-size: 1.5rem;"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-8 fw-semibold"><?= htmlspecialchars($material['name']) ?></h6>
                                                            <small class="text-secondary-light d-block mb-12">
                                                                <?= $material['type'] === 'file' ? 'Arquivo' : 'Link Externo' ?>
                                                            </small>
                                                            
                                                            <?php if ($material['is_released']): ?>
                                                                <!-- Material liberado -->
                                                                <?php if ($material['type'] === 'file'): ?>
                                                                    <a href="<?= htmlspecialchars($material['file_path']) ?>" 
                                                                       class="btn btn-sm btn-outline-primary w-100" download>
                                                                        <i class="ri-download-line me-1"></i>
                                                                        Baixar Material
                                                                    </a>
                                                                <?php else: ?>
                                                                    <a href="<?= htmlspecialchars($material['external_url']) ?>" 
                                                                       class="btn btn-sm btn-outline-success w-100" target="_blank">
                                                                        <i class="ri-external-link-line me-1"></i>
                                                                        Acessar Link
                                                                    </a>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <!-- Material bloqueado -->
                                                                <div class="alert alert-warning p-12 mb-0">
                                                                    <div class="d-flex align-items-center">
                                                                        <i class="ri-time-line me-2"></i>
                                                                        <div>
                                                                            <small class="fw-semibold d-block">Disponível em <?= $material['days_remaining'] ?> dias</small>
                                                                            <small class="text-muted"><?= $material['release_date'] ?></small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Downloads recentes do usuário -->
                <?php if (!empty($userDownloads)): ?>
                <div class="row mt-80">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24">
                                <h3 class="mb-0">
                                    <i class="ri-download-line me-2"></i>
                                    Seus Downloads Recentes
                                </h3>
                            </div>
                            <div class="card-body p-24">
                                <div class="row">
                                    <?php foreach ($userDownloads as $download): ?>
                                    <div class="col-md-6 col-lg-4 mb-24">
                                        <div class="card border h-100 bg-base">
                                            <?php if (!empty($download['image_path']) && file_exists($download['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($download['image_path']) ?>" 
                                                 class="card-img-top" alt="<?= htmlspecialchars($download['product_name']) ?>"
                                                 style="height: 200px; object-fit: cover;">
                                            <?php else: ?>
                                            <div class="card-img-top bg-neutral-100 d-flex align-items-center justify-content-center" style="height: 200px;">
                                                <i class="ri-file-text-line text-secondary-light" style="font-size: 3rem;"></i>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="card-body p-20">
                                                <h6 class="card-title mb-8 fw-semibold"><?= htmlspecialchars($download['product_name']) ?></h6>
                                                <small class="text-secondary-light d-block">
                                                    <i class="ri-time-line me-1"></i>
                                                    Baixado em <?= date('d/m/Y H:i', strtotime($download['downloaded_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Modal de Vídeo Principal (Simples) -->
        <div class="modal fade" id="mainVideoModal" tabindex="-1" aria-labelledby="mainVideoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content radius-16 bg-base">
                    <div class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-base">
                        <h5 class="modal-title text-lg fw-semibold text-primary-light" id="mainVideoModalLabel">Vídeo de Apresentação</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="video-container" style="position: relative; width: 100%; height: 0; padding-bottom: 56.25%; margin-bottom: 0; border-radius: 0; overflow: hidden;">
                            <iframe id="mainVideoIframe" 
                                    src="" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;">
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Vídeo Avançado (Para Aulas) -->
        <div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content radius-16 bg-base">
                    <div class="modal-header py-16 px-24 border border-top-0 border-start-0 border-end-0 bg-base">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <div>
                                <h5 class="modal-title text-lg fw-semibold mb-0 text-primary-light" id="videoModalLabel">Assistir Aula</h5>
                                <small class="text-secondary-light" id="videoModalSubtitle">Aula 1 de <?= count($productVideos) ?></small>
                            </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    </div>
                    
                    <!-- Barra de Progresso -->
                    <div class="px-24 py-12 border-bottom bg-base">
                        <div class="d-flex align-items-center justify-content-between mb-8">
                            <small class="text-secondary-light fw-medium">Progresso do Curso</small>
                            <small class="text-secondary-light" id="progressText">0 de <?= count($productVideos) ?> aulas</small>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" id="progressBar" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <div class="modal-body p-0">
                        <div class="video-container" style="position: relative; width: 100%; height: 0; padding-bottom: 56.25%; margin-bottom: 0; border-radius: 0; overflow: hidden;">
                            <iframe id="videoIframe" 
                                    src="" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;">
                            </iframe>
                        </div>
                    </div>
                    
                    <!-- Seção de Interação (Curtidas e Comentários) -->
                    <div class="px-24 py-16 border-top bg-base">
                        <div class="row">
                            <!-- Curtidas -->
                            <div class="col-12 mb-16">
                                <div class="d-flex align-items-center gap-12">
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="likeVideoBtn">
                                        <i class="ri-heart-line me-1"></i>
                                        <span id="likeText">Curtir</span>
                                    </button>
                                    <span class="text-sm text-secondary-light" id="likeCount">0 curtidas</span>
                                </div>
                            </div>
                            
                            <!-- Comentários -->
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between mb-12">
                                    <h6 class="mb-0 text-primary-light">Comentários</h6>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="toggleCommentsBtn">
                                        <i class="ri-chat-3-line me-1"></i>
                                        <span id="commentCount">0</span>
                                    </button>
                                </div>
                                
                                <!-- Formulário de Comentário -->
                                <div id="commentForm" style="display: none;">
                                    <div class="mb-12">
                                        <textarea class="form-control" id="commentText" rows="3" placeholder="Deixe seu comentário sobre esta aula..." maxlength="500"></textarea>
                                        <small class="text-secondary-light">Máximo 500 caracteres</small>
                                    </div>
                                    <div class="d-flex gap-8">
                                        <button type="button" class="btn btn-primary btn-sm" id="submitCommentBtn">
                                            <i class="ri-send-plane-line me-1"></i>
                                            Comentar
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelCommentBtn">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Lista de Comentários -->
                                <div id="commentsList" style="display: none;">
                                    <div class="mt-16">
                                        <div id="commentsContainer">
                                            <!-- Comentários serão carregados aqui -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Controles do Modal -->
                    <div class="modal-footer py-16 px-24 border-top bg-base">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <button type="button" class="btn btn-outline-secondary" id="prevVideoBtn" disabled>
                                <i class="ri-arrow-left-line me-2"></i>
                                Aula Anterior
                            </button>
                            
                            <div class="d-flex align-items-center gap-3">
                                <button type="button" class="btn btn-success" id="markCompleteBtn">
                                    <i class="ri-check-line me-2"></i>
                                    Marcar como Finalizada
                                </button>
                                <button type="button" class="btn btn-primary" id="nextVideoBtn" disabled>
                                    Próxima Aula
                                    <i class="ri-arrow-right-line ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include './partials/footer.php' ?>
    </main>

    <?php include './partials/scripts.php' ?>
    
    <style>
        /* Estilos para o modal de vídeo */
        .video-container {
            background: #000;
        }
        
        /* Responsividade para o modal */
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 1rem;
            }
        }
        
        /* Corrigir problema do modal escuro */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5) !important;
        }
        
        .modal-backdrop.show {
            opacity: 0.5 !important;
        }
        
        /* Garantir que o modal seja visível */
        .modal.show {
            display: block !important;
        }
        
        .modal.show .modal-dialog {
            transform: none !important;
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variáveis globais
            let currentVideoIndex = 0;
            let currentVideoId = null;
            let videos = [];
            let lessonProgress = {};
            const productId = <?= $productData['id'] ?>;
            
            // Carregar progresso das aulas
            loadLessonProgress();
            
            // Função para abrir modal de vídeo principal (sem controles de aula)
            window.openMainVideoModal = function(videoUrl, title) {
                // Extrair código do vídeo da URL do YouTube
                const videoCode = extractVideoCode(videoUrl);
                
                if (videoCode) {
                    // Criar URL do iframe do YouTube com parâmetros profissionais
                    const embedUrl = `https://www.youtube.com/embed/${videoCode}?rel=0&modestbranding=1&showinfo=0&autoplay=1`;
                    
                    // Atualizar iframe e título
                    const iframe = document.getElementById('mainVideoIframe');
                    const modalTitle = document.getElementById('mainVideoModalLabel');
                    
                    iframe.src = embedUrl;
                    modalTitle.textContent = title;
                    
                    // Abrir modal
                    const modal = new bootstrap.Modal(document.getElementById('mainVideoModal'));
                    modal.show();
                    
                } else {
                    // Se não conseguir extrair o código, abrir em nova aba
                    window.open(videoUrl, '_blank');
                }
            }
            
            // Função para abrir modal de vídeo de aula (com controles)
            window.openVideoModal = function(videoUrl, title, videoId, videoIndex) {
                currentVideoIndex = videoIndex;
                currentVideoId = videoId;
                
                // Extrair código do vídeo da URL do YouTube
                const videoCode = extractVideoCode(videoUrl);
                
                if (videoCode) {
                    // Criar URL do iframe do YouTube com parâmetros profissionais
                    const embedUrl = `https://www.youtube.com/embed/${videoCode}?rel=0&modestbranding=1&showinfo=0&autoplay=1`;
                    
                    // Atualizar iframe e título
                    const iframe = document.getElementById('videoIframe');
                    const modalTitle = document.getElementById('videoModalLabel');
                    const modalSubtitle = document.getElementById('videoModalSubtitle');
                    
                    iframe.src = embedUrl;
                    modalTitle.textContent = title;
                    modalSubtitle.textContent = `Aula ${videoIndex + 1} de ${videos.length}`;
                    
                    // Atualizar controles de navegação
                    updateNavigationControls();
                    
                    // Abrir modal
                    const modal = new bootstrap.Modal(document.getElementById('videoModal'));
                    modal.show();
                    
                } else {
                    // Se não conseguir extrair o código, abrir em nova aba
                    window.open(videoUrl, '_blank');
                }
            }
            
            function extractVideoCode(url) {
                if (!url) return null;
                
                // Se já for apenas o código (sem URL completa), retornar diretamente
                if (!url.includes('youtube.com') && !url.includes('youtu.be')) {
                    return url;
                }
                
                // Padrões para extrair o código do vídeo do YouTube
                const patterns = [
                    // youtube.com/watch?v=CODE
                    /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/,
                    // youtube.com/watch?param=value&v=CODE
                    /youtube\.com\/watch\?.*v=([^&\n?#]+)/,
                    // youtu.be/CODE
                    /youtu\.be\/([^&\n?#]+)/
                ];
                
                for (let pattern of patterns) {
                    const match = url.match(pattern);
                    if (match && match[1]) {
                        return match[1];
                    }
                }
                return null;
            }
            
            // Carregar progresso das aulas
            function loadLessonProgress() {
                console.log('Carregando progresso das aulas para produto:', productId);
                fetch(`ajax/lesson_progress.php?product_id=${productId}`)
                    .then(response => {
                        console.log('Resposta recebida:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Dados recebidos:', data);
                        if (data.success) {
                            videos = data.videos;
                            lessonProgress = data.progress;
                            updateProgressDisplay();
                            updateVideoListDisplay();
                        } else {
                            console.error('Erro na API:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao carregar progresso:', error);
                    });
            }
            
            // Atualizar exibição do progresso
            function updateProgressDisplay() {
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');
                const courseProgressBar = document.getElementById('courseProgressBar');
                const courseProgressText = document.getElementById('courseProgressText');
                
                if (progressBar && progressText) {
                    progressBar.style.width = `${lessonProgress.percentage}%`;
                    progressText.textContent = `${lessonProgress.completed} de ${lessonProgress.total} aulas`;
                }
                
                if (courseProgressBar && courseProgressText) {
                    courseProgressBar.style.width = `${lessonProgress.percentage}%`;
                    courseProgressText.textContent = `${lessonProgress.percentage}% concluído`;
                }
            }
            
            // Atualizar exibição da lista de vídeos
            function updateVideoListDisplay() {
                videos.forEach((video, index) => {
                    const videoItem = document.querySelector(`[data-video-id="${video.id}"]`);
                    if (videoItem) {
                        const completedIndicator = videoItem.querySelector('.video-completed-indicator');
                        const videoNumber = videoItem.querySelector('.video-number');
                        const watchBtn = videoItem.querySelector('.watch-btn');
                        const btnText = videoItem.querySelector('.btn-text');
                        
                        if (video.is_completed) {
                            completedIndicator.style.display = 'flex';
                            videoNumber.classList.remove('bg-primary');
                            videoNumber.classList.add('bg-success');
                            btnText.textContent = 'Assistir Novamente';
                        } else {
                            completedIndicator.style.display = 'none';
                            videoNumber.classList.remove('bg-success');
                            videoNumber.classList.add('bg-primary');
                            btnText.textContent = 'Assistir';
                        }
                    }
                });
            }
            
            // Atualizar controles de navegação
            function updateNavigationControls() {
                const prevBtn = document.getElementById('prevVideoBtn');
                const nextBtn = document.getElementById('nextVideoBtn');
                const markCompleteBtn = document.getElementById('markCompleteBtn');
                
                // Botão anterior
                prevBtn.disabled = currentVideoIndex === 0;
                
                // Botão próximo
                nextBtn.disabled = currentVideoIndex >= videos.length - 1;
                
                // Botão marcar como finalizada
                const currentVideo = videos[currentVideoIndex];
                if (currentVideo && currentVideo.is_completed) {
                    markCompleteBtn.innerHTML = '<i class="ri-check-line me-2"></i>Finalizada';
                    markCompleteBtn.classList.remove('btn-success');
                    markCompleteBtn.classList.add('btn-outline-success');
                    markCompleteBtn.disabled = true;
                } else {
                    markCompleteBtn.innerHTML = '<i class="ri-check-line me-2"></i>Marcar como Finalizada';
                    markCompleteBtn.classList.remove('btn-outline-success');
                    markCompleteBtn.classList.add('btn-success');
                    markCompleteBtn.disabled = false;
                }
            }
            
            // Marcar aula como finalizada
            document.getElementById('markCompleteBtn').addEventListener('click', function() {
                if (!currentVideoId) return;
                
                console.log('Marcando aula como finalizada:', currentVideoId, 'Produto:', productId);
                
                const btn = this;
                const originalText = btn.innerHTML;
                
                btn.innerHTML = '<i class="ri-loader-4-line me-2"></i>Salvando...';
                btn.disabled = true;
                
                fetch('ajax/lesson_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        video_id: currentVideoId,
                        product_id: productId
                    })
                })
                .then(response => {
                    console.log('Resposta da API:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Dados da API:', data);
                    if (data.success) {
                        // Atualizar progresso local
                        lessonProgress = data.progress;
                        videos[currentVideoIndex].is_completed = true;
                        
                        // Atualizar exibições
                        updateProgressDisplay();
                        updateVideoListDisplay();
                        updateNavigationControls();
                        
                        // Mostrar próxima aula se disponível
                        if (data.next_video) {
                            setTimeout(() => {
                                const nextIndex = currentVideoIndex + 1;
                                const nextVideo = videos[nextIndex];
                                if (nextVideo) {
                                    openVideoModal(nextVideo.youtube_url, nextVideo.title, nextVideo.id, nextIndex);
                                }
                            }, 1500);
                        }
                        
                        // Mostrar notificação de sucesso
                        showNotification('Aula marcada como finalizada!', 'success');
                        
                        // Garantir que o backdrop seja removido
                        setTimeout(() => {
                            const backdrops = document.querySelectorAll('.modal-backdrop');
                            backdrops.forEach(backdrop => backdrop.remove());
                            document.body.classList.remove('modal-open');
                            document.body.style.overflow = '';
                            document.body.style.paddingRight = '';
                        }, 100);
                    } else {
                        showNotification(data.message || 'Erro ao salvar progresso', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showNotification('Erro ao salvar progresso', 'error');
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            });
            
            // Navegação entre aulas
            document.getElementById('nextVideoBtn').addEventListener('click', function() {
                if (currentVideoIndex < videos.length - 1) {
                    const nextVideo = videos[currentVideoIndex + 1];
                    openVideoModal(nextVideo.youtube_url, nextVideo.title, nextVideo.id, currentVideoIndex + 1);
                }
            });
            
            document.getElementById('prevVideoBtn').addEventListener('click', function() {
                if (currentVideoIndex > 0) {
                    const prevVideo = videos[currentVideoIndex - 1];
                    openVideoModal(prevVideo.youtube_url, prevVideo.title, prevVideo.id, currentVideoIndex - 1);
                }
            });
            
            // Função para mostrar notificações
            function showNotification(message, type = 'info') {
                // Criar elemento de notificação
                const notification = document.createElement('div');
                notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(notification);
                
                // Remover após 5 segundos
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 5000);
            }
            
            // Limpar iframe quando modal de aula for fechado
            document.getElementById('videoModal').addEventListener('hidden.bs.modal', function () {
                const iframe = document.getElementById('videoIframe');
                iframe.src = '';
                
                // Forçar remoção do backdrop
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                
                // Remover classe modal-open do body
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            });
            
            // Pausar vídeo quando modal de aula for fechado
            document.getElementById('videoModal').addEventListener('hide.bs.modal', function () {
                const iframe = document.getElementById('videoIframe');
                try {
                    iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
                } catch (e) {
                    iframe.src = '';
                }
            });
            
            // Limpar iframe quando modal principal for fechado
            document.getElementById('mainVideoModal').addEventListener('hidden.bs.modal', function () {
                const iframe = document.getElementById('mainVideoIframe');
                iframe.src = '';
                
                // Forçar remoção do backdrop
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                
                // Remover classe modal-open do body
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            });
            
            // Pausar vídeo quando modal principal for fechado
            document.getElementById('mainVideoModal').addEventListener('hide.bs.modal', function () {
                const iframe = document.getElementById('mainVideoIframe');
                try {
                    iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
                } catch (e) {
                    iframe.src = '';
                }
            });
        });

        // Funcionalidade de Favoritar
        document.addEventListener('DOMContentLoaded', function() {
            const favoriteBtn = document.getElementById('favoriteBtn');
            const favoriteIcon = document.getElementById('favoriteIcon');
            const favoriteText = document.getElementById('favoriteText');
            
            if (favoriteBtn && !favoriteBtn.hasAttribute('data-listener-added')) {
                const productId = favoriteBtn.dataset.productId;
                
                // Marcar que o listener foi adicionado
                favoriteBtn.setAttribute('data-listener-added', 'true');
                
                // Verificar status inicial do favorito
                checkFavoriteStatus(productId);
                
                // Adicionar evento de clique
                favoriteBtn.addEventListener('click', function() {
                    toggleFavorite(productId);
                });
            }
        });

        function checkFavoriteStatus(productId) {
            fetch(`ajax/check_favorite.php?product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateFavoriteUI(data.favorited);
                    }
                })
                .catch(error => {
                    console.error('Erro ao verificar favorito:', error);
                });
        }

        function toggleFavorite(productId) {
            const favoriteBtn = document.getElementById('favoriteBtn');
            const favoriteIcon = document.getElementById('favoriteIcon');
            const favoriteText = document.getElementById('favoriteText');
            
            // Desabilitar botão durante a requisição
            favoriteBtn.disabled = true;
            
            fetch('ajax/favorite_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin', // Garantir que cookies sejam enviados
                body: JSON.stringify({
                    action: 'toggle',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateFavoriteUI(data.favorited);
                    
                    // Mostrar notificação
                    showNotification(data.message, data.favorited ? 'success' : 'info');
                } else {
                    showNotification(data.message || 'Erro ao favoritar produto', 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao favoritar:', error);
                showNotification('Erro ao favoritar produto', 'error');
            })
            .finally(() => {
                favoriteBtn.disabled = false;
            });
        }

        function updateFavoriteUI(isFavorited) {
            const favoriteBtn = document.getElementById('favoriteBtn');
            const favoriteIcon = document.getElementById('favoriteIcon');
            const favoriteText = document.getElementById('favoriteText');
            
            if (isFavorited) {
                favoriteBtn.className = 'btn btn-danger-600 radius-8 px-20 py-11 d-flex align-items-center gap-2';
                favoriteIcon.className = 'ri-heart-fill';
                favoriteText.textContent = 'Favoritado';
            } else {
                favoriteBtn.className = 'btn btn-outline-danger-600 radius-8 px-20 py-11 d-flex align-items-center gap-2';
                favoriteIcon.className = 'ri-heart-line';
                favoriteText.textContent = 'Favoritar';
            }
        }

        function showNotification(message, type = 'info') {
            // Criar elemento de notificação
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Remover automaticamente após 3 segundos
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }

        // ===== FUNÇÕES DE INTERAÇÃO COM VÍDEOS =====
        
        let currentVideoId = null;
        let videoLiked = false;
        
        // Inicializar interação quando modal de vídeo abrir
        document.getElementById('videoModal').addEventListener('shown.bs.modal', function () {
            console.log('Modal de vídeo aberto, currentVideoId:', currentVideoId);
            if (currentVideoId) {
                // Aguardar um pouco para garantir que o modal esteja totalmente renderizado
                setTimeout(() => {
                    loadVideoInteraction(currentVideoId);
                }, 200);
            }
        });
        
        // Carregar interação do vídeo
        function loadVideoInteraction(videoId) {
            console.log('Carregando interação para vídeo:', videoId);
            currentVideoId = videoId;
            
            // Carregar status de curtida
            fetch(`ajax/video_like.php?video_id=${videoId}`)
                .then(response => {
                    console.log('Resposta curtidas:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Dados curtidas:', data);
                    if (data.success) {
                        updateLikeUI(data.liked, data.total_likes);
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar curtidas:', error);
                });
            
            // Carregar comentários
            loadComments(videoId);
        }
        
        // Atualizar UI de curtidas
        function updateLikeUI(liked, totalLikes) {
            videoLiked = liked;
            const likeBtn = document.getElementById('likeVideoBtn');
            const likeText = document.getElementById('likeText');
            const likeCount = document.getElementById('likeCount');
            const likeIcon = likeBtn.querySelector('i');
            
            if (liked) {
                likeBtn.className = 'btn btn-danger btn-sm';
                likeIcon.className = 'ri-heart-fill me-1';
                likeText.textContent = 'Curtido';
            } else {
                likeBtn.className = 'btn btn-outline-danger btn-sm';
                likeIcon.className = 'ri-heart-line me-1';
                likeText.textContent = 'Curtir';
            }
            
            likeCount.textContent = `${totalLikes} curtida${totalLikes !== 1 ? 's' : ''}`;
        }
        
        // Inicializar event listeners quando DOM estiver pronto
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM carregado, inicializando event listeners...');
            
            // Curtir/descurtir vídeo
            const likeBtn = document.getElementById('likeVideoBtn');
            if (likeBtn) {
                console.log('Botão de curtir encontrado, adicionando event listener...');
                likeBtn.addEventListener('click', function() {
                    console.log('Botão de curtir clicado!', 'currentVideoId:', currentVideoId);
                    if (!currentVideoId) {
                        console.log('Erro: currentVideoId não definido');
                        return;
                    }
                    
                    const action = videoLiked ? 'unlike' : 'like';
                    console.log('Enviando ação:', action, 'para vídeo:', currentVideoId);
                    
                    const formData = new FormData();
                    formData.append('video_id', currentVideoId);
                    formData.append('action', action);
                    
                    fetch('ajax/video_like.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Resposta recebida:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Dados recebidos:', data);
                        if (data.success) {
                            updateLikeUI(data.liked, data.total_likes);
                            showNotification(data.message, 'success');
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao curtir:', error);
                        showNotification('Erro ao curtir vídeo', 'error');
                    });
                });
            } else {
                console.log('Erro: Botão de curtir não encontrado!');
            }
        });
        
        // Carregar comentários
        function loadComments(videoId) {
            fetch(`ajax/video_comments.php?video_id=${videoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateCommentsUI(data.comments, data.total);
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar comentários:', error);
                });
        }
        
        // Atualizar UI de comentários
        function updateCommentsUI(comments, total) {
            const commentCount = document.getElementById('commentCount');
            const commentsContainer = document.getElementById('commentsContainer');
            
            commentCount.textContent = total;
            
            if (comments.length === 0) {
                commentsContainer.innerHTML = '<p class="text-secondary-light text-center py-16">Nenhum comentário ainda. Seja o primeiro!</p>';
            } else {
                commentsContainer.innerHTML = comments.map(comment => `
                    <div class="d-flex gap-12 mb-16 p-12 border radius-8 bg-neutral-50">
                        <img src="${comment.avatar_url}" alt="${comment.user_name}" class="w-40-px h-40-px rounded-circle object-fit-cover">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-8 mb-4">
                                <strong class="text-sm text-primary-light">${comment.user_name}</strong>
                                <small class="text-secondary-light">${comment.created_at_formatted}</small>
                            </div>
                            <p class="text-sm text-secondary-light mb-0">${comment.comment}</p>
                        </div>
                    </div>
                `).join('');
            }
        }
        
        // Adicionar event listeners restantes quando DOM estiver pronto
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle comentários
            document.getElementById('toggleCommentsBtn').addEventListener('click', function() {
                const commentForm = document.getElementById('commentForm');
                const commentsList = document.getElementById('commentsList');
                
                if (commentsList.style.display === 'none') {
                    commentsList.style.display = 'block';
                    commentForm.style.display = 'block';
                } else {
                    commentsList.style.display = 'none';
                    commentForm.style.display = 'none';
                }
            });
            
            // Cancelar comentário
            document.getElementById('cancelCommentBtn').addEventListener('click', function() {
                document.getElementById('commentText').value = '';
                document.getElementById('commentForm').style.display = 'none';
            });
            
            // Enviar comentário
            document.getElementById('submitCommentBtn').addEventListener('click', function() {
                const commentText = document.getElementById('commentText').value.trim();
                
                console.log('Enviando comentário:', {commentText, currentVideoId});
                
                if (!commentText) {
                    showNotification('Digite um comentário', 'error');
                    return;
                }
                
                if (!currentVideoId) {
                    console.log('Erro: currentVideoId não definido para comentário');
                    return;
                }
                
                const formData = new FormData();
                formData.append('video_id', currentVideoId);
                formData.append('comment', commentText);
                
                console.log('FormData criado:', {video_id: currentVideoId, comment: commentText});
                
                fetch('ajax/video_comments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('commentText').value = '';
                        showNotification(data.message, 'success');
                        loadComments(currentVideoId); // Recarregar comentários
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro ao comentar:', error);
                    showNotification('Erro ao comentar', 'error');
                });
            });
        });
        
        // Sobrescrever função openVideoModal para carregar interação
        // Aguardar um pouco para garantir que a função original esteja definida
        setTimeout(() => {
            if (window.openVideoModal) {
                const originalOpenVideoModal = window.openVideoModal;
                window.openVideoModal = function(videoUrl, title, videoId, videoIndex) {
                    console.log('openVideoModal chamada:', {videoUrl, title, videoId, videoIndex});
                    
                    // Definir currentVideoId ANTES de chamar a função original
                    currentVideoId = videoId;
                    
                    originalOpenVideoModal(videoUrl, title, videoId, videoIndex);
                };
                console.log('Função openVideoModal sobrescrita com sucesso');
            } else {
                console.log('Erro: Função openVideoModal não encontrada para sobrescrever');
            }
        }, 100);
    </script>
    
    <?php
    // Log final para confirmar que o script foi executado completamente
    saveLog("Script produto.php executado completamente - HTML renderizado com sucesso");
    ?>

</body>
</html>
