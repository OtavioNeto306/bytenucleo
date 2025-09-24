<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/SiteConfig.php';

$auth = new Auth($pdo);
$siteConfig = new SiteConfig($pdo);
$error = '';
$success = '';

// Buscar links das redes sociais
$socialLinks = [];
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin')");
        $stmt->execute();
        $socialSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Mapear as chaves para nomes mais limpos
        $socialLinks = [
            'facebook' => $socialSettings['social_facebook'] ?? '',
            'twitter' => $socialSettings['social_twitter'] ?? '',
            'instagram' => $socialSettings['social_instagram'] ?? '',
            'linkedin' => $socialSettings['social_linkedin'] ?? ''
        ];
    }
} catch (Exception $e) {
    // Em caso de erro, usar array vazio
    $socialLinks = [];
}

// Processar formulário de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validações
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor, preencha todos os campos';
    } elseif ($password !== $confirm_password) {
        $error = 'As senhas não coincidem';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, insira um email válido';
    } else {
        $result = $auth->register($name, $email, $password);
        
        if ($result['success']) {
            $success = $result['message'] . ' Agora você pode fazer login.';
        } else {
            $error = $result['message'];
        }
    }
}

// Se já estiver logado, redirecionar
if ($auth->isLoggedIn()) {
    header('Location: index-membros.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include './partials/head.php' ?>

<body>

    <section class="auth bg-base d-flex flex-wrap">
        <div class="auth-left d-lg-block d-none">
            <div class="d-flex align-items-center flex-column h-100 justify-content-center">
                <img src="<?= htmlspecialchars($siteConfig->get('auth_background_image', 'assets/images/auth/auth-img.png')) ?>" alt="">
            </div>
        </div>
        <div class="auth-right py-32 px-24 d-flex flex-column justify-content-center">
            <div class="max-w-464-px mx-auto w-100">
                <div>
                    <a href="index-membros.php" class="mb-40 max-w-290-px">
                        <img src="<?= htmlspecialchars($siteConfig->get('site_logo_light', 'assets/images/logo.png')) ?>" alt="<?= htmlspecialchars($siteConfig->get('site_name', 'Área de Membros')) ?>">
                    </a>
                    <h4 class="mb-12">Criar Nova Conta</h4>
                    <p class="mb-32 text-secondary-light text-lg">Junte-se à nossa comunidade e tenha acesso a conteúdos exclusivos</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-24" role="alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success mb-24" role="alert">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="icon-field mb-16">
                        <span class="icon top-50 translate-middle-y">
                            <iconify-icon icon="solar:user-outline"></iconify-icon>
                        </span>
                        <input type="text" name="name" class="form-control h-56-px bg-neutral-50 radius-12" 
                               placeholder="Nome completo" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="icon-field mb-16">
                        <span class="icon top-50 translate-middle-y">
                            <iconify-icon icon="mage:email"></iconify-icon>
                        </span>
                        <input type="email" name="email" class="form-control h-56-px bg-neutral-50 radius-12" 
                               placeholder="Email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="position-relative mb-16">
                        <div class="icon-field">
                            <span class="icon top-50 translate-middle-y">
                                <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                            </span>
                            <input type="password" name="password" class="form-control h-56-px bg-neutral-50 radius-12" 
                                   id="password" placeholder="Senha" required>
                        </div>
                        <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light" data-toggle="#password"></span>
                    </div>
                    
                    <div class="position-relative mb-20">
                        <div class="icon-field">
                            <span class="icon top-50 translate-middle-y">
                                <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                            </span>
                            <input type="password" name="confirm_password" class="form-control h-56-px bg-neutral-50 radius-12" 
                                   id="confirm-password" placeholder="Confirmar senha" required>
                        </div>
                        <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light" data-toggle="#confirm-password"></span>
                    </div>
                    
                    <div class="mb-24">
                        <div class="form-check style-check d-flex align-items-center">
                            <input class="form-check-input border border-neutral-300" type="checkbox" value="" id="terms" required>
                            <label class="form-check-label" for="terms">
                                Eu concordo com os <a href="terms.php" class="text-primary-600">Termos de Uso</a> e 
                                <a href="privacy.php" class="text-primary-600">Política de Privacidade</a>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32">
                        Criar Conta
                    </button>

                    <div class="mt-32 text-center">
                        <p class="text-secondary-light mb-16">Siga-nos nas redes sociais</p>
                        <div class="d-flex align-items-center justify-content-center gap-16">
                            <?php if (!empty($socialLinks['facebook'])): ?>
                            <a href="<?= htmlspecialchars($socialLinks['facebook']) ?>" target="_blank" class="text-neutral-400 hover:text-primary-600 transition-colors" title="Facebook">
                                <i class="ri-facebook-fill text-xl"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($socialLinks['twitter'])): ?>
                            <a href="<?= htmlspecialchars($socialLinks['twitter']) ?>" target="_blank" class="text-neutral-400 hover:text-primary-600 transition-colors" title="Twitter">
                                <i class="ri-twitter-fill text-xl"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($socialLinks['instagram'])): ?>
                            <a href="<?= htmlspecialchars($socialLinks['instagram']) ?>" target="_blank" class="text-neutral-400 hover:text-primary-600 transition-colors" title="Instagram">
                                <i class="ri-instagram-fill text-xl"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($socialLinks['linkedin'])): ?>
                            <a href="<?= htmlspecialchars($socialLinks['linkedin']) ?>" target="_blank" class="text-neutral-400 hover:text-primary-600 transition-colors" title="LinkedIn">
                                <i class="ri-linkedin-fill text-xl"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-32 text-center text-sm">
                        <p class="mb-0">Já tem uma conta? <a href="login.php" class="text-primary-600 fw-semibold">Faça login</a></p>
                    </div>

                </form>
            </div>
        </div>
    </section>

    <?php $script = '<script>
                        // ================== Password Show Hide Js Start ==========
                        function initializePasswordToggle(toggleSelector) {
                            $(toggleSelector).on("click", function() {
                                $(this).toggleClass("ri-eye-off-line");
                                var input = $($(this).attr("data-toggle"));
                                if (input.attr("type") === "password") {
                                    input.attr("type", "text");
                                } else {
                                    input.attr("type", "password");
                                }
                            });
                        }
                        // Call the function
                        initializePasswordToggle(".toggle-password");
                        // ========================= Password Show Hide Js End ===========================
                    </script>';?>
                    
<?php include './partials/scripts.php' ?>

</body>

</html>
