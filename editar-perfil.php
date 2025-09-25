<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/SiteConfig.php';

$auth = new Auth($pdo);
$siteConfig = new SiteConfig($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user = $auth->getCurrentUser();
$error = '';
$success = '';

// Processar formulário de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // Validações
        if (empty($name)) {
            $error = 'Nome é obrigatório';
        } elseif (empty($email)) {
            $error = 'Email é obrigatório';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido';
        } else {
            try {
                // Verificar se email já existe (exceto para o usuário atual)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user['id']]);
                if ($stmt->rowCount() > 0) {
                    $error = 'Este email já está em uso';
                } else {
                    // Atualizar perfil
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, bio = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $bio, $user['id']]);
                    $success = 'Perfil atualizado com sucesso!';
                    
                    // Atualizar dados da sessão
                    $user = $auth->getCurrentUser();
                }
            } catch (PDOException $e) {
                $error = 'Erro ao atualizar perfil: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Todos os campos de senha são obrigatórios';
        } elseif ($new_password !== $confirm_password) {
            $error = 'As senhas não coincidem';
        } elseif (strlen($new_password) < 6) {
            $error = 'A nova senha deve ter pelo menos 6 caracteres';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Senha atual incorreta';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user['id']]);
                $success = 'Senha alterada com sucesso!';
            } catch (PDOException $e) {
                $error = 'Erro ao alterar senha: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Tipo de arquivo não permitido. Use apenas JPG, PNG ou GIF';
            } elseif ($file['size'] > $max_size) {
                $error = 'Arquivo muito grande. Máximo 5MB';
            } else {
                $upload_dir = 'uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                        $stmt->execute([$filepath, $user['id']]);
                        $success = 'Avatar atualizado com sucesso!';
                        $user = $auth->getCurrentUser(); // Atualizar dados
                    } catch (PDOException $e) {
                        $error = 'Erro ao salvar avatar: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Erro ao fazer upload do arquivo';
                }
            }
        } else {
            $error = 'Nenhum arquivo selecionado';
        }
    }
}
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
                <h6 class="fw-semibold mb-0">Editar Perfil</h6>
                <ul class="d-flex align-items-center gap-2">
                    <li class="fw-medium">
                        <a href="perfil.php" class="d-flex align-items-center gap-1 hover-text-primary">
                            <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                            Meu Perfil
                        </a>
                    </li>
                    <li>-</li>
                    <li class="fw-medium">Editar Perfil</li>
                </ul>
            </div>
        </div>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Editar Perfil</h1>
                                                                        <p class="text-lg text-secondary-light mb-0">
                            Atualize suas informações pessoais e configurações da conta
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="card h-100">
                        <div class="card-body p-24">
                            
                            <!-- Alertas -->
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($error) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($success) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <ul class="nav nav-pills mb-20" id="pills-tab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link d-flex align-items-center active" id="pills-edit-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-edit-profile" type="button" role="tab" aria-controls="pills-edit-profile" aria-selected="true">
                                        <iconify-icon icon="solar:user-outline" class="me-8"></iconify-icon>
                                        Editar Perfil
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link d-flex align-items-center" id="pills-change-password-tab" data-bs-toggle="pill" data-bs-target="#pills-change-password" type="button" role="tab" aria-controls="pills-change-password" aria-selected="false" tabindex="-1">
                                        <iconify-icon icon="solar:lock-outline" class="me-8"></iconify-icon>
                                        Alterar Senha
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link d-flex align-items-center" id="pills-notification-tab" data-bs-toggle="pill" data-bs-target="#pills-notification" type="button" role="tab" aria-controls="pills-notification" aria-selected="false" tabindex="-1">
                                        <iconify-icon icon="solar:bell-outline" class="me-8"></iconify-icon>
                                        Notificações
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="pills-tabContent">
                                
                                <!-- Tab: Editar Perfil -->
                                <div class="tab-pane fade show active" id="pills-edit-profile" role="tabpanel" aria-labelledby="pills-edit-profile-tab" tabindex="0">
                                                                         <h6 class="text-md text-primary-light mb-16">Foto do Perfil</h6>
                                     
                                     <!-- Upload Avatar -->
                                     <div class="mb-24 mt-16">
                                         <div class="avatar-upload">
                                             <div class="avatar-edit position-absolute bottom-0 end-0 me-24 mt-16 z-1 cursor-pointer">
                                                 <input type='file' id="imageUpload" accept=".png, .jpg, .jpeg" hidden>
                                                                                                 <label for="imageUpload" class="w-32-px h-32-px d-flex justify-content-center align-items-center bg-primary-50 text-primary-600 border border-primary-600 bg-hover-primary-100 text-lg rounded-circle">
                                                    <i class="ri-camera-line"></i>
                                                </label>
                                             </div>
                                             <div class="avatar-preview">
                                                 <div id="imagePreview" style="background-image: url('<?= !empty($user['avatar']) && file_exists($user['avatar']) ? htmlspecialchars($user['avatar']) : '' ?>');">
                                                 </div>
                                             </div>
                                         </div>
                                         
                                         <!-- Formulário de Upload -->
                                         <form id="avatarForm" method="POST" enctype="multipart/form-data" class="mt-16">
                                             <input type="hidden" name="action" value="upload_avatar">
                                             <input type="file" id="avatarFileInput" name="avatar" accept=".png, .jpg, .jpeg, .gif" style="display: none;">
                                             <button type="button" class="btn btn-sm btn-primary d-flex align-items-center" onclick="document.getElementById('avatarFileInput').click()">
                                                 <iconify-icon icon="solar:upload-outline" class="me-8"></iconify-icon>
                                                 Selecionar Foto
                                             </button>
                                             <button type="submit" id="submitAvatarBtn" class="btn btn-sm btn-success ms-2 d-flex align-items-center">
                                                 <iconify-icon icon="solar:check-circle-outline" class="me-8"></iconify-icon>
                                                 Enviar Foto
                                             </button>
                                         </form>
                                     </div>
                                    
                                    <!-- Formulário de Perfil -->
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="update_profile">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="mb-20">
                                                    <label for="name" class="form-label fw-semibold text-primary-light text-sm mb-8">Nome Completo <span class="text-danger-600">*</span></label>
                                                    <input type="text" class="form-control radius-8" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="Digite seu nome completo" required>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-20">
                                                    <label for="email" class="form-label fw-semibold text-primary-light text-sm mb-8">Email <span class="text-danger-600">*</span></label>
                                                    <input type="email" class="form-control radius-8" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="Digite seu email" required>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-20">
                                                    <label for="phone" class="form-label fw-semibold text-primary-light text-sm mb-8">Telefone</label>
                                                    <input type="tel" class="form-control radius-8" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Digite seu telefone">
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-20">
                                                    <label for="role" class="form-label fw-semibold text-primary-light text-sm mb-8">Nível de Acesso</label>
                                                    <input type="text" class="form-control radius-8" id="role" value="<?= htmlspecialchars($auth->getRoleName($user['role_id'])) ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-sm-12">
                                                <div class="mb-20">
                                                    <label for="bio" class="form-label fw-semibold text-primary-light text-sm mb-8">Biografia</label>
                                                    <textarea class="form-control radius-8" id="bio" name="bio" rows="4" placeholder="Conte um pouco sobre você..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center gap-3">
                                            <a href="/perfil.php" class="btn btn-outline-danger d-flex align-items-center">
                                                <iconify-icon icon="solar:close-circle-outline" class="me-8"></iconify-icon>
                                                Cancelar
                                            </a>
                                            <button type="submit" class="btn btn-primary d-flex align-items-center">
                                                <iconify-icon icon="solar:check-circle-outline" class="me-8"></iconify-icon>
                                                Salvar Alterações
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Tab: Alterar Senha -->
                                <div class="tab-pane fade" id="pills-change-password" role="tabpanel" aria-labelledby="pills-change-password-tab" tabindex="0">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="change_password">
                                        <div class="mb-20">
                                            <label for="current_password" class="form-label fw-semibold text-primary-light text-sm mb-8">Senha Atual <span class="text-danger-600">*</span></label>
                                            <div class="position-relative">
                                                <input type="password" class="form-control radius-8" id="current_password" name="current_password" placeholder="Digite sua senha atual" required>
                                                <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light" data-toggle="#current_password"></span>
                                            </div>
                                        </div>
                                        <div class="mb-20">
                                            <label for="new_password" class="form-label fw-semibold text-primary-light text-sm mb-8">Nova Senha <span class="text-danger-600">*</span></label>
                                            <div class="position-relative">
                                                <input type="password" class="form-control radius-8" id="new_password" name="new_password" placeholder="Digite a nova senha" required>
                                                <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light" data-toggle="#new_password"></span>
                                            </div>
                                        </div>
                                        <div class="mb-20">
                                            <label for="confirm_password" class="form-label fw-semibold text-primary-light text-sm mb-8">Confirmar Nova Senha <span class="text-danger-600">*</span></label>
                                            <div class="position-relative">
                                                <input type="password" class="form-control radius-8" id="confirm_password" name="confirm_password" placeholder="Confirme a nova senha" required>
                                                <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light" data-toggle="#confirm_password"></span>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-center gap-3">
                                            <button type="button" class="btn btn-outline-secondary d-flex align-items-center" onclick="resetPasswordForm()">
                                                <iconify-icon icon="solar:refresh-outline" class="me-8"></iconify-icon>
                                                Limpar
                                            </button>
                                            <button type="submit" class="btn btn-primary d-flex align-items-center">
                                                <iconify-icon icon="solar:lock-password-outline" class="me-8"></iconify-icon>
                                                Alterar Senha
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Tab: Notificações -->
                                <div class="tab-pane fade" id="pills-notification" role="tabpanel" aria-labelledby="pills-notification-tab" tabindex="0">
                                    <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                        <label for="emailNotifications" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                        <div class="d-flex align-items-center gap-3 justify-content-between">
                                            <span class="form-check-label line-height-1 fw-medium text-secondary-light">Notificações por Email</span>
                                            <input class="form-check-input" type="checkbox" role="switch" id="emailNotifications" checked>
                                        </div>
                                    </div>
                                    <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                        <label for="productUpdates" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                        <div class="d-flex align-items-center gap-3 justify-content-between">
                                            <span class="form-check-label line-height-1 fw-medium text-secondary-light">Atualizações de Produtos</span>
                                            <input class="form-check-input" type="checkbox" role="switch" id="productUpdates" checked>
                                        </div>
                                    </div>
                                    <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                        <label for="subscriptionAlerts" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                        <div class="d-flex align-items-center gap-3 justify-content-between">
                                            <span class="form-check-label line-height-1 fw-medium text-secondary-light">Alertas de Assinatura</span>
                                            <input class="form-check-input" type="checkbox" role="switch" id="subscriptionAlerts" checked>
                                        </div>
                                    </div>
                                    <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                        <label for="newsletter" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                        <div class="d-flex align-items-center gap-3 justify-content-between">
                                            <span class="form-check-label line-height-1 fw-medium text-secondary-light">Newsletter Semanal</span>
                                            <input class="form-check-input" type="checkbox" role="switch" id="newsletter">
                                        </div>
                                    </div>
                                    <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                        <label for="promotionalOffers" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                        <div class="d-flex align-items-center gap-3 justify-content-between">
                                            <span class="form-check-label line-height-1 fw-medium text-secondary-light">Ofertas Promocionais</span>
                                            <input class="form-check-input" type="checkbox" role="switch" id="promotionalOffers">
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center justify-content-center gap-3 mt-24">
                                        <button type="button" class="btn btn-outline-secondary d-flex align-items-center" onclick="resetNotificationSettings()">
                                            <iconify-icon icon="solar:refresh-outline" class="me-8"></iconify-icon>
                                            Restaurar Padrão
                                        </button>
                                        <button type="button" class="btn btn-primary d-flex align-items-center" onclick="saveNotificationSettings()">
                                            <iconify-icon icon="solar:check-circle-outline" class="me-8"></iconify-icon>
                                            Salvar Configurações
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <?php include './partials/scripts.php' ?>

                                       <script>
                    $(document).ready(function() {
                        // Garantir que o botão de enviar esteja oculto inicialmente
                        $("#submitAvatarBtn").hide();
                        
                        // Verificar se já existe uma foto de perfil
                        var currentAvatar = $("#imagePreview").css("background-image");
                        if (currentAvatar && currentAvatar !== "none" && currentAvatar !== "") {
                            // Se já tem foto, mostrar o botão
                            $("#submitAvatarBtn").show();
                        }
                        
                        // Preview da imagem do avatar
                        function readURL(input) {
                            console.log('readURL chamada com:', input.files);
                            
                            if (input.files && input.files[0]) {
                                console.log('Arquivo selecionado:', input.files[0].name);
                                
                                var reader = new FileReader();
                                reader.onload = function(e) {
                                    $("#imagePreview").css("background-image", "url(" + e.target.result + ")");
                                    $("#imagePreview").hide();
                                    $("#imagePreview").fadeIn(650);
                                    
                                    // Copiar o arquivo para o input do formulário
                                    const formInput = document.getElementById('avatarFileInput');
                                    const dataTransfer = new DataTransfer();
                                    dataTransfer.items.add(input.files[0]);
                                    formInput.files = dataTransfer.files;
                                    
                                    // Mostrar botão de enviar
                                    console.log('Mostrando botão de enviar');
                                    $("#submitAvatarBtn").fadeIn();
                                }
                                reader.readAsDataURL(input.files[0]);
                            } else {
                                // Se não há arquivo, ocultar o botão
                                console.log('Nenhum arquivo selecionado, ocultando botão');
                                $("#submitAvatarBtn").fadeOut();
                            }
                        }
                        
                        // Event listener para o botão de câmera
                        $("#imageUpload").change(function() {
                            console.log('imageUpload change event');
                            readURL(this);
                        });
                        
                        // Event listener para o botão de selecionar foto
                        $("#avatarFileInput").change(function() {
                            console.log('avatarFileInput change event');
                            readURL(this);
                        });
                        

                        
                        // Event listener para o formulário de avatar
                        $("#avatarForm").submit(function() {
                            // Mostrar loading
                            $("#submitAvatarBtn").prop('disabled', true);
                            $("#submitAvatarBtn").html('<iconify-icon icon="solar:loading-outline" class="me-8"></iconify-icon>Enviando...');
                            
                            // Após o envio, ocultar o botão
                            setTimeout(function() {
                                $("#submitAvatarBtn").fadeOut();
                                $("#submitAvatarBtn").prop('disabled', false);
                                $("#submitAvatarBtn").html('<iconify-icon icon="solar:check-circle-outline" class="me-8"></iconify-icon>Enviar Foto');
                            }, 2000);
                        });
                    });

        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-toggle');
                const target = document.querySelector(targetId);
                const type = target.type === 'password' ? 'text' : 'password';
                target.type = type;
                this.classList.toggle('ri-eye-line');
                this.classList.toggle('ri-eye-off-line');
            });
        });

        // Reset password form
        function resetPasswordForm() {
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
        }

        // Reset notification settings
        function resetNotificationSettings() {
            document.getElementById('emailNotifications').checked = true;
            document.getElementById('productUpdates').checked = true;
            document.getElementById('subscriptionAlerts').checked = true;
            document.getElementById('newsletter').checked = false;
            document.getElementById('promotionalOffers').checked = false;
        }

        // Save notification settings
        function saveNotificationSettings() {
            // Aqui você pode implementar a lógica para salvar as configurações
            alert('Configurações de notificação salvas com sucesso!');
        }
    </script>

                   <style>
                    /* Avatar simples e eficaz */
          .avatar-upload {
              display: inline-block;
          }
          
          .avatar-preview {
              display: inline-block;
          }
          
          /* Garantir que o botão de enviar foto esteja oculto por padrão */
          #submitAvatarBtn {
              display: none;
          }
          
          .border-gradient-tab {
              border: 1px solid #e9ecef;
          }
          
          .border-gradient-tab .nav-link {
              border: none;
              color: #6c757d;
          }
          
                     .border-gradient-tab .nav-link.active {
               background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
               color: white;
           }
           
           
          

      </style>

</body>
</html>
