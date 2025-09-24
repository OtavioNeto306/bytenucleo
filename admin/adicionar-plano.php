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

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validar dados
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $durationDays = intval($_POST['duration_days'] ?? 30);
    $maxDownloads = $_POST['max_downloads'] === '' ? -1 : intval($_POST['max_downloads']);
    $maxProducts = $_POST['max_products'] === '' ? -1 : intval($_POST['max_products']);
    $status = $_POST['status'] ?? 'active';
    
    // Corrigir processamento das features
    $features = [];
    if (isset($_POST['features']) && is_array($_POST['features'])) {
        $features = array_filter($_POST['features']); // Remove valores vazios
    }
    
    // Se nenhuma feature foi selecionada, definir features básicas
    if (empty($features)) {
        $features = ['view_products', 'download_products'];
    }
    
    // Validações
    if (empty($name)) {
        $errors[] = 'Nome do plano é obrigatório';
    }
    
    if (empty($description)) {
        $errors[] = 'Descrição é obrigatória';
    }
    
    if ($price < 0) {
        $errors[] = 'Preço não pode ser negativo';
    }
    
    if ($durationDays <= 0) {
        $errors[] = 'Duração deve ser maior que zero';
    }
    
    if ($maxDownloads < -1) {
        $errors[] = 'Limite de downloads inválido';
    }
    
    if ($maxProducts < -1) {
        $errors[] = 'Limite de produtos inválido';
    }
    
    // Criar slug
    $slug = $subscription->createSlug($name);
    if ($subscription->slugExists($slug)) {
        $errors[] = 'Já existe um plano com este nome';
    }
    
    // Se não há erros, criar o plano
    if (empty($errors)) {
        $planData = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'price' => $price,
            'duration_days' => $durationDays,
            'max_downloads' => $maxDownloads,
            'max_products' => $maxProducts,
            'features' => $features,
            'status' => $status
        ];
        
        if ($subscription->createPlan($planData)) {
            header('Location: planos.php?success=created');
            exit;
        } else {
            $errors[] = 'Erro ao criar plano. Tente novamente.';
        }
    }
}

// Obter todas as permissões para o formulário
$availableFeatures = $subscription->getFeatureNames();
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include '../partials/head.php' ?>
?>

<body>

    <?php include '../partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include '../partials/navbar.php' ?>
    
             <!-- Header -->
         <section class="py-80 bg-primary-50">
             <div class="container">
                 <div class="row align-items-center">
                     <div class="col-lg-8">
                         <h1 class="display-4 fw-bold mb-24">Adicionar Plano</h1>
                                                   <p class="text-lg text-neutral-600 mb-0">
                              Crie um novo plano de assinatura
                          </p>
                     </div>
                     <div class="col-lg-4 text-lg-end">
                         <a href="planos.php" class="btn btn-outline-light">
                             <i class="ri-arrow-left-line me-2"></i>Voltar
                         </a>
                     </div>
                 </div>
             </div>
         </section>

         <!-- Conteúdo -->
         <section class="py-80">
             <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card border bg-base">
                            <div class="card-body p-32">
                                <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <h6 class="alert-heading mb-8">Erros encontrados:</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <!-- Informações Básicas -->
                                    <div class="mb-32">
                                                                                 <h5 class="mb-16">Informações Básicas</h5>
                                        
                                        <div class="row g-16">
                                            <div class="col-md-6">
                                                                                                 <label class="form-label">Nome do Plano *</label>
                                                <input type="text" class="form-control bg-base border-secondary" 
                                                       name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                                       placeholder="Ex: Plano Premium" required>
                                            </div>
                                            <div class="col-md-6">
                                                                                                 <label class="form-label">Status</label>
                                                <select class="form-select bg-base border-secondary" name="status">
                                                    <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Ativo</option>
                                                    <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-16">
                                                                                         <label class="form-label">Descrição *</label>
                                            <textarea class="form-control bg-base border-secondary" name="description" 
                                                      rows="3" placeholder="Descreva os benefícios deste plano" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Preços e Duração -->
                                    <div class="mb-32">
                                                                                 <h5 class="mb-16">Preços e Duração</h5>
                                        
                                        <div class="row g-16">
                                            <div class="col-md-6">
                                                                                                 <label class="form-label">Preço (R$)</label>
                                                <input type="number" class="form-control bg-base border-secondary" 
                                                       name="price" value="<?= htmlspecialchars($_POST['price'] ?? '0.00') ?>" 
                                                       step="0.01" min="0" placeholder="0.00">
                                                                                                 <small class="text-neutral-600">Deixe 0 para plano gratuito</small>
                                            </div>
                                            <div class="col-md-6">
                                                                                                 <label class="form-label">Duração (dias) *</label>
                                                <input type="number" class="form-control bg-base border-secondary" 
                                                       name="duration_days" value="<?= htmlspecialchars($_POST['duration_days'] ?? '30') ?>" 
                                                       min="1" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Limites -->
                                    <div class="mb-32">
                                                                                 <h5 class="mb-16">Limites</h5>
                                        
                                        <div class="row g-16">
                                            <div class="col-md-6">
                                                                                                 <label class="form-label">Limite de Downloads</label>
                                                <input type="number" class="form-control bg-base border-secondary" 
                                                       name="max_downloads" value="<?= htmlspecialchars($_POST['max_downloads'] ?? '') ?>" 
                                                       min="-1" placeholder="Deixe vazio para ilimitado">
                                                                                                 <small class="text-neutral-600">Use -1 para ilimitado</small>
                                             </div>
                                             <div class="col-md-6">
                                                 <label class="form-label">Limite de Produtos</label>
                                                <input type="number" class="form-control bg-base border-secondary" 
                                                       name="max_products" value="<?= htmlspecialchars($_POST['max_products'] ?? '') ?>" 
                                                       min="-1" placeholder="Deixe vazio para ilimitado">
                                                                                                 <small class="text-neutral-600">Use -1 para ilimitado</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                                                                                             <!-- Recursos -->
                                    <div class="mb-32">
                                        <h5 class="mb-16">Recursos Incluídos</h5>
                                        <p class="text-neutral-600 mb-16">Selecione os recursos que este plano terá:</p>
                                        
                                        <?php 
                                        // Corrigir a lógica de seleção
                                        $selectedFeatures = [];
                                        if (isset($_POST['features']) && is_array($_POST['features'])) {
                                            $selectedFeatures = $_POST['features'];
                                        }
                                        ?>
                                        <div class="row g-12">
                                            <?php foreach ($availableFeatures as $slug => $name): ?>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="features[]" value="<?= $slug ?>" 
                                                           id="feature_<?= $slug ?>"
                                                           <?= in_array($slug, $selectedFeatures) ? 'checked' : '' ?>>
                                                    <label class="form-check-label text-neutral-600" for="feature_<?= $slug ?>">
                                                        <?= htmlspecialchars($name) ?>
                                                    </label>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Botões -->
                                    <div class="d-flex gap-16">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ri-save-line me-2"></i>Criar Plano
                                        </button>
                                        <a href="planos.php" class="btn btn-outline-secondary">
                                            Cancelar
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                                         </div>
                 </div>
             </div>
         </section>
     </main>

           <?php include '../partials/footer.php' ?>
      <?php include '../partials/scripts.php' ?>
 </body>
 </html>
