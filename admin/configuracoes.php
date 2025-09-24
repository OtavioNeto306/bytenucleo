<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/SiteConfig.php';

// Verificar se a tabela settings existe
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
} catch (PDOException $e) {
    die("Erro: A tabela 'settings' não existe. Execute o arquivo SQL primeiro.");
}

$auth = new Auth($pdo);
$siteConfig = new SiteConfig($pdo);

// Verificar se usuário está logado e tem permissão
if (!$auth->isLoggedIn() || !$auth->hasPermission('manage_system')) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Obter grupos de configurações
$groups = $siteConfig->getGroups();
$currentGroup = $_GET['group'] ?? 'general';
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php 
// Definir variáveis necessárias para os partials
if (!isset($siteConfig)) {
    $siteConfig = new SiteConfig($pdo);
}
if (!isset($auth)) {
    $auth = new Auth($pdo);
}

// Verificar se os partials existem
$headPath = '../partials/head.php';
$sidebarPath = '../partials/sidebar.php';
$navbarPath = '../partials/navbar.php';
$footerPath = '../partials/footer.php';
$scriptsPath = '../partials/scripts.php';

if (!file_exists($headPath)) {
    die("Erro: Arquivo head.php não encontrado em: " . realpath($headPath));
}
if (!file_exists($sidebarPath)) {
    die("Erro: Arquivo sidebar.php não encontrado em: " . realpath($sidebarPath));
}
if (!file_exists($navbarPath)) {
    die("Erro: Arquivo navbar.php não encontrado em: " . realpath($navbarPath));
}
if (!file_exists($footerPath)) {
    die("Erro: Arquivo footer.php não encontrado em: " . realpath($footerPath));
}
if (!file_exists($scriptsPath)) {
    die("Erro: Arquivo scripts.php não encontrado em: " . realpath($scriptsPath));
}

include $headPath;
?>

<body>

    <?php include $sidebarPath; ?>

    <main class="dashboard-main">
        <?php include $navbarPath; ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Configurações do Site</h1>
                        <p class="text-lg text-secondary-light mb-0">
                            Gerencie as configurações gerais, branding e informações de contato
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Configurações -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <!-- Menu lateral -->
                    <div class="col-lg-3 mb-40">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Categorias</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php 
                                    // Traduzir nomes dos grupos
                                    $groupTranslations = [
                                        'general' => 'Geral',
                                        'branding' => 'Marca',
                                        'contact' => 'Contato',
                                        'social' => 'Redes Sociais',
                                        'system' => 'Sistema'
                                    ];
                                    
                                    foreach ($groups as $group): 
                                        $groupName = $groupTranslations[$group['setting_group']] ?? ucfirst($group['setting_group']);
                                    ?>
                                    <a href="?group=<?= $group['setting_group'] ?>" 
                                       class="list-group-item list-group-item-action <?= $currentGroup === $group['setting_group'] ? 'active' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><?= $groupName ?></span>
                                            <span class="badge bg-secondary rounded-pill"><?= $group['count'] ?></span>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulário de configurações -->
                    <div class="col-lg-9">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0"><?= $groupTranslations[$currentGroup] ?? ucfirst($currentGroup) ?></h5>
                            </div>
                            <div class="card-body p-32">
                                <form id="configForm" data-group="<?= $currentGroup ?>">
                                    <input type="hidden" name="group" value="<?= $currentGroup ?>">
                                    <?php
                                    $settings = $siteConfig->getByGroup($currentGroup);
                                    
                                    if ($currentGroup === 'payment'):
                                        // Separar configurações principais das secundárias
                                        $mainConfigs = [];
                                        $mercadopagoConfigs = [];
                                        $offlineConfigs = [];
                                        
                                        foreach ($settings as $setting) {
                                            if (strpos($setting['setting_key'], 'mercadopago_') === 0) {
                                                if ($setting['setting_key'] === 'mercadopago_enabled') {
                                                    $mainConfigs[] = $setting;
                                                } else {
                                                    $mercadopagoConfigs[] = $setting;
                                                }
                                            } elseif (strpos($setting['setting_key'], 'offline_') === 0 || 
                                                     strpos($setting['setting_key'], 'pix_') === 0 || 
                                                     strpos($setting['setting_key'], 'bank_') === 0) {
                                                if ($setting['setting_key'] === 'offline_payments_enabled') {
                                                    $mainConfigs[] = $setting;
                                                } else {
                                                    $offlineConfigs[] = $setting;
                                                }
                                            } else {
                                                $mainConfigs[] = $setting;
                                            }
                                        }
                                    ?>
                                        
                                        <!-- Checkboxes principais -->
                                        <?php foreach ($mainConfigs as $setting): ?>
                                        <div class="mb-32">
                                            <label class="form-label fw-bold">
                                                <?= htmlspecialchars($setting['setting_label']) ?>
                                            </label>
                                            <small class="text-neutral-600 d-block mb-8">
                                                <?= htmlspecialchars($setting['setting_description']) ?>
                                            </small>
                                            
                                            <?php if ($setting['setting_type'] === 'checkbox'): ?>
                                                <div class="form-check">
                                                    <input 
                                                        type="checkbox" 
                                                        name="<?= $setting['setting_key'] ?>" 
                                                        class="form-check-input payment-main-checkbox" 
                                                        id="<?= $setting['setting_key'] ?>"
                                                        value="1"
                                                        <?= ($setting['setting_value'] == '1') ? 'checked' : '' ?>
                                                    >
                                                    <label class="form-check-label" for="<?= $setting['setting_key'] ?>">
                                                        Ativar esta opção
                                                    </label>
                                                </div>
                                            <?php else: ?>
                                                <input 
                                                    type="text" 
                                                    name="<?= $setting['setting_key'] ?>" 
                                                    class="form-control" 
                                                    value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                    placeholder="Digite o valor..."
                                                >
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <!-- Configurações do Mercado Pago (expandíveis) -->
                                        <?php if (!empty($mercadopagoConfigs)): ?>
                                        <div id="mercadopago_config" class="mb-32" style="display: none;">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-header bg-base">
                                                    <h6 class="mb-0">Configurações do Mercado Pago</h6>
                                                </div>
                                                <div class="card-body">
                                                    <?php foreach ($mercadopagoConfigs as $setting): ?>
                                                    <div class="mb-16">
                                                        <label class="form-label fw-semibold">
                                                            <?= htmlspecialchars($setting['setting_label']) ?>
                                                        </label>
                                                        <small class="text-neutral-600 d-block mb-8">
                                                            <?= htmlspecialchars($setting['setting_description']) ?>
                                                        </small>
                                                        
                                                        <?php if ($setting['setting_type'] === 'password'): ?>
                                                            <input 
                                                                type="password" 
                                                                name="<?= $setting['setting_key'] ?>" 
                                                                class="form-control" 
                                                                value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                                placeholder="Digite o token de acesso do Mercado Pago..."
                                                            >
                                                        <?php elseif ($setting['setting_type'] === 'checkbox'): ?>
                                                            <div class="form-check">
                                                                <input 
                                                                    type="checkbox" 
                                                                    name="<?= $setting['setting_key'] ?>" 
                                                                    class="form-check-input" 
                                                                    id="<?= $setting['setting_key'] ?>"
                                                                    value="1"
                                                                    <?= ($setting['setting_value'] == '1') ? 'checked' : '' ?>
                                                                >
                                                                <label class="form-check-label" for="<?= $setting['setting_key'] ?>">
                                                                    Ativar esta opção
                                                                </label>
                                                            </div>
                                                        <?php else: ?>
                                                            <input 
                                                                type="text" 
                                                                name="<?= $setting['setting_key'] ?>" 
                                                                class="form-control" 
                                                                value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                                placeholder="<?= strpos($setting['setting_key'], 'public_key') !== false ? 'Digite a chave pública do Mercado Pago...' : 'Digite o valor...' ?>"
                                                            >
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Configurações Offline (expandíveis) -->
                                        <?php if (!empty($offlineConfigs)): ?>
                                        <div id="offline_config" class="mb-32" style="display: none;">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-header bg-base">
                                                    <h6 class="mb-0">Configurações de Pagamento Offline</h6>
                                                </div>
                                                <div class="card-body">
                                                    <?php foreach ($offlineConfigs as $setting): ?>
                                                    <div class="mb-16">
                                                        <label class="form-label fw-semibold">
                                                            <?= htmlspecialchars($setting['setting_label']) ?>
                                                        </label>
                                                        <small class="text-neutral-600 d-block mb-8">
                                                            <?= htmlspecialchars($setting['setting_description']) ?>
                                                        </small>
                                                        
                                                        <?php if ($setting['setting_type'] === 'checkbox'): ?>
                                                            <div class="form-check">
                                                                <input 
                                                                    type="checkbox" 
                                                                    name="<?= $setting['setting_key'] ?>" 
                                                                    class="form-check-input" 
                                                                    id="<?= $setting['setting_key'] ?>"
                                                                    value="1"
                                                                    <?= ($setting['setting_value'] == '1') ? 'checked' : '' ?>
                                                                >
                                                                <label class="form-check-label" for="<?= $setting['setting_key'] ?>">
                                                                    Ativar esta opção
                                                                </label>
                                                            </div>
                                                        <?php else: ?>
                                                            <input 
                                                                type="text" 
                                                                name="<?= $setting['setting_key'] ?>" 
                                                                class="form-control" 
                                                                value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                                placeholder="Digite o valor..."
                                                            >
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                    <?php else: ?>
                                        <!-- Layout normal para outros grupos -->
                                        <?php foreach ($settings as $setting): ?>
                                        <div class="mb-32">
                                            <label class="form-label fw-bold">
                                                <?= htmlspecialchars($setting['setting_label']) ?>
                                            </label>
                                            <small class="text-neutral-600 d-block mb-8">
                                                <?= htmlspecialchars($setting['setting_description']) ?>
                                            </small>
                                            
                                            <?php if ($setting['setting_type'] === 'textarea'): ?>
                                                <textarea 
                                                    name="<?= $setting['setting_key'] ?>" 
                                                    class="form-control" 
                                                    rows="3"
                                                    placeholder="Digite o valor..."
                                                ><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                            
                                            <?php elseif ($setting['setting_type'] === 'image'): ?>
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <input 
                                                            type="text" 
                                                            name="<?= $setting['setting_key'] ?>" 
                                                            class="form-control" 
                                                            value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                            placeholder="Caminho da imagem..."
                                                        >
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button type="button" class="btn btn-outline-primary btn-upload" 
                                                                data-input="<?= $setting['setting_key'] ?>">
                                                            <i class="ri-upload-line me-8"></i>
                                                            Upload
                                                        </button>
                                                    </div>
                                                </div>
                                                <?php if ($setting['setting_value']): ?>
                                                <div class="mt-12">
                                                    <img src="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                                         class="img-thumbnail" style="max-width: 200px; max-height: 100px;"
                                                         onerror="this.style.display='none'">
                                                </div>
                                                <?php endif; ?>
                                            
                                            <?php elseif ($setting['setting_type'] === 'email'): ?>
                                                <input 
                                                    type="email" 
                                                    name="<?= $setting['setting_key'] ?>" 
                                                    class="form-control" 
                                                    value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                    placeholder="exemplo@email.com"
                                                >
                                            
                                            <?php elseif ($setting['setting_type'] === 'url'): ?>
                                                <input 
                                                    type="url" 
                                                    name="<?= $setting['setting_key'] ?>" 
                                                    class="form-control" 
                                                    value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                    placeholder="https://exemplo.com"
                                                >
                                            
                                            <?php elseif ($setting['setting_type'] === 'password'): ?>
                                                <input 
                                                    type="password" 
                                                    name="<?= $setting['setting_key'] ?>" 
                                                    class="form-control" 
                                                    value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                    placeholder="Digite a senha..."
                                                >
                                            
                                            <?php elseif ($setting['setting_type'] === 'checkbox'): ?>
                                                <div class="form-check">
                                                    <input 
                                                        type="checkbox" 
                                                        name="<?= $setting['setting_key'] ?>" 
                                                        class="form-check-input" 
                                                        id="<?= $setting['setting_key'] ?>"
                                                        value="1"
                                                        <?= ($setting['setting_value'] == '1') ? 'checked' : '' ?>
                                                    >
                                                    <label class="form-check-label" for="<?= $setting['setting_key'] ?>">
                                                        Ativar esta opção
                                                    </label>
                                                </div>
                                            
                                            <?php else: ?>
                                                <input 
                                                    type="text" 
                                                    name="<?= $setting['setting_key'] ?>" 
                                                    class="form-control" 
                                                    value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                    placeholder="Digite o valor..."
                                                >
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                            <i class="ri-refresh-line me-8"></i>
                                            Restaurar Padrão
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ri-save-line me-8"></i>
                                            Salvar Configurações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php include $footerPath; ?>
    </main>

    <!-- Modal de Upload -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload de Imagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <div class="mb-24">
                            <label class="form-label">Selecionar Imagem</label>
                            <input type="file" name="image" class="form-control" accept="image/*" required>
                            <small class="text-neutral-600">Formatos aceitos: PNG, JPG, JPEG, GIF. Tamanho máximo: 2MB</small>
                        </div>
                        <div class="mb-24">
                            <label class="form-label">Nome do Arquivo (opcional)</label>
                            <input type="text" name="filename" class="form-control" placeholder="Deixe em branco para usar o nome original">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="uploadImage()">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <?php include $scriptsPath; ?>

    <script>
    let currentUploadInput = '';

    // Salvar configurações via AJAX
    $('#configForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const group = $(this).data('group');
        
        // Mostrar loading
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="ri-loader-4-line me-8"></i>Salvando...').prop('disabled', true);
        
        $.ajax({
            url: 'ajax/save_config.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'Configurações salvas com sucesso!');
                } else {
                    showAlert('danger', 'Erro ao salvar: ' + response.message);
                }
            },
            error: function() {
                showAlert('danger', 'Erro de conexão. Tente novamente.');
            },
            complete: function() {
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });

    // Upload de imagem
    $('.btn-upload').on('click', function() {
        currentUploadInput = $(this).data('input');
        $('#uploadModal').modal('show');
    });

    function uploadImage() {
        const formData = new FormData($('#uploadForm')[0]);
        
        $.ajax({
            url: 'ajax/upload_image.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $(`input[name="${currentUploadInput}"]`).val(response.file_path);
                    $('#uploadModal').modal('hide');
                    showAlert('success', 'Imagem enviada com sucesso!');
                } else {
                    showAlert('danger', 'Erro no upload: ' + response.message);
                }
            },
            error: function() {
                showAlert('danger', 'Erro de conexão. Tente novamente.');
            }
        });
    }

    // Restaurar configurações padrão
    function resetForm() {
        if (confirm('Tem certeza que deseja restaurar as configurações padrão? Esta ação não pode ser desfeita.')) {
            const group = $('#configForm').data('group');
            
            $.ajax({
                url: 'ajax/reset_config.php',
                type: 'POST',
                data: { group: group },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        showAlert('danger', 'Erro ao restaurar: ' + response.message);
                    }
                },
                error: function() {
                    showAlert('danger', 'Erro de conexão. Tente novamente.');
                }
            });
        }
    }

    // Função para mostrar alertas
    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Remover alertas anteriores
        $('.alert').remove();
        
        // Adicionar novo alerta
        $('.container').first().prepend(alertHtml);
        
        // Auto-remover após 5 segundos
        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
    }
    
    // Controlar exibição das configurações de pagamento
    function togglePaymentConfigs() {
        const mercadopagoEnabled = document.getElementById('mercadopago_enabled');
        const offlineEnabled = document.getElementById('offline_payments_enabled');
        
        if (mercadopagoEnabled) {
            const mercadopagoConfig = document.getElementById('mercadopago_config');
            if (mercadopagoConfig) {
                mercadopagoConfig.style.display = mercadopagoEnabled.checked ? 'block' : 'none';
            }
        }
        
        if (offlineEnabled) {
            const offlineConfig = document.getElementById('offline_config');
            if (offlineConfig) {
                offlineConfig.style.display = offlineEnabled.checked ? 'block' : 'none';
            }
        }
    }
    
    // Event listeners para checkboxes de pagamento
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar estado dos checkboxes
        togglePaymentConfigs();
        
        // Adicionar listeners aos checkboxes
        const mercadopagoCheckbox = document.getElementById('mercadopago_enabled');
        const offlineCheckbox = document.getElementById('offline_payments_enabled');
        
        if (mercadopagoCheckbox) {
            mercadopagoCheckbox.addEventListener('change', togglePaymentConfigs);
        }
        
        if (offlineCheckbox) {
            offlineCheckbox.addEventListener('change', togglePaymentConfigs);
        }
    });
    </script>

</body>
</html>
