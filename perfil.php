<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Product.php';
require_once 'includes/Subscription.php';

$auth = new Auth($pdo);
$product = new Product($pdo);
$subscription = new Subscription($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login');
    exit;
}

$user = $auth->getCurrentUser();
$hasSubscription = $auth->hasActiveSubscription();
$currentSubscription = $auth->getActiveSubscription();
$userDownloads = $product->getUserDownloads($_SESSION['user_id'], 10);

// Processar logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: index-membros.php');
    exit;
}
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
                        <h1 class="display-4 fw-bold mb-24">Meu Perfil</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Gerencie sua conta e visualize suas informações
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Informações do usuário -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <!-- Perfil -->
                    <div class="col-lg-4 mb-40">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32 text-center">
                                <div class="mb-24">
                                    <div class="avatar-preview" style="width: 120px; height: 120px; margin: 0 auto;">
                                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                            <div style="background-image: url('<?= htmlspecialchars($user['avatar']) ?>'); width: 100%; height: 100%; border-radius: 100%; background-size: cover; background-repeat: no-repeat; background-position: center; border: 1px solid var(--primary-600); box-shadow: 0px 2px 4px 0px rgba(0, 0, 0, 0.1);">
                                            </div>
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; border-radius: 100%; background-size: cover; background-repeat: no-repeat; background-position: center; border: 1px solid var(--primary-600); box-shadow: 0px 2px 4px 0px rgba(0, 0, 0, 0.1); background-color: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                                <i class="ri-user-line" style="font-size: 3rem; color: #667eea;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <h4 class="mb-8"><?= htmlspecialchars($user['name']) ?></h4>
                                <p class="text-secondary-light mb-16"><?= htmlspecialchars($user['email']) ?></p>
                                <div class="mb-24">
                                    <span class="badge bg-<?= $user['role_name'] === 'super_admin' ? 'danger' : ($user['role_name'] === 'admin' ? 'warning' : 'info') ?>">
                                        <?= htmlspecialchars($user['role_name']) ?>
                                    </span>
                                    <small class="text-secondary-light d-block mt-8"><?= htmlspecialchars($user['role_description']) ?></small>
                                </div>
                                
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="/editar-perfil" class="btn btn-outline-primary d-flex align-items-center">
                                        <i class="ri-edit-line me-8"></i>
                                        Editar Perfil
                                    </a>
                                    <?php if ($auth->isAdmin()): ?>
                                    <a href="/admin/usuarios" class="btn btn-outline-warning d-flex align-items-center">
                                        <i class="ri-settings-line me-8"></i>
                                        Administração
                                    </a>
                                    <?php endif; ?>
                                    <a href="/perfil?logout=1" class="btn btn-outline-danger d-flex align-items-center" 
                                       onclick="return confirm('Tem certeza que deseja sair?')">
                                        <i class="ri-logout-box-line me-8"></i>
                                        Sair
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status da assinatura -->
                    <div class="col-lg-8 mb-40">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32">
                                <h5 class="card-title mb-24">Status da Assinatura</h5>
                                
                                <?php if ($hasSubscription && $currentSubscription): ?>
                                    <?php if ($currentSubscription['status'] === 'cancelling'): ?>
                                        <div class="alert alert-warning" role="alert">
                                            <div class="d-flex align-items-center">
                                                <i class="ri-alert-line me-12" style="font-size: 1.5rem;"></i>
                                                <div>
                                                    <h6 class="alert-heading mb-8">Assinatura Cancelada</h6>
                                                    <p class="mb-0">
                                                        Plano: <strong><?= htmlspecialchars($currentSubscription['plan_name']) ?></strong><br>
                                                        Acesso até: <strong><?= date('d/m/Y', strtotime($currentSubscription['end_date'])) ?></strong>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-success" role="alert">
                                            <div class="d-flex align-items-center">
                                                <i class="ri-check-circle-line me-12" style="font-size: 1.5rem;"></i>
                                                <div>
                                                    <h6 class="alert-heading mb-8">Assinatura Ativa</h6>
                                                    <p class="mb-0">
                                                        Plano: <strong><?= htmlspecialchars($currentSubscription['plan_name']) ?></strong><br>
                                                        Válida até: <strong><?= date('d/m/Y', strtotime($currentSubscription['end_date'])) ?></strong>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-16">
                                            <div class="d-flex align-items-center">
                                                <i class="ri-calendar-line text-primary me-12"></i>
                                                                                            <div>
                                                <small class="text-secondary-light">Início</small><br>
                                                <strong><?= date('d/m/Y', strtotime($currentSubscription['start_date'])) ?></strong>
                                            </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-16">
                                            <div class="d-flex align-items-center">
                                                <i class="ri-time-line text-primary me-12"></i>
                                                <div>
                                                    <small class="text-secondary-light">Status</small><br>
                                                    <?php if ($currentSubscription['status'] === 'cancelling'): ?>
                                                        <strong class="text-warning">Cancelando</strong>
                                                    <?php else: ?>
                                                        <strong class="text-success">Ativa</strong>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap gap-2 mt-24">
                                        <?php if ($currentSubscription['status'] === 'cancelling'): ?>
                                            <a href="/planos" class="btn btn-primary d-inline-flex align-items-center">
                                                <i class="ri-add-circle-line me-2"></i>
                                                Assinar Novo Plano
                                            </a>
                                            <a href="/historico-pagamentos" class="btn btn-outline-secondary d-inline-flex align-items-center">
                                                <i class="ri-file-list-line me-2"></i>
                                                Histórico de Pagamentos
                                            </a>
                                        <?php else: ?>
                                            <a href="/planos" class="btn btn-outline-primary d-inline-flex align-items-center">
                                                <i class="ri-refresh-line me-2"></i>
                                                Trocar Plano
                                            </a>
                                            <a href="/cancelar-assinatura" class="btn btn-outline-danger d-inline-flex align-items-center">
                                                <i class="ri-close-circle-line me-2"></i>
                                                Cancelar Assinatura
                                            </a>
                                            <a href="/historico-pagamentos" class="btn btn-outline-secondary d-inline-flex align-items-center">
                                                <i class="ri-file-list-line me-2"></i>
                                                Histórico de Pagamentos
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning" role="alert">
                                        <div class="d-flex align-items-center">
                                            <i class="ri-information-line me-12" style="font-size: 1.5rem;"></i>
                                            <div>
                                                <h6 class="alert-heading mb-8">Sem Assinatura Ativa</h6>
                                                <p class="mb-0">
                                                    Você não possui uma assinatura ativa. 
                                                    <a href="/planos" class="alert-link">Assine um plano</a> para ter acesso a todos os conteúdos premium.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="/planos" class="btn btn-primary d-inline-flex align-items-center">
                                            <i class="ri-eye-line me-2"></i>
                                            Ver Planos
                                        </a>
                                        <a href="/historico-pagamentos" class="btn btn-outline-secondary d-inline-flex align-items-center">
                                            <i class="ri-file-list-line me-2"></i>
                                            Histórico de Pagamentos
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Downloads recentes -->
        <section class="py-80 bg-neutral-50">
            <div class="container">
                <div class="row">
                    <div class="col-12 mb-40">
                        <h3>Meus Downloads Recentes</h3>
                    </div>
                </div>
                
                <?php if (empty($userDownloads)): ?>
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="py-80">
                            <i class="ri-download-line text-secondary-light" style="font-size: 4rem;"></i>
                            <h4 class="mt-24 mb-16">Nenhum download ainda</h4>
                            <p class="text-secondary-light mb-32">
                                Comece a explorar nossos produtos e faça seu primeiro download
                            </p>
                            <a href="/produtos" class="btn btn-primary d-flex align-items-center">Ver Produtos</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                
                <div class="row">
                    <?php foreach ($userDownloads as $download): ?>
                    <div class="col-lg-4 col-md-6 mb-32">
                        <div class="card border-0 shadow-sm">
                            <?php if ($download['image_path']): ?>
                            <img src="<?= htmlspecialchars($download['image_path']) ?>" class="card-img-top" alt="<?= htmlspecialchars($download['product_name']) ?>">
                            <?php endif; ?>
                            <div class="card-body p-24">
                                <div class="d-flex align-items-start justify-content-between mb-16">
                                    <h6 class="card-title mb-0"><?= htmlspecialchars($download['product_name']) ?></h6>
                                    <small class="text-secondary-light">
                                        <?= date('d/m/Y', strtotime($download['downloaded_at'])) ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex align-items-center mb-16">
                                    <small class="text-secondary-light">
                                        <i class="ri-time-line me-8"></i>
                                        <?= date('H:i', strtotime($download['downloaded_at'])) ?>
                                    </small>
                                    <?php if ($download['category_name']): ?>
                                        <small class="text-secondary-light ms-16">
                                            <i class="ri-folder-line me-8"></i>
                                            <?= htmlspecialchars($download['category_name']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="/produto?id=<?= $download['product_id'] ?>" class="btn btn-sm btn-outline-primary flex-fill d-flex align-items-center">
                                        Ver Produto
                                    </a>
                                    <a href="/download?id=<?= $download['product_id'] ?>" class="btn btn-sm btn-primary d-flex align-items-center">
                                        <i class="ri-download-line"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="row">
                    <div class="col-12 text-center">
                        <a href="/produtos" class="btn btn-outline-primary d-inline-flex align-items-center">
                            Ver Todos os Produtos
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Estatísticas -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-12 mb-40">
                        <h3>Minhas Estatísticas</h3>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-32">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body p-32">
                                <div class="mb-16">
                                    <i class="ri-download-line text-primary" style="font-size: 2.5rem;"></i>
                                </div>
                                <h4 class="mb-8"><?= count($userDownloads) ?></h4>
                                <p class="text-secondary-light mb-0">Downloads</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-32">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body p-32">
                                <div class="mb-16">
                                    <i class="ri-calendar-line text-primary" style="font-size: 2.5rem;"></i>
                                </div>
                                <h4 class="mb-8">
                                    <?= $hasSubscription ? 'Ativa' : 'Inativa' ?>
                                </h4>
                                <p class="text-secondary-light mb-0">Assinatura</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-32">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body p-32">
                                <div class="mb-16">
                                    <i class="ri-time-line text-primary" style="font-size: 2.5rem;"></i>
                                </div>
                                <h4 class="mb-8">
                                    <?= $user ? date('d/m/Y', strtotime($user['created_at'])) : 'N/A' ?>
                                </h4>
                                <p class="text-secondary-light mb-0">Membro desde</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-32">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body p-32">
                                <div class="mb-16">
                                    <i class="ri-user-line text-primary" style="font-size: 2.5rem;"></i>
                                </div>
                                <h4 class="mb-8"><?= ucfirst($user['status'] ?? 'Ativo') ?></h4>
                                <p class="text-secondary-light mb-0">Status da conta</p>
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


</body>
</html>
