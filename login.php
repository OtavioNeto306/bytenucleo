<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/SiteConfig.php';

$auth = new Auth($pdo);
$siteConfig = new SiteConfig($pdo);
$error = '';
$success = '';

// Buscar configurações do reCAPTCHA
$recaptchaEnabled = $siteConfig->get('recaptcha_enabled', '0') === '1';
$recaptchaSiteKey = $siteConfig->get('recaptcha_site_key', '');

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

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos';
    } elseif ($recaptchaEnabled && empty($recaptchaResponse)) {
        $error = 'Por favor, complete a verificação reCAPTCHA';
    } else {
        // Validar reCAPTCHA se estiver habilitado
        if ($recaptchaEnabled && !empty($recaptchaResponse)) {
            $recaptchaSecretKey = $siteConfig->get('recaptcha_secret_key', '');
            if (!empty($recaptchaSecretKey)) {
                $recaptchaValid = validateRecaptcha($recaptchaResponse, $recaptchaSecretKey);
                if (!$recaptchaValid) {
                    $error = 'Verificação reCAPTCHA falhou. Tente novamente.';
                }
            }
        }
        
        // Se não há erro, tentar fazer login
        if (empty($error)) {
            $result = $auth->login($email, $password);
            
            if ($result['success']) {
                // Redirecionar para a página original ou página principal
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirectUrl = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirectUrl);
                } else {
                    header('Location: index-membros');
                }
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Função para validar reCAPTCHA
function validateRecaptcha($response, $secretKey) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secretKey,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        return false;
    }
    
    $json = json_decode($result, true);
    return isset($json['success']) && $json['success'] === true;
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

<?php if ($recaptchaEnabled && !empty($recaptchaSiteKey)): ?>
<meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-inline' 'unsafe-eval' https://api.iconify.design https://api.unisvg.com https://api.simplesvg.com https://sdk.mercadopago.com https://www.google.com https://www.gstatic.com; frame-src 'self' https://www.google.com;">
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>

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
                    <h4 class="mb-12">Entrar na sua Conta</h4>
                    <p class="mb-32 text-secondary-light text-lg">Bem-vindo de volta! Por favor, insira seus dados</p>
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
                            <iconify-icon icon="mage:email"></iconify-icon>
                        </span>
                        <input type="email" name="email" class="form-control h-56-px bg-neutral-50 radius-12" 
                               placeholder="Email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="position-relative mb-20">
                        <div class="icon-field">
                            <span class="icon top-50 translate-middle-y">
                                <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                            </span>
                            <input type="password" name="password" class="form-control h-56-px bg-neutral-50 radius-12" 
                                   id="your-password" placeholder="Senha" required>
                        </div>
                        <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light" data-toggle="#your-password"></span>
                    </div>
                    <div class="">
                        <div class="d-flex justify-content-between gap-2">
                            <div class="form-check style-check d-flex align-items-center">
                                <input class="form-check-input border border-neutral-300" type="checkbox" value="" id="remeber">
                                <label class="form-check-label" for="remeber">Lembrar de mim</label>
                            </div>
                            <a href="forgot-password.php" class="text-primary-600 fw-medium">Esqueceu a senha?</a>
                        </div>
                    </div>

                    <?php if ($recaptchaEnabled && !empty($recaptchaSiteKey)): ?>
                    <div class="mt-24">
                        <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($recaptchaSiteKey) ?>"></div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32">
                        Entrar
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
                        <p class="mb-0">Não tem uma conta? <a href="register.php" class="text-primary-600 fw-semibold">Cadastre-se</a></p>
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
