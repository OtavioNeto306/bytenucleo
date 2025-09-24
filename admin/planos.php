
<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Subscription.php';

// Verificar se usuário está logado e tem permissão
$auth = new Auth($pdo);
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

// Verificar se tem permissão de admin
if (!$auth->isAdmin()) {
    header('Location: ../index.php');
    exit;
}

$subscription = new Subscription($pdo);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (isset($_POST['plan_id'])) {
                    $subscription->deletePlan($_POST['plan_id']);
                    header('Location: planos.php?success=deleted');
                    exit;
                }
                break;
        }
    }
}

// Obter todos os planos
$plans = $subscription->getAllPlans();

// Mensagens de sucesso
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Plano criado com sucesso!';
            break;
        case 'updated':
            $successMessage = 'Plano atualizado com sucesso!';
            break;
        case 'deleted':
            $successMessage = 'Plano excluído com sucesso!';
            break;
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
                 <div class="row align-items-center">
                     <div class="col-lg-8">
                         <h1 class="display-4 fw-bold mb-24">Gerenciar Planos</h1>
                                                   <p class="text-lg text-neutral-600 mb-0">
                              Crie e gerencie os planos de assinatura do sistema
                          </p>
                     </div>
                     <div class="col-lg-4 text-lg-end">
                         <a href="/admin/adicionar-plano" class="btn btn-primary">
                             <i class="ri-add-line me-2"></i>Adicionar Plano
                         </a>
                     </div>
                 </div>
             </div>
         </section>

         <!-- Conteúdo -->
         <section class="py-80">
             <div class="container">
                <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ri-check-line me-2"></i><?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Cards dos Planos -->
                <div class="row g-24">
                    <?php foreach ($plans as $plan): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card border h-100 bg-base">
                            <div class="card-body p-24">
                                <!-- Header do plano -->
                                <div class="d-flex justify-content-between align-items-start mb-16">
                                    <div>
                                                                                 <h5 class="card-title mb-4"><?= htmlspecialchars($plan['name']) ?></h5>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-primary"><?= htmlspecialchars($plan['slug']) ?></span>
                                            <?php if ($plan['status'] === 'active'): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="ri-more-2-fill"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-primary d-flex align-items-center gap-3" href="/admin/editar-plano?id=<?= $plan['id'] ?>">
                                                <i class="ri-edit-line me-2"></i>Editar
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-danger d-flex align-items-center gap-3" href="#" onclick="deletePlan(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['name']) ?>')">
                                                <i class="ri-delete-bin-line me-2"></i>Excluir
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                                                 <!-- Descrição -->
                                 <p class="text-neutral-600 mb-16"><?= htmlspecialchars($plan['description']) ?></p>
                                
                                <!-- Preço -->
                                <div class="mb-16">
                                                                         <span class="h4 mb-0">
                                        <?php if ($plan['price'] > 0): ?>
                                            R$ <?= number_format($plan['price'], 2, ',', '.') ?>
                                        <?php else: ?>
                                            <span class="text-success">Grátis</span>
                                        <?php endif; ?>
                                    </span>
                                                                         <small class="text-neutral-600 d-block">por <?= $plan['duration_days'] ?> dias</small>
                                </div>
                                
                                <!-- Limites -->
                                <div class="row g-2 mb-16">
                                    <div class="col-6">
                                                                                 <small class="text-neutral-600 d-block">Downloads</small>
                                         <span>
                                            <?= $plan['max_downloads'] == -1 ? 'Ilimitado' : $plan['max_downloads'] ?>
                                        </span>
                                    </div>
                                    <div class="col-6">
                                                                                 <small class="text-neutral-600 d-block">Produtos</small>
                                         <span>
                                            <?= $plan['max_products'] == -1 ? 'Ilimitado' : $plan['max_products'] ?>
                                        </span>
                                    </div>
                                </div>
                                
                                                                <!-- Features -->
                                <?php 
                                $features = json_decode($plan['features'], true);
                                if ($features && count($features) > 0): 
                                    $featureNames = $subscription->getFeatureDisplayNames($features);
                                ?>
                                <div class="mb-16">
                                    <small class="text-neutral-600 d-block mb-8">Recursos incluídos:</small>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($featureNames as $featureName): ?>
                                        <span class="badge bg-primary-50 text-primary"><?= htmlspecialchars($featureName) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                                                 <!-- Data de criação -->
                                 <small class="text-neutral-600">
                                    Criado em <?= date('d/m/Y', strtotime($plan['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($plans)): ?>
                <div class="text-center py-80">
                    <div class="mb-24">
                        <i class="ri-price-tag-3-line text-muted" style="font-size: 4rem;"></i>
                    </div>
                                         <h5 class="mb-8">Nenhum plano encontrado</h5>
                     <p class="text-neutral-600 mb-24">Crie seu primeiro plano de assinatura para começar.</p>
                    <a href="/admin/adicionar-plano" class="btn btn-primary">
                        <i class="ri-add-line me-2"></i>Criar Primeiro Plano
                    </a>
                </div>
                                 <?php endif; ?>
             </div>
         </section>
    
        <!-- Formulário para exclusão -->
        <form id="deleteForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="plan_id" id="deletePlanId">
        </form>
        
        <?php include '../partials/footer.php' ?>
    </main>

    <?php include '../partials/scripts.php' ?>
     
     <script>
     function deletePlan(planId, planName) {
         if (confirm(`Tem certeza que deseja excluir o plano "${planName}"? Esta ação não pode ser desfeita.`)) {
             document.getElementById('deletePlanId').value = planId;
             document.getElementById('deleteForm').submit();
         }
     }
     </script>
 </body>
 </html>
