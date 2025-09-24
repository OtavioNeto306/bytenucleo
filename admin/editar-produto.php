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

// Verificar se ID do produto foi fornecido
$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header('Location: produtos.php?error=ID do produto não fornecido');
    exit;
}

// Buscar dados do produto
$productData = $product->getProductById($product_id);
if (!$productData) {
    header('Location: produtos.php?error=Produto não encontrado');
    exit;
}

// Buscar planos associados ao produto
$stmt = $pdo->prepare("SELECT plan_id FROM product_plans WHERE product_id = ?");
$stmt->execute([$product_id]);
$associated_plans = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
        
        // Preparar dados para atualização
        $updateData = [
            'name' => $name,
            'short_description' => $short_description,
            'full_description' => $full_description,
            'category_id' => $category_id,
            'price' => $price,
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
        ];
        
        // Upload do arquivo principal (se fornecido)
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
            
            $updateData['file_path'] = $file_path;
        } else {
            // Manter o arquivo atual se não for enviado um novo
            $updateData['file_path'] = $productData['file_path'];
        }
        
        // Upload da imagem (se fornecida)
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
            
            $updateData['image_path'] = $image_path;
        } else {
            // Manter a imagem atual se não for enviada uma nova
            $updateData['image_path'] = $productData['image_path'];
        }
        
        // Adicionar dados de venda individual
        $updateData['individual_sale'] = $individual_sale;
        $updateData['individual_price'] = $individual_price;
        
        // Atualizar produto
        if ($product->updateProduct($product_id, $updateData)) {
            // Atualizar planos associados
            // Primeiro, remover todos os planos atuais
            $stmt = $pdo->prepare("DELETE FROM product_plans WHERE product_id = ?");
            $stmt->execute([$product_id]);
            
            // Depois, adicionar os novos planos selecionados
            if (!empty($selected_plans)) {
                foreach ($selected_plans as $plan_value) {
                    $plan_id = intval($plan_value);
                    if ($plan_id > 0) {
                        $stmt = $pdo->prepare("INSERT INTO product_plans (product_id, plan_id) VALUES (?, ?)");
                        $stmt->execute([$product_id, $plan_id]);
                    }
                }
            }
            
            // Adicionar vídeos (aulas) se fornecidos
            if (!empty($_POST['video_titles'])) {
                // Salvar progresso existente antes de deletar vídeos
                $stmt = $pdo->prepare("
                    SELECT lp.*, pv.title, pv.youtube_url 
                    FROM lesson_progress lp 
                    JOIN product_videos pv ON lp.video_id = pv.id 
                    WHERE lp.product_id = ?
                ");
                $stmt->execute([$product_id]);
                $existing_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Remover vídeos existentes
                $stmt = $pdo->prepare("DELETE FROM product_videos WHERE product_id = ?");
                $stmt->execute([$product_id]);
                
                // Adicionar novos vídeos
                $new_video_ids = [];
                foreach ($_POST['video_titles'] as $index => $title) {
                    if (!empty($title) && !empty($_POST['video_urls'][$index])) {
                        $video_id = $product->addVideo(
                            $product_id,
                            $title,
                            $_POST['video_descriptions'][$index] ?? '',
                            $_POST['video_urls'][$index],
                            $_POST['video_durations'][$index] ?? null,
                            $index
                        );
                        
                        // Mapear título/URL para o novo ID do vídeo
                        $new_video_ids[] = [
                            'id' => $video_id,
                            'title' => $title,
                            'youtube_url' => $_POST['video_urls'][$index]
                        ];
                    }
                }
                
                // Restaurar progresso baseado no título e URL
                foreach ($existing_progress as $progress) {
                    // Procurar vídeo correspondente pelo título e URL
                    foreach ($new_video_ids as $new_video) {
                        if ($new_video['title'] === $progress['title'] && 
                            $new_video['youtube_url'] === $progress['youtube_url']) {
                            
                            // Restaurar progresso para o novo vídeo
                            $stmt = $pdo->prepare("
                                INSERT INTO lesson_progress (user_id, product_id, video_id, completed_at, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                completed_at = VALUES(completed_at),
                                updated_at = VALUES(updated_at)
                            ");
                            $stmt->execute([
                                $progress['user_id'],
                                $progress['product_id'],
                                $new_video['id'],
                                $progress['completed_at'],
                                $progress['created_at'],
                                $progress['updated_at']
                            ]);
                            break;
                        }
                    }
                }
            }
            
            // Adicionar materiais de apoio se fornecidos
            if (!empty($_POST['material_names'])) {
                // Remover materiais existentes
                $stmt = $pdo->prepare("DELETE FROM product_materials WHERE product_id = ?");
                $stmt->execute([$product_id]);
                
                // Adicionar novos materiais
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
                            
                            if (!move_uploaded_file($_FILES['material_files']['tmp_name'][$index], '../' . $file_path_material)) {
                                throw new Exception('Erro ao fazer upload do material: ' . $name);
                            }
                        } elseif ($type === 'link' && !empty($_POST['material_urls'][$index])) {
                            $external_url = $_POST['material_urls'][$index];
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
            
            $success = 'Produto atualizado com sucesso!';
            
            // Recarregar dados do produto
            $productData = $product->getProductById($product_id);
            $stmt = $pdo->prepare("SELECT plan_id FROM product_plans WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $associated_plans = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } else {
            throw new Exception('Erro ao atualizar produto');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obter categorias e planos
$categories = $product->getAllCategories();
$plans = $subscription->getAllPlans();

// Buscar vídeos e materiais existentes
$videos = $product->getProductVideos($product_id);
$materials = $product->getProductMaterials($product_id);

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
                        <h1 class="display-4 fw-bold mb-24">Editar Produto</h1>
                        <p class="text-lg text-secondary-light mb-0">
                            Edite o produto "<?= htmlspecialchars($productData['name']) ?>"
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

            <?php if (isset($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="ri-check-circle-line me-2"></i>
                    <?= htmlspecialchars($success) ?>
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
                                       value="<?= htmlspecialchars($productData['name']) ?>" 
                                       placeholder="Digite o nome do produto">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Categoria *</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Selecione uma categoria</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= $productData['category_id'] == $category['id'] ? 'selected' : '' ?>>
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
                                                   <?= in_array($plan['id'], $associated_plans) ? 'checked' : '' ?>>
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
                                               <?= $productData['individual_sale'] ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="individual_sale">
                                            <i class="ri-shopping-cart-line me-2"></i>
                                            Venda Individual
                                        </label>
                                    </div>
                                    <div class="row g-3" id="individual_sale_fields" style="display: none;">
                                        <div class="col-md-6">
                                            <label class="form-label">Preço Individual (R$)</label>
                                            <input type="number" class="form-control" name="individual_price" step="0.01" min="0" 
                                                   value="<?= htmlspecialchars($productData['individual_price'] ?? '0.00') ?>" 
                                                   placeholder="0.00">
                                            <small class="text-secondary-light">Preço para compra direta do produto</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição Curta</label>
                                <textarea class="form-control" name="short_description" rows="2" 
                                          placeholder="Descrição breve do produto..."><?= htmlspecialchars($productData['short_description']) ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição Completa</label>
                                <textarea class="form-control" name="full_description" rows="5" 
                                          placeholder="Descrição detalhada do produto..."><?= htmlspecialchars($productData['full_description']) ?></textarea>
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
                                    <option value="draft" <?= $productData['status'] == 'draft' ? 'selected' : '' ?>>Rascunho</option>
                                    <option value="active" <?= $productData['status'] == 'active' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="inactive" <?= $productData['status'] == 'inactive' ? 'selected' : '' ?>>Inativo</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Downloads por Usuário</label>
                                <input type="number" class="form-control" name="max_downloads_per_user" min="-1" 
                                       value="<?= htmlspecialchars($productData['max_downloads_per_user']) ?>" 
                                       placeholder="-1 = ilimitado">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tags</label>
                                <input type="text" class="form-control" name="tags" 
                                       value="<?= htmlspecialchars($productData['tags']) ?>" 
                                       placeholder="tag1, tag2, tag3">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="featured" value="1" 
                                           <?= $productData['featured'] ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        Produto em Destaque
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Arquivos e Imagens -->
                <div class="card mb-24">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-file-line me-2"></i>
                            Arquivos e Imagens
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Arquivo Principal</label>
                                <?php if ($productData['file_path']): ?>
                                    <div class="mb-2">
                                        <small class="text-success">Arquivo atual: <?= basename($productData['file_path']) ?></small>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="product_file">
                                <small class="text-secondary-light">Deixe em branco para manter o arquivo atual</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Imagem do Produto</label>
                                <?php if ($productData['image_path'] && file_exists('../' . $productData['image_path'])): ?>
                                    <div class="mb-2">
                                        <img src="../<?= htmlspecialchars($productData['image_path']) ?>" 
                                             alt="Imagem atual" class="img-thumbnail" style="max-width: 100px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="product_image" accept="image/*">
                                <small class="text-secondary-light">Deixe em branco para manter a imagem atual</small>
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
                                       value="<?= htmlspecialchars($productData['video_apresentacao']) ?>" 
                                       placeholder="Ex: i6gEcyFmJDc?si=sYI499sHHK5tFKCZ">
                                <small class="text-neutral-600">Para o vídeo 'https://youtu.be/i6gEcyFmJDc?si=sYI499sHHK5tFKCZ', use apenas 'i6gEcyFmJDc?si=sYI499sHHK5tFKCZ'</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Thumbnail do Vídeo (URL)</label>
                                <input type="url" class="form-control" name="video_thumbnail" 
                                       value="<?= htmlspecialchars($productData['video_thumbnail']) ?>" 
                                       placeholder="https://...">
                                <small class="text-neutral-600">URL da imagem de capa do vídeo</small>
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
                                       value="<?= htmlspecialchars($productData['version'] ?? '') ?>" 
                                       placeholder="Ex: 1.0, v2.1, 3.0.1">
                                <small class="text-neutral-600">Versão atual do produto</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data da Última Atualização</label>
                                <input type="date" class="form-control" name="last_updated" 
                                       value="<?= htmlspecialchars($productData['last_updated'] ?? '') ?>">
                                <small class="text-neutral-600">Quando foi a última atualização</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">URL da Demonstração</label>
                                <input type="url" class="form-control" name="demo_url" 
                                       value="<?= htmlspecialchars($productData['demo_url'] ?? '') ?>" 
                                       placeholder="https://demo.meusite.com/produto">
                                <small class="text-neutral-600">Link para demonstração online do produto</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Requisitos para Instalação</label>
                                <textarea class="form-control" name="requirements" rows="4" 
                                          placeholder="Ex: PHP 7.4+, MySQL 5.7+, Apache/Nginx, Extensões: GD, PDO, cURL"><?= htmlspecialchars($productData['requirements'] ?? '') ?></textarea>
                                <small class="text-neutral-600">Liste os requisitos técnicos necessários</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vídeos (Aulas) -->
                <div class="card mb-24">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="ri-video-add-line me-2"></i>
                            Vídeos (Aulas)
                        </h5>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addVideo()">
                            <i class="ri-add-line me-1"></i>Adicionar Vídeo
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="videos-container">
                            <?php if (!empty($videos)): ?>
                                <?php foreach ($videos as $index => $video): ?>
                                <div class="card mb-3" id="video-<?= $index ?>">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <label class="form-label">Título</label>
                                                <input type="text" class="form-control" name="video_titles[]" 
                                                       value="<?= htmlspecialchars($video['title']) ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">URL do Vídeo</label>
                                                <input type="url" class="form-control" name="video_urls[]" 
                                                       value="<?= htmlspecialchars($video['youtube_url']) ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Duração (min)</label>
                                                <input type="number" class="form-control" name="video_durations[]" 
                                                       value="<?= htmlspecialchars($video['duration']) ?>" min="0">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Descrição</label>
                                                <input type="text" class="form-control" name="video_descriptions[]" 
                                                       value="<?= htmlspecialchars($video['description']) ?>">
                                            </div>
                                            <div class="col-md-1">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-danger w-100" onclick="removeVideo(<?= $index ?>)">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div id="no-videos-message" class="text-center py-4">
                                    <i class="ri-video-line text-neutral-400" style="font-size: 3rem;"></i>
                                    <p class="text-neutral-600 mt-2">Nenhum vídeo adicionado</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Materiais de Apoio -->
                <div class="card mb-24">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="ri-attachment-line me-2"></i>
                            Materiais de Apoio
                        </h5>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addMaterial()">
                            <i class="ri-add-line me-1"></i>Adicionar Material
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="materials-container">
                            <?php if (!empty($materials)): ?>
                                <?php foreach ($materials as $index => $material): ?>
                                <div class="card mb-3" id="material-<?= $index ?>">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-2">
                                                <label class="form-label">Nome</label>
                                                <input type="text" class="form-control" name="material_names[]" 
                                                       value="<?= htmlspecialchars($material['name']) ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Tipo</label>
                                                <select class="form-select" name="material_types[]" onchange="toggleMaterialType(<?= $index ?>)">
                                                    <option value="file" <?= $material['type'] == 'file' ? 'selected' : '' ?>>Arquivo</option>
                                                    <option value="link" <?= $material['type'] == 'link' ? 'selected' : '' ?>>Link Externo</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2" id="material-file-<?= $index ?>" <?= $material['type'] == 'link' ? 'style="display:none;"' : '' ?>>
                                                <label class="form-label">Arquivo</label>
                                                <?php if ($material['file_path']): ?>
                                                    <div class="mb-2">
                                                        <small class="text-success">Arquivo atual: <?= basename($material['file_path']) ?></small>
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" class="form-control" name="material_files[]">
                                            </div>
                                            <div class="col-md-2" id="material-link-<?= $index ?>" <?= $material['type'] == 'file' ? 'style="display:none;"' : '' ?>>
                                                <label class="form-label">URL Externa</label>
                                                <input type="url" class="form-control" name="material_urls[]" 
                                                       value="<?= htmlspecialchars($material['external_url']) ?>" 
                                                       placeholder="https://...">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Liberação Gradual</label>
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" name="material_gradual_release[]" value="1" 
                                                           id="gradual-<?= $index ?>" <?= $material['is_gradual_release'] ? 'checked' : '' ?> 
                                                           onchange="toggleReleaseDays(<?= $index ?>)">
                                                    <label class="form-check-label" for="gradual-<?= $index ?>">
                                                        Liberação gradual
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-1" id="release-days-<?= $index ?>" <?= !$material['is_gradual_release'] ? 'style="display:none;"' : '' ?>>
                                                <label class="form-label">Dias</label>
                                                <input type="number" class="form-control" name="material_release_days[]" 
                                                       min="0" max="365" value="<?= $material['release_days'] ?>" placeholder="7">
                                            </div>
                                            <div class="col-md-1">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-danger w-100" onclick="removeMaterial(<?= $index ?>)">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div id="no-materials-message" class="text-center py-4">
                                    <i class="ri-attachment-line text-neutral-400" style="font-size: 3rem;"></i>
                                    <p class="text-neutral-600 mt-2">Nenhum material adicionado</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="d-flex gap-3 mb-24">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="ri-save-line me-2"></i>
                        Salvar Alterações
                    </button>
                    <a href="produtos.php" class="btn btn-outline-secondary btn-lg">
                        <i class="ri-arrow-left-line me-2"></i>
                        Voltar
                    </a>
                </div>
            </form>
        </div>
    </main>

    <?php include '../partials/scripts.php' ?>
    
    <script>
        let videoIndex = <?= count($videos) ?>;
        let materialIndex = <?= count($materials) ?>;
        
        function addVideo() {
            const container = document.getElementById("videos-container");
            const videoHtml = `
                <div class="card mb-3" id="video-${videoIndex}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Título</label>
                                <input type="text" class="form-control" name="video_titles[]" placeholder="Título do vídeo" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">URL do Vídeo</label>
                                <input type="url" class="form-control" name="video_urls[]" placeholder="https://..." required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Duração (min)</label>
                                <input type="number" class="form-control" name="video_durations[]" placeholder="0" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Descrição</label>
                                <input type="text" class="form-control" name="video_descriptions[]" placeholder="Descrição do vídeo">
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
                if (noVideosMessage) noVideosMessage.style.display = "none";
            } else {
                if (noVideosMessage) noVideosMessage.style.display = "block";
            }
            
            if (materialsContainer.children.length > 0) {
                if (noMaterialsMessage) noMaterialsMessage.style.display = "none";
            } else {
                if (noMaterialsMessage) noMaterialsMessage.style.display = "block";
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
