<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/SiteConfig.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$payment_id = $_GET['payment_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;

// Buscar dados do produto
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

// Inicializar classes necessárias para os partials
$auth = new Auth($pdo);
$siteConfig = new SiteConfig($pdo);
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include '../partials/head.php' ?>

<body>

    <?php include '../partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include '../partials/navbar.php' ?>

        <!-- Breadcrumb -->
        <div class="dashboard-main-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
                <h6 class="fw-semibold mb-0">Pagamento Confirmado</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="../produtos.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Produtos
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">
                        <a href="../produto.php?id=<?= $product_id ?>" class="hover-text-primary">
                            <?= htmlspecialchars($product['name']) ?>
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Pagamento Confirmado</li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Pagamento Confirmado!</h1>
                       <p class="text-lg text-neutral-600 mb-0">
                           Seu pagamento foi processado com sucesso
                       </p>
                   </div>
               </div>
           </div>
       </section>

       <!-- Sucesso -->
       <section class="py-80">
           <div class="container">
               <div class="row justify-content-center">
                   <div class="col-lg-6">
                       <div class="card border-0 shadow-sm">
                           <div class="card-body p-40 text-center">
                               <div class="mb-32">
                                   <i class="ri-check-line text-success" style="font-size: 4rem;"></i>
                               </div>
                               
                               <h3 class="h4 mb-16">Pagamento Aprovado!</h3>
                               <p class="text-neutral-600 mb-32">
                                   Seu pagamento foi processado com sucesso. Você já tem acesso ao produto.
                               </p>
                               
                               <?php if ($product): ?>
                               <div class="alert alert-success mb-32">
                                   <h5 class="mb-8"><?= htmlspecialchars($product['name']) ?></h5>
                                   <p class="mb-0 small"><?= htmlspecialchars($product['short_description']) ?></p>
                               </div>
                               <?php endif; ?>
                               
                               <div class="d-grid gap-3">
                                   <a href="../produto.php?id=<?= $product_id ?>" class="btn btn-primary">
                                       <i class="ri-download-line me-2"></i>
                                       Acessar Produto
                                   </a>
                                   
                                   <a href="../produtos.php" class="btn btn-outline-secondary">
                                       <i class="ri-store-line me-2"></i>
                                       Ver Mais Produtos
                                   </a>
                               </div>
                               
                               <div class="mt-32">
                                   <small class="text-neutral-600">
                                       <i class="ri-information-line me-1"></i>
                                       ID da transação: <?= $payment_id ?>
                                   </small>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
           </div>
       </section>
   </main>

   <?php include '../partials/scripts.php' ?>
</body>
</html>
