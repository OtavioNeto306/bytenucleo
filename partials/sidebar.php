<?php
// Verificar se as classes Auth estão disponíveis
if (!isset($auth)) {
    // Tentar diferentes caminhos possíveis
    $possiblePaths = [
        'config/database.php',
        '../config/database.php',
        '../../config/database.php'
    ];
    
    $loaded = false;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
    
    $authPaths = [
        'includes/Auth.php',
        '../includes/Auth.php',
        '../../includes/Auth.php'
    ];
    
    foreach ($authPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $auth = new Auth($pdo);
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        // Fallback - criar objeto vazio
        $auth = (object) [
            'isLoggedIn' => function() { return false; },
            'isAdmin' => function() { return false; },
            'isSuperAdmin' => function() { return false; },
            'hasPermission' => function() { return false; }
        ];
    }
}

// Verificar se siteConfig está disponível
if (!isset($siteConfig)) {
    // Detectar se está sendo usado de admin/
    $isAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
    
    $siteConfig = (object) [
        'getLogoLight' => function() { return '/assets/images/logo.png'; },
        'getLogoDark' => function() { return '/assets/images/logo-light.png'; },
        'getLogoIcon' => function() { return '/assets/images/logo-icon.png'; },
        'getSiteName' => function() { return 'Área de Membros'; }
    ];
}
?>

<aside class="sidebar">
    <button type="button" class="sidebar-close-btn">
        <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
    </button>
    <div>
        <a href="/index-membros" class="sidebar-logo">
            <img src="<?= htmlspecialchars($siteConfig->getLogoLight()) ?>" alt="<?= htmlspecialchars($siteConfig->getSiteName()) ?>" class="light-logo">
            <img src="<?= htmlspecialchars($siteConfig->getLogoDark()) ?>" alt="<?= htmlspecialchars($siteConfig->getSiteName()) ?>" class="dark-logo">
            <img src="<?= htmlspecialchars($siteConfig->getLogoIcon()) ?>" alt="<?= htmlspecialchars($siteConfig->getSiteName()) ?>" class="logo-icon">
        </a>
    </div>
    <div class="sidebar-menu-area">
        <ul class="sidebar-menu" id="sidebar-menu">
            
                         <!-- Dashboard Principal -->
             <li>
                 <a href="/index-membros">
                     <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
                     <span>Dashboard</span>
                 </a>
             </li>

             <!-- Categorias - Acesso para todos -->
             <li>
                 <a href="/categorias">
                     <iconify-icon icon="solar:folder-outline" class="menu-icon"></iconify-icon>
                     <span>Categorias</span>
                 </a>
             </li>
             
             <!-- Seção: Área do Usuário -->
             <li class="sidebar-menu-group-title">Área do Usuário</li>
             
             <!-- Produtos - Acesso para todos os usuários logados -->
             <?php if ($auth->isLoggedIn()): ?>
             <li>
                 <a href="/produtos">
                     <iconify-icon icon="solar:box-outline" class="menu-icon"></iconify-icon>
                     <span>Produtos</span>
                 </a>
             </li>
             
             <!-- Meu Perfil -->
             <li>
                 <a href="/perfil">
                     <iconify-icon icon="solar:user-outline" class="menu-icon"></iconify-icon>
                     <span>Meu Perfil</span>
                 </a>
             </li>
             
             <!-- Meus Downloads -->
             <li>
                 <a href="/meus-downloads">
                     <iconify-icon icon="solar:download-outline" class="menu-icon"></iconify-icon>
                     <span>Meus Downloads</span>
                 </a>
             </li>
             
             <!-- Notificações -->
             <li>
                 <a href="/notifications">
                     <iconify-icon icon="solar:bell-outline" class="menu-icon"></iconify-icon>
                     <span>Notificações</span>
                     <?php 
                     if ($auth->isLoggedIn()) {
                         try {
                             // Detectar se está sendo usado de admin/
                             $isAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
                             $notificationPath = $isAdmin ? '../includes/Notification.php' : 'includes/Notification.php';
                             
                             if (file_exists($notificationPath)) {
                                 require_once $notificationPath;
                                 $notification = new Notification($pdo);
                                 $unreadCount = $notification->getUnreadCount($auth->getCurrentUser()['id']);
                                 if ($unreadCount > 0) {
                                     echo '<span class="badge bg-danger ms-auto sidebar-notification-count">' . $unreadCount . '</span>';
                                 }
                             }
                         } catch (Exception $e) {
                             // Silenciar erro
                         }
                     }
                     ?>
                 </a>
             </li>
             

             
             <!-- Planos de Assinatura -->
             <li>
                 <a href="/planos">
                     <iconify-icon icon="solar:card-outline" class="menu-icon"></iconify-icon>
                     <span>Planos</span>
                 </a>
             </li>
             <?php endif; ?>

            <!-- Seção: Administração (Apenas para Admin e Super Admin) -->
            <?php if ($auth->isAdmin()): ?>
            <li class="sidebar-menu-group-title">Administração</li>
            
                         <!-- Gerenciar Usuários - Apenas Admin e Super Admin -->
             <?php if ($auth->hasPermission('manage_users')): ?>
             <li>
                 <a href="/admin/usuarios">
                     <iconify-icon icon="heroicons:users" class="menu-icon"></iconify-icon>
                     <span>Gerenciar Usuários</span>
                     <?php if ($auth->isSuperAdmin()): ?>
                         <span class="badge bg-danger ms-auto">Super Admin</span>
                     <?php else: ?>
                         <span class="badge bg-warning ms-auto">Admin</span>
                     <?php endif; ?>
                 </a>
             </li>
             <?php endif; ?>
            
            <!-- Gerenciar Produtos - Apenas Admin e Super Admin -->
            <?php if ($auth->hasPermission('manage_products')): ?>
            <li>
                <a href="/admin/produtos">
                    <iconify-icon icon="solar:box-bold" class="menu-icon"></iconify-icon>
                    <span>Gerenciar Produtos</span>
                    <span class="badge bg-warning ms-auto">Admin</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Gerenciar Categorias - Apenas Admin e Super Admin -->
            <?php if ($auth->hasPermission('manage_categories')): ?>
            <li>
                <a href="/admin/categorias">
                    <iconify-icon icon="solar:folder-outline" class="menu-icon"></iconify-icon>
                    <span>Gerenciar Categorias</span>
                    <span class="badge bg-warning ms-auto">Admin</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Gerenciar Planos - Apenas Admin e Super Admin -->
            <?php if ($auth->hasPermission('manage_plans')): ?>
            <li>
                <a href="/admin/planos">
                    <iconify-icon icon="solar:card-bold" class="menu-icon"></iconify-icon>
                    <span>Gerenciar Planos</span>
                    <span class="badge bg-warning ms-auto">Admin</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Gerenciar Pagamentos - Apenas Admin e Super Admin -->
            <?php if ($auth->hasPermission('manage_payments')): ?>
            <li>
                <a href="/admin/pagamentos">
                    <iconify-icon icon="heroicons:credit-card" class="menu-icon"></iconify-icon>
                    <span>Gerenciar Pagamentos</span>
                    <span class="badge bg-warning ms-auto">Admin</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Gerenciar Avisos - Apenas Admin e Super Admin -->
            <?php if ($auth->hasPermission('manage_news')): ?>
            <li>
                <a href="/admin/noticias">
                    <iconify-icon icon="solar:bell-outline" class="menu-icon"></iconify-icon>
                    <span>Gerenciar Avisos</span>
                    <span class="badge bg-warning ms-auto">Admin</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Relatórios - Apenas Admin e Super Admin -->
            <?php if ($auth->hasPermission('view_reports')): ?>
            <li>
                <a href="/admin/relatorios">
                    <iconify-icon icon="solar:chart-outline" class="menu-icon"></iconify-icon>
                    <span>Relatórios</span>
                    <span class="badge bg-warning ms-auto">Admin</span>
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Seção: Sistema (Apenas Super Admin) -->
            <?php if ($auth->isSuperAdmin()): ?>
            <li class="sidebar-menu-group-title">Sistema</li>
            
            <!-- Gerenciar Roles e Permissões - Apenas Super Admin -->
            <li>
                <a href="/admin/roles">
                    <iconify-icon icon="solar:shield-keyhole-outline" class="menu-icon"></iconify-icon>
                    <span>Roles & Permissões</span>
                    <span class="badge bg-danger ms-auto">Super Admin</span>
                </a>
            </li>
            
            <!-- Configurações do Sistema - Apenas Super Admin -->
            <?php if ($auth->hasPermission('manage_system')): ?>
            <li>
                <a href="/admin/configuracoes">
                    <iconify-icon icon="solar:settings-outline" class="menu-icon"></iconify-icon>
                    <span>Configurações</span>
                    <span class="badge bg-danger ms-auto">Super Admin</span>
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>

                         <!-- Seção: Autenticação -->
             <li class="sidebar-menu-group-title">Conta</li>
             
             <?php if ($auth->isLoggedIn()): ?>
             <!-- Logout -->
                           <li>
                  <a href="/perfil?logout=1" onclick="return confirm('Tem certeza que deseja sair?')">
                      <iconify-icon icon="solar:logout-outline" class="menu-icon"></iconify-icon>
                      <span>Sair</span>
                  </a>
              </li>
             <?php else: ?>
             <!-- Login e Registro -->
                           <li>
                  <a href="/login">
                      <iconify-icon icon="solar:login-outline" class="menu-icon"></iconify-icon>
                      <span>Entrar</span>
                  </a>
              </li>
              <li>
                  <a href="/register">
                      <iconify-icon icon="solar:user-plus-outline" class="menu-icon"></iconify-icon>
                      <span>Cadastrar</span>
                  </a>
              </li>
             <?php endif; ?>

        </ul>
    </div>
</aside>

<style>
/* Estilos para os badges de nível */
.badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 500;
}

.badge.bg-danger {
    background-color: #dc3545 !important;
    color: white;
}

.badge.bg-warning {
    background-color: #ffc107 !important;
    color: #212529;
}

.badge.bg-info {
    background-color: #0dcaf0 !important;
    color: white;
}

/* Ajuste do espaçamento do menu */
.sidebar-menu li a {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.sidebar-menu li a span:first-of-type {
    flex: 1;
}
</style>