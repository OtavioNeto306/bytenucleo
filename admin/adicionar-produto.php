<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/Product.php';
require_once '../includes/Subscription.php';
require_once '../includes/Notification.php';

$auth = new Auth($pdo);
$product = new Product($pdo);
$subscription = new Subscription($pdo);
$notification = new Notification($pdo);

// Verificar permissões
if (!$auth->hasPermission('manage_products')) {
    header('Location: ../login.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Função para limpar caracteres especiais e emojis
        function cleanText($text) {
            // Remove emojis e caracteres especiais
            $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text); // Emojis faciais
            $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $text); // Símbolos e pictogramas
            $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text); // Transporte e símbolos
            $text = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', '', $text); // Bandeiras
            $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text); // Símbolos diversos
            $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text); // Símbolos decorativos
            return trim($text);
        }
        
        // Dados básicos do produto
        $name = cleanText(trim($_POST['name']));
        $short_description = cleanText(trim($_POST['short_description']));
        $full_description = cleanText(trim($_POST['full_description']));
        $category_id = $_POST['category_id'];
        $selected_plans = $_POST['plans'] ?? [];
        $individual_sale = isset($_POST['individual_sale']) ? 1 : 0;
        $individual_price = floatval($_POST['individual_price'] ?? 0);
        $video_apresentacao = trim($_POST['video_apresentacao']);
        $video_thumbnail = trim($_POST['video_thumbnail']);
        $max_downloads_per_user = intval($_POST['max_downloads_per_user']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $status = $_POST['status'];
        $tags = trim($_POST['tags']);
        $version = trim($_POST['version'] ?? '');
        $last_updated = !empty($_POST['last_updated']) ? $_POST['last_updated'] : null;
        $demo_url = trim($_POST['demo_url'] ?? '');
        $requirements = trim($_POST['requirements'] ?? '');
        
        // Validar dados obrigatórios
        if (empty($name)) {
            throw new Exception('Nome do produto é obrigatório');
        }
        
        if (empty($category_id)) {
            throw new Exception('Categoria é obrigatória');
        }
        
        // Upload do arquivo principal
        $file_path = null;
        if (!empty($_FILES['product_file']['name'])) {
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['product_file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = 'uploads/products/' . $file_name;
            
            if (!move_uploaded_file($_FILES['product_file']['tmp_name'], '../' . $file_path)) {
                throw new Exception('Erro ao fazer upload do arquivo principal');
            }
        }
        
        // Upload da imagem
        $image_path = null;
        if (!empty($_FILES['product_image']['name'])) {
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $image_path = 'uploads/products/' . $file_name;
            
            if (!move_uploaded_file($_FILES['product_image']['tmp_name'], '../' . $image_path)) {
                throw new Exception('Erro ao fazer upload da imagem');
            }
        }
        
        // Criar produto (sem plan_id por enquanto)
        $product_id = $product->createProduct([
            'name' => $name,
            'short_description' => $short_description,
            'full_description' => $full_description,
            'category_id' => $category_id,
            'plan_id' => null, // Será gerenciado pela tabela product_plans
            'individual_sale' => $individual_sale,
            'individual_price' => $individual_price,
            'file_path' => $file_path,
            'image_path' => $image_path,
            'video_apresentacao' => $video_apresentacao,
            'video_thumbnail' => $video_thumbnail,
            'max_downloads_per_user' => $max_downloads_per_user,
            'featured' => $featured,
            'status' => $status,
            'tags' => $tags,
            'version' => $version,
            'last_updated' => $last_updated,
            'demo_url' => $demo_url,
            'requirements' => $requirements
        ]);
        
        // Associar planos ao produto
        if ($product_id && !empty($selected_plans)) {
            foreach ($selected_plans as $plan_value) {
                $plan_id = intval($plan_value);
                if ($plan_id > 0) {
                    $stmt = $pdo->prepare("INSERT INTO product_plans (product_id, plan_id) VALUES (?, ?)");
                    $stmt->execute([$product_id, $plan_id]);
                }
            }
        }
        
        if ($product_id) {
            // Adicionar vídeos (aulas)
            if (!empty($_POST['video_titles'])) {
                foreach ($_POST['video_titles'] as $index => $title) {
                    if (!empty($title) && !empty($_POST['video_urls'][$index])) {
                        $product->addVideo(
                            $product_id,
                            $title,
                            $_POST['video_descriptions'][$index] ?? '',
                            $_POST['video_urls'][$index],
                            $_POST['video_durations'][$index] ?? null,
                            $index
                        );
                    }
                }
            }
            
            // Adicionar materiais de apoio
            if (!empty($_POST['material_names'])) {
                foreach ($_POST['material_names'] as $index => $name) {
                    if (!empty($name)) {
                        $type = $_POST['material_types'][$index];
                        $file_path_material = null;
                        $external_url = null;
                        
                        if ($type === 'file' && !empty($_FILES['material_files']['name'][$index])) {
                            $upload_dir = '../uploads/materials/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            
                            $file_extension = pathinfo($_FILES['material_files']['name'][$index], PATHINFO_EXTENSION);
                            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
                            $file_path_material = 'uploads/materials/' . $file_name;
                            
                            if (move_uploaded_file($_FILES['material_files']['tmp_name'][$index], '../' . $file_path_material)) {
                                $file_path_material = $file_path_material;
                            }
                        } elseif ($type === 'link') {
                            $external_url = $_POST['material_urls'][$index] ?? '';
                        }
                        
                        $is_gradual_release = isset($_POST['material_gradual_release'][$index]) ? 1 : 0;
                        $release_days = $_POST['material_release_days'][$index] ?? 0;
                        
                        $product->addMaterial(
                            $product_id,
                            $name,
                            $type,
                            $file_path_material,
                            $external_url,
                            $index,
                            $is_gradual_release,
                            $release_days
                        );
                    }
                }
            }
            
            // Notificar admins sobre o novo produto
            try {
                // Buscar todos os admins e super admins
                $stmt = $pdo->prepare("
                    SELECT DISTINCT u.id 
                    FROM users u 
                    JOIN user_permissions up ON u.id = up.user_id 
                    WHERE up.permission IN ('manage_products', 'manage_system') 
                    AND up.granted = 1
                    UNION
                    SELECT u.id 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE r.name IN ('admin', 'super_admin')
                ");
                $stmt->execute();
                $adminUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($adminUserIds)) {
                    $notification->createAdminNotification(
                        $adminUserIds,
                        'Novo Produto Criado',
                        "O produto '{$name}' foi criado com sucesso por " . $auth->getCurrentUser()['name']
                    );
                }
            } catch (Exception $e) {
                // Silenciar erro de notificação
                error_log("Erro ao enviar notificação de novo produto: " . $e->getMessage());
            }
            
            header('Location: produtos.php?success=created');
            exit;
        } else {
            throw new Exception('Erro ao criar produto');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obter categorias e planos
$categories = $product->getAllCategories();
$plans = $subscription->getAllPlans();
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
                        <h1 class="display-4 fw-bold mb-24">Adicionar Produto</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Crie um novo produto com vídeos, materiais de apoio e arquivos
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div class="dashboard-main-body">

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="ri-error-warning-line me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                
                <!-- Informações Básicas -->
                <div class="card mb-24">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-information-line me-2"></i>
                            Informações Básicas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome do Produto *</label>
                                <input type="text" class="form-control" name="name" required 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                       placeholder="Digite o nome do produto">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Categoria *</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Selecione uma categoria</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Planos Disponíveis</label>
                                <div class="row g-2">
                                    <?php foreach ($plans as $plan): ?>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="plans[]" value="<?= $plan['id'] ?>" 
                                                   <?= (isset($_POST['plans']) && in_array($plan['id'], $_POST['plans'])) ? 'checked' : '' ?>>
                                            <label class="form-check-label">
                                                <strong><?= htmlspecialchars($plan['name']) ?></strong>
                                                <small class="d-block text-secondary-light">R$ <?= number_format($plan['price'], 2, ',', '.') ?> - <?= htmlspecialchars($plan['description']) ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-secondary-light">Selecione os planos que terão acesso a este produto.</small>
                            </div>
                            
                            <!-- Venda Individual -->
                            <div class="col-md-12">
                                <div class="border-top pt-3 mt-3">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="individual_sale" id="individual_sale" value="1" 
                                               <?= (isset($_POST['individual_sale']) && $_POST['individual_sale']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="individual_sale">
                                            <i class="ri-shopping-cart-line me-2"></i>
                                            Venda Individual
                                        </label>
                                    </div>
                                    <div class="row g-3" id="individual_sale_fields" style="display: none;">
                                        <div class="col-md-6">
                                            <label class="form-label">Preço Individual (R$)</label>
                                            <input type="number" class="form-control" name="individual_price" step="0.01" min="0" 
                                                   value="<?= htmlspecialchars($_POST['individual_price'] ?? '0.00') ?>" 
                                                   placeholder="0.00">
                                            <small class="text-secondary-light">Preço para compra direta do produto</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição Curta</label>
                                <textarea class="form-control" name="short_description" rows="2" 
                                          placeholder="Descrição breve do produto..."><?= htmlspecialchars($_POST['short_description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição Completa</label>
                                <textarea class="form-control" name="full_description" rows="5" 
                                          placeholder="Descrição detalhada do produto..."><?= htmlspecialchars($_POST['full_description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configurações do Produto -->
                <div class="card mb-24">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-settings-line me-2"></i>
                            Configurações do Produto
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="draft" <?= (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : '' ?>>Rascunho</option>
                                    <option value="active" <?= (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : '' ?>>Ativo</option>
                                    <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : '' ?>>Inativo</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Downloads por Usuário</label>
                                <input type="number" class="form-control" name="max_downloads_per_user" min="-1" 
                                       value="<?= htmlspecialchars($_POST['max_downloads_per_user'] ?? '-1') ?>" 
                                       placeholder="-1 = ilimitado">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tags</label>
                                <input type="text" class="form-control" name="tags" 
                                       value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>" 
                                       placeholder="tag1, tag2, tag3">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="featured" value="1" 
                                           <?= (isset($_POST['featured']) && $_POST['featured']) ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        Produto em Destaque
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vídeo de Apresentação -->
                <div class="card mb-24">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-video-line me-2"></i>
                            Vídeo de Apresentação (Opcional)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Código do Vídeo</label>
                                <input type="text" class="form-control" name="video_apresentacao" 
                                       value="<?= htmlspecialchars($_POST['video_apresentacao'] ?? '') ?>" 
                                       placeholder="Ex: i6gEcyFmJDc?si=sYI499sHHK5tFKCZ">
                                <small class="text-neutral-600">Para o vídeo 'https://youtu.be/i6gEcyFmJDc?si=sYI499sHHK5tFKCZ', use apenas 'i6gEcyFmJDc?si=sYI499sHHK5tFKCZ'</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Thumbnail do Vídeo (URL)</label>
                                <input type="url" class="form-control" name="video_thumbnail" 
                                       value="<?= htmlspecialchars($_POST['video_thumbnail'] ?? '') ?>" 
                                       placeholder="https://...">
                                <small class="text-neutral-600">Deixe vazio para usar thumbnail automático do YouTube</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informações Adicionais -->
                <div class="card mb-24">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-information-line me-2"></i>
                            Informações Adicionais (Opcional)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Versão do Produto</label>
                                <input type="text" class="form-control" name="version" 
                                       value="<?= htmlspecialchars($_POST['version'] ?? '') ?>" 
                                       placeholder="Ex: 1.0, v2.1, 3.0.1">
                                <small class="text-neutral-600">Versão atual do produto</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data da Última Atualização</label>
                                <input type="date" class="form-control" name="last_updated" 
                                       value="<?= htmlspecialchars($_POST['last_updated'] ?? '') ?>">
                                <small class="text-neutral-600">Quando foi a última atualização</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">URL da Demonstração</label>
                                <input type="url" class="form-control" name="demo_url" 
                                       value="<?= htmlspecialchars($_POST['demo_url'] ?? '') ?>" 
                                       placeholder="https://demo.meusite.com/produto">
                                <small class="text-neutral-600">Link para demonstração online do produto</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Requisitos para Instalação</label>
                                <textarea class="form-control" name="requirements" rows="4" 
                                          placeholder="Ex: PHP 7.4+, MySQL 5.7+, Apache/Nginx, Extensões: GD, PDO, cURL"><?= htmlspecialchars($_POST['requirements'] ?? '') ?></textarea>
                                <small class="text-neutral-600">Liste os requisitos técnicos necessários</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Arquivos -->
                <div class="card mb-24">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-file-line me-2"></i>
                            Arquivos
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Arquivo Principal do Produto</label>
                                <input type="file" class="form-control" name="product_file">
                                <small class="text-neutral-600">Arquivo que será baixado pelos usuários</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Imagem do Produto</label>
                                <input type="file" class="form-control" name="product_image" accept="image/*">
                                <small class="text-neutral-600">Imagem de capa do produto</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vídeos do Curso -->
                <div class="card mb-24">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="ri-play-circle-line me-2"></i>
                            Vídeos do Curso (Aulas)
                        </h5>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addVideo()">
                            <i class="ri-add-line me-1"></i>
                            Adicionar Aula
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="videos-container">
                            <!-- Vídeos serão adicionados aqui dinamicamente -->
                        </div>
                                                        <div class="text-center text-neutral-600 py-3" id="no-videos-message">
                            <i class="ri-video-line" style="font-size: 2rem;"></i>
                            <p class="mt-2">Nenhuma aula adicionada ainda</p>
                            <p class="small">Clique em "Adicionar Aula" para começar</p>
                        </div>
                    </div>
                </div>

                <!-- Materiais de Apoio -->
                <div class="card mb-24">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="ri-folder-line me-2"></i>
                            Materiais de Apoio
                        </h5>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addMaterial()">
                            <i class="ri-add-line me-1"></i>
                            Adicionar Material
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="materials-container">
                            <!-- Materiais serão adicionados aqui dinamicamente -->
                        </div>
                                                        <div class="text-center text-neutral-600 py-3" id="no-materials-message">
                            <i class="ri-folder-line" style="font-size: 2rem;"></i>
                            <p class="mt-2">Nenhum material adicionado ainda</p>
                            <p class="small">Clique em "Adicionar Material" para começar</p>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="d-flex justify-content-between">
                    <a href="produtos.php" class="btn btn-secondary">
                        <i class="ri-arrow-left-line me-1"></i>
                        Voltar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i>
                        Criar Produto
                    </button>
                </div>

            </form>

        </div>

        <?php include '../partials/footer.php' ?>
    </main>

    <?php include '../partials/scripts.php' ?>
    
    <script>
        let videoIndex = 0;
        let materialIndex = 0;
        
        function addVideo() {
            const container = document.getElementById("videos-container");
            const videoHtml = `
                <div class="card mb-3" id="video-${videoIndex}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Título da Aula</label>
                                <input type="text" class="form-control" name="video_titles[]" placeholder="Ex: Introdução ao Curso" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Código do Vídeo</label>
                                <input type="text" class="form-control" name="video_urls[]" placeholder="Ex: i6gEcyFmJDc?si=sYI499sHHK5tFKCZ" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Duração (opcional)</label>
                                <input type="text" class="form-control" name="video_durations[]" placeholder="12:34">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Descrição</label>
                                <textarea class="form-control" name="video_descriptions[]" rows="2" placeholder="Descrição da aula..."></textarea>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-danger w-100" onclick="removeVideo(${videoIndex})">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML("beforeend", videoHtml);
            videoIndex++;
            updateMessages();
        }
        
        function removeVideo(index) {
            document.getElementById(`video-${index}`).remove();
            updateMessages();
        }
        
        function addMaterial() {
            const container = document.getElementById("materials-container");
            const materialHtml = `
                <div class="card mb-3" id="material-${materialIndex}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <label class="form-label">Nome</label>
                                <input type="text" class="form-control" name="material_names[]" placeholder="Nome do material" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="material_types[]" onchange="toggleMaterialType(${materialIndex})">
                                    <option value="file">Arquivo</option>
                                    <option value="link">Link Externo</option>
                                </select>
                            </div>
                            <div class="col-md-2" id="material-file-${materialIndex}">
                                <label class="form-label">Arquivo</label>
                                <input type="file" class="form-control" name="material_files[]">
                            </div>
                            <div class="col-md-2" id="material-link-${materialIndex}" style="display:none;">
                                <label class="form-label">URL Externa</label>
                                <input type="url" class="form-control" name="material_urls[]" placeholder="https://...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Liberação Gradual</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="material_gradual_release[]" value="1" id="gradual-${materialIndex}" onchange="toggleReleaseDays(${materialIndex})">
                                    <label class="form-check-label" for="gradual-${materialIndex}">
                                        Liberação gradual
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-1" id="release-days-${materialIndex}" style="display:none;">
                                <label class="form-label">Dias</label>
                                <input type="number" class="form-control" name="material_release_days[]" min="0" max="365" value="7" placeholder="7">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-danger w-100" onclick="removeMaterial(${materialIndex})">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML("beforeend", materialHtml);
            materialIndex++;
            updateMessages();
        }
        
        function removeMaterial(index) {
            document.getElementById(`material-${index}`).remove();
            updateMessages();
        }
        
        function toggleMaterialType(index) {
            const select = document.querySelector(`#material-${index} select[name="material_types[]"]`);
            const type = select.value;
            const fileDiv = document.getElementById(`material-file-${index}`);
            const linkDiv = document.getElementById(`material-link-${index}`);
            
            if (type === "file") {
                fileDiv.style.display = "block";
                linkDiv.style.display = "none";
            } else {
                fileDiv.style.display = "none";
                linkDiv.style.display = "block";
            }
        }
        
        function toggleReleaseDays(index) {
            const checkbox = document.getElementById(`gradual-${index}`);
            const daysDiv = document.getElementById(`release-days-${index}`);
            
            if (checkbox.checked) {
                daysDiv.style.display = "block";
            } else {
                daysDiv.style.display = "none";
            }
        }
        
        // Função para atualizar visibilidade das mensagens
        function updateMessages() {
            const videosContainer = document.getElementById("videos-container");
            const materialsContainer = document.getElementById("materials-container");
            const noVideosMessage = document.getElementById("no-videos-message");
            const noMaterialsMessage = document.getElementById("no-materials-message");
            
            if (videosContainer.children.length > 0) {
                noVideosMessage.style.display = "none";
            } else {
                noVideosMessage.style.display = "block";
            }
            
            if (materialsContainer.children.length > 0) {
                noMaterialsMessage.style.display = "none";
            } else {
                noMaterialsMessage.style.display = "block";
            }
        }
        
        // Atualizar mensagens quando a página carrega
        document.addEventListener('DOMContentLoaded', updateMessages);
        
        // Controle da venda individual
        document.addEventListener('DOMContentLoaded', function() {
            const individualSaleCheckbox = document.getElementById('individual_sale');
            const individualSaleFields = document.getElementById('individual_sale_fields');
            
            function toggleIndividualSaleFields() {
                if (individualSaleCheckbox.checked) {
                    individualSaleFields.style.display = 'block';
                } else {
                    individualSaleFields.style.display = 'none';
                }
            }
            
            individualSaleCheckbox.addEventListener('change', toggleIndividualSaleFields);
            toggleIndividualSaleFields(); // Executar na carga inicial
        });
    </script>
    
    <style>
        /* Estilos para os checkboxes dos planos */
        .form-check {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }
        
        .form-check:hover {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .form-check-input:checked + .form-check-label {
            color: #0d6efd;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
        }
        
        .form-check-label {
            cursor: pointer;
            padding-left: 8px;
        }
        
        .form-check-label strong {
            display: block;
            margin-bottom: 4px;
        }
        
        .form-check-label small {
            color: #6c757d;
            font-size: 0.875em;
        }
    </style>
</body>

</html>
