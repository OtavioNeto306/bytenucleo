<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Product.php';
require_once '../includes/Subscription.php';
require_once '../includes/Payment.php';

// Verificar se o usuário está logado e tem permissão
$auth = new Auth($pdo);
if (!$auth->isLoggedIn() || !$auth->hasPermission('view_reports')) {
    header('Location: /login.php');
    exit;
}

// Instanciar classes
$product = new Product($pdo);
$subscription = new Subscription($pdo);
$payment = new Payment($pdo);

// Buscar estatísticas
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalDownloads = $pdo->query("SELECT COUNT(*) FROM product_downloads")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalProductPurchases = $pdo->query("SELECT COUNT(*) FROM product_purchases")->fetchColumn();
$totalRevenue = $pdo->query("SELECT SUM(total_amount) FROM orders")->fetchColumn() ?? 0;
$productRevenue = $pdo->query("SELECT SUM(amount) FROM product_purchases WHERE status = 'completed'")->fetchColumn() ?? 0;
$totalRevenueCombined = $totalRevenue + $productRevenue;

// Usuários por mês (últimos 6 meses)
$usersByMonth = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $usersByMonth[] = $stmt->fetchColumn();
}

// Downloads por mês (últimos 6 meses)
$downloadsByMonth = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_downloads WHERE DATE_FORMAT(downloaded_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $downloadsByMonth[] = $stmt->fetchColumn();
}

// Compras de produtos por mês (últimos 6 meses)
$productPurchasesByMonth = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_purchases WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $productPurchasesByMonth[] = $stmt->fetchColumn();
}

// Receita de produtos por mês (últimos 6 meses)
$productRevenueByMonth = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM product_purchases WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status = 'completed'");
    $stmt->execute([$month]);
    $productRevenueByMonth[] = $stmt->fetchColumn() ?? 0;
}

// Produtos mais baixados
$topProducts = $pdo->query("
    SELECT p.name, COUNT(d.id) as download_count 
    FROM products p 
    LEFT JOIN product_downloads d ON p.id = d.product_id 
    GROUP BY p.id 
    ORDER BY download_count DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Produtos mais vendidos (compras)
$topSoldProducts = $pdo->query("
    SELECT p.name, COUNT(pp.id) as purchase_count, SUM(pp.amount) as total_revenue
    FROM products p 
    LEFT JOIN product_purchases pp ON p.id = pp.product_id 
    WHERE pp.status = 'completed'
    GROUP BY p.id 
    ORDER BY purchase_count DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Pedidos por status
$ordersByStatus = $pdo->query("
    SELECT 'Total' as status, COUNT(*) as count 
    FROM orders
")->fetchAll(PDO::FETCH_ASSOC);

// Categorias com mais produtos
$categoriesWithProducts = $pdo->query("
    SELECT c.name, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id 
    ORDER BY product_count DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Usuários por plano
$usersByPlan = $pdo->query("
    SELECT sp.name as plan_name, COUNT(u.id) as user_count 
    FROM subscription_plans sp 
    LEFT JOIN users u ON sp.id = u.current_plan_id 
    GROUP BY sp.id 
    ORDER BY user_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Relatórios";
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
                        <h1 class="display-4 fw-bold mb-24">Relatórios e Estatísticas</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Visualize estatísticas e gráficos do sistema
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Conteúdo Principal -->
        <section class="py-80">
            <div class="container">

         <!-- Cards de Estatísticas -->
     <div class="row gy-4 mb-24">
         <div class="col-md-3">
             <div class="card border-0 shadow-sm h-100">
                 <div class="card-body p-24 d-flex flex-column">
                     <div class="d-flex align-items-center justify-content-between flex-grow-1">
                         <div>
                             <h6 class="text-neutral-600 fw-medium mb-8">Total de Usuários</h6>
                             <h4 class="fw-semibold mb-0"><?= number_format($totalUsers) ?></h4>
                         </div>
                         <div class="w-48-px h-48-px bg-primary-subtle rounded-circle d-flex justify-content-center align-items-center">
                             <i class="ri-user-line text-primary" style="font-size: 1.5rem;"></i>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
         
         <div class="col-md-3">
             <div class="card border-0 shadow-sm h-100">
                 <div class="card-body p-24 d-flex flex-column">
                     <div class="d-flex align-items-center justify-content-between flex-grow-1">
                         <div>
                             <h6 class="text-neutral-600 fw-medium mb-8">Total de Produtos</h6>
                             <h4 class="fw-semibold mb-0"><?= number_format($totalProducts) ?></h4>
                         </div>
                         <div class="w-48-px h-48-px bg-success-subtle rounded-circle d-flex justify-content-center align-items-center">
                             <iconify-icon icon="solar:box-outline" class="icon text-xxl text-success"></iconify-icon>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
         
         <div class="col-md-3">
             <div class="card border-0 shadow-sm h-100">
                 <div class="card-body p-24 d-flex flex-column">
                     <div class="d-flex align-items-center justify-content-between flex-grow-1">
                         <div>
                             <h6 class="text-neutral-600 fw-medium mb-8">Total de Downloads</h6>
                             <h4 class="fw-semibold mb-0"><?= number_format($totalDownloads) ?></h4>
                         </div>
                         <div class="w-48-px h-48-px bg-warning-subtle rounded-circle d-flex justify-content-center align-items-center">
                             <iconify-icon icon="solar:download-outline" class="icon text-xxl text-warning"></iconify-icon>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
         
         <div class="col-md-3">
             <div class="card border-0 shadow-sm h-100">
                 <div class="card-body p-24 d-flex flex-column">
                     <div class="d-flex align-items-center justify-content-between flex-grow-1">
                         <div>
                             <h6 class="text-neutral-600 fw-medium mb-8">Compras de Produtos</h6>
                             <h4 class="fw-semibold mb-0"><?= number_format($totalProductPurchases) ?></h4>
                         </div>
                         <div class="w-48-px h-48-px bg-info-subtle rounded-circle d-flex justify-content-center align-items-center">
                             <iconify-icon icon="solar:cart-outline" class="icon text-xxl text-info"></iconify-icon>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
     </div>

     <!-- Segunda linha de estatísticas -->
     <div class="row gy-4 mt-4">
         <div class="col-md-3">
             <div class="card border-0 shadow-sm h-100">
                 <div class="card-body p-24 d-flex flex-column">
                     <div class="d-flex align-items-center justify-content-between flex-grow-1">
                         <div>
                             <h6 class="text-neutral-600 fw-medium mb-8">Receita Total</h6>
                             <h4 class="fw-semibold mb-0">R$ <?= number_format($totalRevenueCombined, 2, ',', '.') ?></h4>
                             <small class="text-neutral-600">R$ <?= number_format($totalRevenue, 2, ',', '.') ?> assinaturas + R$ <?= number_format($productRevenue, 2, ',', '.') ?> produtos</small>
                         </div>
                         <div class="w-48-px h-48-px bg-success-subtle rounded-circle d-flex justify-content-center align-items-center">
                             <iconify-icon icon="solar:wallet-money-outline" class="icon text-xxl text-success"></iconify-icon>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
         
         <div class="col-md-3">
             <div class="card border-0 shadow-sm h-100">
                 <div class="card-body p-24 d-flex flex-column">
                     <div class="d-flex align-items-center justify-content-between flex-grow-1">
                         <div>
                             <h6 class="text-neutral-600 fw-medium mb-8">Pedidos de Assinatura</h6>
                             <h4 class="fw-semibold mb-0"><?= number_format($totalOrders) ?></h4>
                         </div>
                         <div class="w-48-px h-48-px bg-primary-subtle rounded-circle d-flex justify-content-center align-items-center">
                             <iconify-icon icon="solar:card-outline" class="icon text-xxl text-primary"></iconify-icon>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
         
         <div class="col-md-3">
             <div class="card border-0 shadow-sm h-100">
                 <div class="card-body p-24 d-flex flex-column">
                     <div class="d-flex align-items-center justify-content-between flex-grow-1">
                         <div>
                             <h6 class="text-neutral-600 fw-medium mb-8">Receita de Produtos</h6>
                             <h4 class="fw-semibold mb-0">R$ <?= number_format($productRevenue, 2, ',', '.') ?></h4>
                         </div>
                         <div class="w-48-px h-48-px bg-warning-subtle rounded-circle d-flex justify-content-center align-items-center">
                             <iconify-icon icon="solar:shop-outline" class="icon text-xxl text-warning"></iconify-icon>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
         
         <div class="col-md-3">
             <div class="card border-0 shadow-sm h-100">
                 <div class="card-body p-24 d-flex flex-column">
                     <div class="d-flex align-items-center justify-content-between flex-grow-1">
                         <div>
                             <h6 class="text-neutral-600 fw-medium mb-8">Receita de Assinaturas</h6>
                             <h4 class="fw-semibold mb-0">R$ <?= number_format($totalRevenue, 2, ',', '.') ?></h4>
                         </div>
                         <div class="w-48-px h-48-px bg-danger-subtle rounded-circle d-flex justify-content-center align-items-center">
                             <iconify-icon icon="solar:calendar-outline" class="icon text-xxl text-danger"></iconify-icon>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
     </div>

         <!-- Gráficos -->
     <div class="row gy-4 mt-5">
         <!-- Gráfico de Usuários por Mês -->
         <div class="col-md-6">
             <div class="card border-0 shadow-sm">
                 <div class="card-header bg-base py-16 px-24">
                     <h6 class="text-lg fw-semibold mb-0">Usuários Registrados por Mês</h6>
                 </div>
                 <div class="card-body p-24">
                     <div id="usersChart"></div>
                 </div>
             </div>
         </div>

         <!-- Gráfico de Downloads por Mês -->
         <div class="col-md-6">
             <div class="card border-0 shadow-sm">
                 <div class="card-header bg-base py-16 px-24">
                     <h6 class="text-lg fw-semibold mb-0">Downloads por Mês</h6>
                 </div>
                 <div class="card-body p-24">
                     <div id="downloadsChart"></div>
                 </div>
             </div>
         </div>
     </div>

     <!-- Segunda linha de gráficos -->
     <div class="row gy-4 mt-4">
         <!-- Gráfico de Compras de Produtos por Mês -->
         <div class="col-md-6">
             <div class="card border-0 shadow-sm">
                 <div class="card-header bg-base py-16 px-24">
                     <h6 class="text-lg fw-semibold mb-0">Compras de Produtos por Mês</h6>
                 </div>
                 <div class="card-body p-24">
                     <div id="productPurchasesChart"></div>
                 </div>
             </div>
         </div>

         <!-- Gráfico de Receita de Produtos por Mês -->
         <div class="col-md-6">
             <div class="card border-0 shadow-sm">
                 <div class="card-header bg-base py-16 px-24">
                     <h6 class="text-lg fw-semibold mb-0">Receita de Produtos por Mês</h6>
                 </div>
                 <div class="card-body p-24">
                     <div id="productRevenueChart"></div>
                 </div>
             </div>
         </div>

         <!-- Gráfico de Pedidos por Status -->
         <div class="col-md-6">
             <div class="card border-0 shadow-sm">
                 <div class="card-header bg-base py-16 px-24">
                     <h6 class="text-lg fw-semibold mb-0">Pedidos por Status</h6>
                 </div>
                 <div class="card-body p-24">
                     <div id="ordersChart"></div>
                 </div>
             </div>
         </div>

         <!-- Gráfico de Usuários por Plano -->
         <div class="col-md-6">
             <div class="card border-0 shadow-sm">
                 <div class="card-header bg-base py-16 px-24">
                     <h6 class="text-lg fw-semibold mb-0">Usuários por Plano</h6>
                 </div>
                 <div class="card-body p-24">
                     <div id="plansChart"></div>
                 </div>
             </div>
         </div>
     </div>

                   <!-- Tabelas de Dados -->
      <div class="row gy-4 mt-24">
          <!-- Produtos Mais Baixados -->
          <div class="col-md-6">
                                 <div class="card border-0 shadow-sm">
                       <div class="card-header bg-base py-16 px-24">
                           <h5 class="fw-semibold mb-0">Produtos Mais Baixados</h5>
                       </div>
                       <div class="card-body p-24">
                           <div class="table-responsive">
                               <table class="table bordered-table sm-table mb-0">
                              <thead>
                                  <tr>
                                      <th>Produto</th>
                                      <th class="text-end">Downloads</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php if (empty($topProducts)): ?>
                                  <tr>
                                      <td colspan="2" class="text-center py-40">
                                                                                     <i class="ri-download-line text-neutral-600" style="font-size: 3rem;"></i>
                                                                                     <h5 class="fw-semibold mt-16 mb-8">Nenhum download encontrado</h5>
                                                                                     <p class="text-neutral-600">Não há produtos com downloads registrados.</p>
                                      </td>
                                  </tr>
                                  <?php else: ?>
                                      <?php foreach ($topProducts as $product): ?>
                                      <tr>
                                          <td><?= htmlspecialchars($product['name']) ?></td>
                                          <td class="text-end"><?= number_format($product['download_count']) ?></td>
                                      </tr>
                                      <?php endforeach; ?>
                                  <?php endif; ?>
                              </tbody>
                          </table>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Produtos Mais Vendidos -->
          <div class="col-md-6">
              <div class="card border-0 shadow-sm">
                  <div class="card-header bg-base py-16 px-24">
                      <h5 class="fw-semibold mb-0">Produtos Mais Vendidos</h5>
                  </div>
                  <div class="card-body p-24">
                      <div class="table-responsive">
                          <table class="table bordered-table sm-table mb-0">
                              <thead>
                                  <tr>
                                      <th>Produto</th>
                                      <th class="text-end">Vendas</th>
                                      <th class="text-end">Receita</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php if (empty($topSoldProducts)): ?>
                                  <tr>
                                      <td colspan="3" class="text-center py-40">
                                          <i class="ri-shopping-cart-line text-neutral-600" style="font-size: 3rem;"></i>
                                          <h5 class="fw-semibold mt-16 mb-8">Nenhuma venda encontrada</h5>
                                          <p class="text-neutral-600">Não há produtos com vendas registradas.</p>
                                      </td>
                                  </tr>
                                  <?php else: ?>
                                      <?php foreach ($topSoldProducts as $product): ?>
                                      <tr>
                                          <td><?= htmlspecialchars($product['name']) ?></td>
                                          <td class="text-end"><?= number_format($product['purchase_count']) ?></td>
                                          <td class="text-end">R$ <?= number_format($product['total_revenue'], 2, ',', '.') ?></td>
                                      </tr>
                                      <?php endforeach; ?>
                                  <?php endif; ?>
                              </tbody>
                          </table>
                      </div>
                  </div>
              </div>
          </div>
      </div>

      <!-- Segunda linha de tabelas -->
      <div class="row gy-4 mt-4">
          <!-- Categorias com Mais Produtos -->
          <div class="col-md-6">
                                 <div class="card border-0 shadow-sm">
                       <div class="card-header bg-base py-16 px-24">
                           <h5 class="fw-semibold mb-0">Categorias com Mais Produtos</h5>
                       </div>
                       <div class="card-body p-24">
                           <div class="table-responsive">
                               <table class="table bordered-table sm-table mb-0">
                              <thead>
                                  <tr>
                                      <th>Categoria</th>
                                      <th class="text-end">Produtos</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php if (empty($categoriesWithProducts)): ?>
                                  <tr>
                                      <td colspan="2" class="text-center py-40">
                                                                                     <i class="ri-folder-line text-neutral-600" style="font-size: 3rem;"></i>
                                                                                     <h5 class="fw-semibold mt-16 mb-8">Nenhuma categoria encontrada</h5>
                                                                                     <p class="text-neutral-600">Não há categorias com produtos registrados.</p>
                                      </td>
                                  </tr>
                                  <?php else: ?>
                                      <?php foreach ($categoriesWithProducts as $category): ?>
                                      <tr>
                                          <td><?= htmlspecialchars($category['name']) ?></td>
                                          <td class="text-end"><?= number_format($category['product_count']) ?></td>
                                      </tr>
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

<script>
// Gráfico de Usuários por Mês
var usersOptions = {
    series: [{
        name: 'Usuários',
        data: <?= json_encode($usersByMonth) ?>
    }],
    chart: {
        type: 'area',
        height: 300,
        toolbar: {
            show: false
        }
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        curve: 'smooth',
        width: 2
    },
    colors: ['#3b82f6'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.3,
            stops: [0, 90, 100]
        }
    },
    xaxis: {
        categories: ['<?= date('M Y', strtotime('-5 months')) ?>', '<?= date('M Y', strtotime('-4 months')) ?>', '<?= date('M Y', strtotime('-3 months')) ?>', '<?= date('M Y', strtotime('-2 months')) ?>', '<?= date('M Y', strtotime('-1 month')) ?>', '<?= date('M Y') ?>']
    },
    tooltip: {
        theme: 'dark'
    }
};

// Gráfico de Downloads por Mês
var downloadsOptions = {
    series: [{
        name: 'Downloads',
        data: <?= json_encode($downloadsByMonth) ?>
    }],
    chart: {
        type: 'line',
        height: 300,
        toolbar: {
            show: false
        }
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        curve: 'smooth',
        width: 3
    },
    colors: ['#10b981'],
    xaxis: {
        categories: ['<?= date('M Y', strtotime('-5 months')) ?>', '<?= date('M Y', strtotime('-4 months')) ?>', '<?= date('M Y', strtotime('-3 months')) ?>', '<?= date('M Y', strtotime('-2 months')) ?>', '<?= date('M Y', strtotime('-1 month')) ?>', '<?= date('M Y') ?>']
    },
    tooltip: {
        theme: 'dark'
    }
};

// Gráfico de Compras de Produtos por Mês
var productPurchasesOptions = {
    series: [{
        name: 'Compras',
        data: <?= json_encode($productPurchasesByMonth) ?>
    }],
    chart: {
        type: 'line',
        height: 300,
        toolbar: {
            show: false
        }
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        curve: 'smooth',
        width: 3
    },
    colors: ['#f59e0b'],
    xaxis: {
        categories: ['<?= date('M Y', strtotime('-5 months')) ?>', '<?= date('M Y', strtotime('-4 months')) ?>', '<?= date('M Y', strtotime('-3 months')) ?>', '<?= date('M Y', strtotime('-2 months')) ?>', '<?= date('M Y', strtotime('-1 month')) ?>', '<?= date('M Y') ?>']
    },
    tooltip: {
        theme: 'dark'
    }
};

// Gráfico de Receita de Produtos por Mês
var productRevenueOptions = {
    series: [{
        name: 'Receita (R$)',
        data: <?= json_encode($productRevenueByMonth) ?>
    }],
    chart: {
        type: 'area',
        height: 300,
        toolbar: {
            show: false
        }
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        curve: 'smooth',
        width: 2
    },
    colors: ['#8b5cf6'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.3,
            stops: [0, 90, 100]
        }
    },
    xaxis: {
        categories: ['<?= date('M Y', strtotime('-5 months')) ?>', '<?= date('M Y', strtotime('-4 months')) ?>', '<?= date('M Y', strtotime('-3 months')) ?>', '<?= date('M Y', strtotime('-2 months')) ?>', '<?= date('M Y', strtotime('-1 month')) ?>', '<?= date('M Y') ?>']
    },
    tooltip: {
        theme: 'dark',
        y: {
            formatter: function (val) {
                return "R$ " + val.toFixed(2).replace('.', ',');
            }
        }
    }
};

// Gráfico de Pedidos por Status
var ordersData = <?= json_encode($ordersByStatus) ?>;
var ordersOptions = {
    series: ordersData.map(item => item.count),
    chart: {
        type: 'donut',
        height: 300
    },
    labels: ordersData.map(item => item.status),
    colors: ['#10b981', '#f59e0b', '#ef4444', '#6b7280'],
    legend: {
        position: 'bottom'
    },
    tooltip: {
        theme: 'dark'
    }
};

// Gráfico de Usuários por Plano
var plansData = <?= json_encode($usersByPlan) ?>;
var plansOptions = {
    series: plansData.map(item => item.user_count),
    chart: {
        type: 'pie',
        height: 300
    },
    labels: plansData.map(item => item.plan_name),
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
    legend: {
        position: 'bottom'
    },
    tooltip: {
        theme: 'dark'
    }
};

// Inicializar gráficos
document.addEventListener('DOMContentLoaded', function() {
    new ApexCharts(document.querySelector("#usersChart"), usersOptions).render();
    new ApexCharts(document.querySelector("#downloadsChart"), downloadsOptions).render();
    new ApexCharts(document.querySelector("#productPurchasesChart"), productPurchasesOptions).render();
    new ApexCharts(document.querySelector("#productRevenueChart"), productRevenueOptions).render();
    new ApexCharts(document.querySelector("#ordersChart"), ordersOptions).render();
    new ApexCharts(document.querySelector("#plansChart"), plansOptions).render();
});
</script>

                                   </div>
             </div>
         </section>

         <?php include '../partials/footer.php' ?>
     </main>

     <?php include '../partials/scripts.php' ?>

</body>
</html>
