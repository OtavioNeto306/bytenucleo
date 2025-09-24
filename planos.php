<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Subscription.php';

$auth = new Auth($pdo);
$subscription = new Subscription($pdo);

// Obter planos ativos
$plans = $subscription->getAllPlans('active');

$isLoggedIn = $auth->isLoggedIn();
$hasSubscription = false;
$currentSubscription = null;

if ($isLoggedIn) {
    $currentSubscription = $subscription->getUserActiveSubscription($auth->getUserId());
    $hasSubscription = $subscription->isSubscriptionActive($auth->getUserId());
}
?>

<?php include './partials/layouts/layoutTop.php' ?>

        <!-- Hero Section -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Planos</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Escolha o plano ideal para suas necessidades
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Current Subscription Banner -->
        <?php if ($hasSubscription && $currentSubscription): ?>
        <section class="py-40 border-bottom">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-info" role="alert">
                            <h5 class="alert-heading">Sua assinatura atual</h5>
                            <p class="mb-0">
                                Você está inscrito no plano <strong><?= htmlspecialchars($currentSubscription['plan_name']) ?></strong> 
                                até <?= date('d/m/Y', strtotime($currentSubscription['end_date'])) ?>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Plans Section -->
        <section class="py-80 bg-base">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xxl-10">
                        <div class="row">
                            <?php 
                            $planConfigs = [
                                [
                                    'name' => 'Básico',
                                    'icon' => 'ri-rocket-line',
                                    'bgClass' => 'bg-neutral-50',
                                    'borderClass' => 'border-neutral-200',
                                    'textClass' => 'text-neutral-800',
                                    'buttonClass' => 'btn-primary',
                                    'checkClass' => 'bg-primary-600'
                                ],
                                [
                                    'name' => 'Premium',
                                    'icon' => 'ri-vip-crown-line',
                                    'bgClass' => 'bg-neutral-50',
                                    'borderClass' => 'border-neutral-200',
                                    'textClass' => 'text-neutral-800',
                                    'buttonClass' => 'btn-warning',
                                    'checkClass' => 'bg-warning-600',
                                    'popular' => true
                                ],
                                [
                                    'name' => 'Exclusivo',
                                    'icon' => 'ri-diamond-line',
                                    'bgClass' => 'bg-neutral-50',
                                    'borderClass' => 'border-neutral-200',
                                    'textClass' => 'text-neutral-800',
                                    'buttonClass' => 'btn-success',
                                    'checkClass' => 'bg-success-600'
                                ]
                            ];
                            
                            foreach ($plans as $index => $plan): 
                                $config = $planConfigs[$index % count($planConfigs)];
                                $isCurrentPlan = $hasSubscription && $currentSubscription && $currentSubscription['plan_id'] == $plan['id'];
                            ?>
                            <div class="col-lg-4 col-md-6 mb-32">
                                <div class="card h-100 border-0 shadow-sm <?= $config['bgClass'] ?> <?= $config['borderClass'] ?> <?= isset($config['popular']) && $config['popular'] ? 'border-2' : '' ?>">
                                    <?php if (isset($config['popular']) && $config['popular']): ?>
                                    <div class="position-absolute top-0 end-0 m-16">
                                        <span class="badge bg-lilac-600 text-white px-16 py-8 radius-16">
                                            <i class="ri-star-line me-4"></i>
                                            Popular
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body p-24">
                                        <!-- Header -->
                                        <div class="text-center mb-16">
                                            <div class="w-64 h-64 mx-auto mb-16 d-flex align-items-center justify-content-center <?= $config['bgClass'] ?> radius-16">
                                                <i class="<?= $config['icon'] ?> text-24 <?= $config['textClass'] ?>"></i>
                                            </div>
                                            <h5 class="card-title mb-8 <?= $config['textClass'] ?>"><?= htmlspecialchars($plan['name']) ?></h5>
                                            <p class="text-neutral-600 mb-0"><?= htmlspecialchars($plan['description']) ?></p>
                                        </div>


                                        <!-- Price -->
                                        <div class="text-center mb-16">
                                            <div class="d-flex align-items-baseline justify-content-center">
                                                <span class="h4 fw-bold <?= $config['textClass'] ?>">
                                                    <?php if ($plan['price'] > 0): ?>
                                                        R$ <?= number_format($plan['price'], 2, ',', '.') ?>
                                                    <?php else: ?>
                                                        <span class="text-success">Grátis</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="text-neutral-600 ms-8">
                                                    /<?= $plan['duration_days'] ?> dias
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Features -->
                                        <div class="mb-16">
                                            <h6 class="fw-semibold mb-12 <?= $config['textClass'] ?>">O que está incluído:</h6>
                                            <?php 
                                            $features = json_decode($plan['features'], true);
                                            if ($features):
                                                $featureNames = $subscription->getFeatureDisplayNames($features);
                                            ?>
                                            <ul class="list-unstyled">
                                                <?php foreach ($featureNames as $featureName): ?>
                                                <li class="d-flex align-items-center mb-8">
                                                    <div class="w-20 h-20 d-flex align-items-center justify-content-center <?= $config['checkClass'] ?> radius-10 me-8">
                                                        <i class="ri-check-line text-white text-12"></i>
                                                    </div>
                                                    <span class="text-neutral-600">
                                                        <?= htmlspecialchars($featureName) ?>
                                                    </span>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Button -->
                                        <div class="d-flex gap-2">
                                            <?php if ($isLoggedIn): ?>
                                                <?php if ($isCurrentPlan): ?>
                                                    <button class="btn btn-success flex-fill" disabled>
                                                        <i class="ri-check-line me-8"></i>
                                                        Plano Atual
                                                    </button>
                                                <?php else: ?>
                                                    <a href="pagamento-plano.php?plan=<?= $plan['id'] ?>" class="btn <?= $config['buttonClass'] ?> flex-fill">
                                                        <i class="ri-arrow-right-line me-8"></i>
                                                        <?= $hasSubscription ? 'Trocar Plano' : 'Assinar Agora' ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="login.php?redirect=planos.php" class="btn btn-outline-primary flex-fill">
                                                    <i class="ri-login-box-line me-8"></i>
                                                    Fazer Login para Assinar
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Comparison -->
        <section class="py-80">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center mb-40">
                        <h3 class="mb-16">Por que escolher nossos planos?</h3>
                        <p class="text-neutral-600">
                            Oferecemos a melhor experiência para desenvolvedores e empresas
                        </p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-4 col-md-6 mb-32">
                        <div class="text-center">
                            <div class="w-64 h-64 mx-auto mb-24 d-flex align-items-center justify-content-center bg-primary-50 radius-16">
                                <i class="ri-download-line text-24 text-primary"></i>
                            </div>
                            <h5 class="fw-semibold mb-12">Downloads Ilimitados</h5>
                            <p class="text-neutral-600 mb-0">
                                Baixe quantos produtos quiser, sem limites ou restrições
                            </p>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-32">
                        <div class="text-center">
                            <div class="w-64 h-64 mx-auto mb-24 d-flex align-items-center justify-content-center bg-success-50 radius-16">
                                <i class="ri-refresh-line text-24 text-success"></i>
                            </div>
                            <h5 class="fw-semibold mb-12">Atualizações Automáticas</h5>
                            <p class="text-neutral-600 mb-0">
                                Receba atualizações e melhorias automaticamente
                            </p>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6 mb-32">
                        <div class="text-center">
                            <div class="w-64 h-64 mx-auto mb-24 d-flex align-items-center justify-content-center bg-warning-50 radius-16">
                                <i class="ri-customer-service-line text-24 text-warning"></i>
                            </div>
                            <h5 class="fw-semibold mb-12">Suporte Prioritário</h5>
                            <p class="text-neutral-600 mb-0">
                                Suporte técnico especializado e resposta rápida
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

<?php include './partials/layouts/layoutBottom.php' ?>