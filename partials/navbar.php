<?php
// Detectar se está sendo usado de admin/
$isAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$assetPrefix = $isAdmin ? '../' : '';

// Carregar conexão do banco se não estiver disponível
if (!isset($pdo)) {
    require_once $assetPrefix . 'config/database.php';
}

// Obter dados do usuário logado
$currentUser = null;
if (isset($auth) && $auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentUser();
}

// Definir avatar padrão ou do usuário
$userAvatar = $assetPrefix . 'assets/images/user.png'; // Avatar padrão
if ($currentUser && $currentUser['avatar']) {
    $userAvatar = $assetPrefix . $currentUser['avatar'];
}

// Definir nome e role do usuário
$userName = $currentUser ? $currentUser['name'] : 'Usuário';
$userRole = $currentUser ? $currentUser['role_name'] : 'Usuário';

// Buscar número do WhatsApp das configurações
$whatsappNumber = '';
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'contact_phone'");
        $stmt->execute();
        $whatsappNumber = $stmt->fetchColumn() ?: '';
    }
} catch (Exception $e) {
    // Em caso de erro, usar string vazia
    $whatsappNumber = '';
}
?>
<div class="navbar-header">
            <div class="row align-items-center justify-content-between">
                <div class="col-auto">
                    <div class="d-flex flex-wrap align-items-center gap-4">
                        <button type="button" class="sidebar-toggle">
                            <iconify-icon icon="heroicons:bars-3-solid" class="icon text-2xl non-active"></iconify-icon>
                            <iconify-icon icon="iconoir:arrow-right" class="icon text-2xl active"></iconify-icon>
                        </button>
                        <button type="button" class="sidebar-mobile-toggle">
                            <iconify-icon icon="heroicons:bars-3-solid" class="icon"></iconify-icon>
                        </button>
                        <div class="navbar-search-container position-relative">
                            <form class="navbar-search" id="searchForm" action="<?= $assetPrefix ?>produtos.php" method="GET">
                                <input type="text" id="searchInput" name="search" placeholder="Buscar produtos..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" autocomplete="off">
                                <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
                            </form>
                            
                            <!-- Dropdown de resultados da busca -->
                            <div id="searchResults" class="search-results-dropdown position-absolute w-100 bg-base border border-neutral-200 rounded shadow-lg" style="display: none; z-index: 1000; top: 100%;">
                                <div id="searchResultsContent" class="p-0">
                                    <!-- Resultados serão inseridos aqui via JavaScript -->
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                                                 <button type="button" data-theme-toggle class="w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center"></button>

                        <?php if (!empty($whatsappNumber)): ?>
                        <!-- Botão WhatsApp -->
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsappNumber) ?>?text=Quero%20saber%20mais%20sobre%20seu%20site" 
                           target="_blank" 
                           class="w-40-px h-40-px bg-success-100 hover-bg-success-200 rounded-circle d-flex justify-content-center align-items-center transition-colors" 
                           title="Fale conosco no WhatsApp">
                            <iconify-icon icon="ri:whatsapp-fill" class="text-success-600 text-xl"></iconify-icon>
                        </a>
                        <?php endif; ?>

                        <div class="dropdown">
                            <button class="has-indicator w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center" type="button" data-bs-toggle="dropdown">
                                <iconify-icon icon="iconoir:bell" class="text-primary-light text-xl"></iconify-icon>
                                <?php 
                                if ($currentUser && isset($pdo)) {
                                    try {
                                        $userId = (int)$currentUser['id'];
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
                                        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
                                        $stmt->execute();
                                        $unreadCount = $stmt->fetchColumn();
                                        
                                        if ($unreadCount > 0) {
                                            echo '<span class="notification-badge position-absolute top-0 end-0 w-16-px h-16-px bg-danger rounded-circle d-flex justify-content-center align-items-center">';
                                            echo '<span class="text-white text-xs fw-bold">' . $unreadCount . '</span>';
                                            echo '</span>';
                                        }
                                    } catch (Exception $e) {
                                        // Silenciar erro
                                    }
                                }
                                ?>
                            </button>
                            <div class="dropdown-menu to-top dropdown-menu-lg p-0">
                                <div class="m-16 py-12 px-16 radius-8 bg-primary-50 mb-16 d-flex align-items-center justify-content-between gap-2">
                                    <div>
                                        <h6 class="text-lg text-primary-light fw-semibold mb-0">Notificações</h6>
                                    </div>
                                    <span class="text-primary-600 fw-semibold text-lg w-40-px h-40-px rounded-circle bg-base d-flex justify-content-center align-items-center notification-count">
                                        <?php 
                                        if ($currentUser && isset($pdo)) {
                                            try {
                                                $userId = (int)$currentUser['id'];
                                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
                                                $stmt->bindValue(1, $userId, PDO::PARAM_INT);
                                                $stmt->execute();
                                                echo $stmt->fetchColumn();
                                            } catch (Exception $e) {
                                                echo '0';
                                            }
                                        } else {
                                            echo '0';
                                        }
                                        ?>
                                    </span>
                                </div>

                                <div class="max-h-400-px overflow-y-auto scroll-sm pe-4" id="notifications-list">
                                    <?php 
                                    if ($currentUser && isset($pdo)) {
                                        try {
                                            // Usar PDO direto como no notifications.php
                                            $userId = (int)$currentUser['id'];
                                            $limitInt = 5;
                                            
                                            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT ?");
                                            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
                                            $stmt->bindValue(2, $limitInt, PDO::PARAM_INT);
                                            $stmt->execute();
                                            $recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (empty($recentNotifications)) {
                                                echo '<div class="px-24 py-32 text-center">';
                                                echo '<i class="ri-notification-off-line text-neutral-400" style="font-size: 2rem;"></i>';
                                                echo '<p class="text-neutral-400 mt-16 mb-0">Nenhuma notificação não lida</p>';
                                                echo '</div>';
                                            } else {
                                                foreach ($recentNotifications as $notif) {
                                                    $isRead = $notif['is_read'] ? '' : 'bg-neutral-50';
                                                    $iconClass = getNotificationIcon($notif['type']);
                                                    $iconColor = getNotificationColor($notif['type']);
                                                    
                                                    echo '<a href="' . $assetPrefix . 'notifications.php" class="px-24 py-12 d-flex align-items-start gap-3 mb-2 justify-content-between ' . $isRead . ' notification-item" data-id="' . $notif['id'] . '">';
                                                    echo '<div class="text-black hover-bg-transparent hover-text-primary d-flex align-items-center gap-3">';
                                                    echo '<span class="w-40-px h-40-px rounded-circle flex-shrink-0 position-relative bg-' . $iconColor . ' d-flex align-items-center justify-content-center">';
                                                    echo '<i class="' . $iconClass . ' text-white text-sm"></i>';
                                                    if (!$notif['is_read']) {
                                                        echo '<span class="w-8-px h-8-px bg-danger rounded-circle position-absolute end-0 bottom-0"></span>';
                                                    }
                                                    echo '</span>';
                                                    echo '<div>';
                                                    echo '<h6 class="text-md fw-semibold mb-4">' . htmlspecialchars($notif['title']) . '</h6>';
                                                    echo '<p class="mb-0 text-sm text-neutral-400 text-w-100-px">' . htmlspecialchars(substr($notif['message'], 0, 50)) . (strlen($notif['message']) > 50 ? '...' : '') . '</p>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                    echo '<div class="d-flex flex-column align-items-end">';
                                                    echo '<span class="text-sm text-neutral-400 flex-shrink-0">' . formatNotificationTime($notif['created_at']) . '</span>';
                                                    if (!$notif['is_read']) {
                                                        echo '<span class="mt-4 text-xs text-white w-16-px h-16-px d-flex justify-content-center align-items-center bg-danger rounded-circle">!</span>';
                                                    }
                                                    echo '</div>';
                                                    echo '</a>';
                                                }
                                            }
                                        } catch (Exception $e) {
                                            echo '<div class="px-24 py-32 text-center">';
                                            echo '<i class="ri-notification-off-line text-neutral-400" style="font-size: 2rem;"></i>';
                                            echo '<p class="text-neutral-400 mt-16 mb-0">Erro ao carregar notificações não lidas</p>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<div class="px-24 py-32 text-center">';
                                        echo '<i class="ri-notification-off-line text-neutral-400" style="font-size: 2rem;"></i>';
                                        echo '<p class="text-neutral-400 mt-16 mb-0">Faça login para ver notificações</p>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>

                                <div class="text-center py-12 px-16">
                                    <a href="<?= $assetPrefix ?>notifications" class="text-primary-600 fw-semibold text-md">Ver Todas as Notificações</a>
                                </div>

                            </div>
                        </div><!-- Notification dropdown end -->

                        <div class="dropdown">
                            <button class="d-flex justify-content-center align-items-center rounded-circle" type="button" data-bs-toggle="dropdown">
                                <img src="<?= $userAvatar ?>" alt="<?= htmlspecialchars($userName) ?>" class="w-40-px h-40-px object-fit-cover rounded-circle">
                            </button>
                            <div class="dropdown-menu to-top dropdown-menu-sm">
                                <div class="py-12 px-16 radius-8 bg-primary-50 mb-16 d-flex align-items-center justify-content-between gap-2">
                                    <div>
                                        <h6 class="text-lg text-primary-light fw-semibold mb-2"><?= htmlspecialchars($userName) ?></h6>
                                        <span class="text-secondary-light fw-medium text-sm"><?= htmlspecialchars($userRole) ?></span>
                                    </div>
                                    <button type="button" class="hover-text-danger">
                                        <iconify-icon icon="radix-icons:cross-1" class="icon text-xl"></iconify-icon>
                                    </button>
                                </div>
                                                                 <ul class="to-top-list">
                                     <li>
                                         <a class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-primary d-flex align-items-center gap-3" href="<?= $assetPrefix ?>perfil">
                                             <iconify-icon icon="solar:user-linear" class="icon text-xl"></iconify-icon> Meu Perfil
                                         </a>
                                     </li>
                                     <li>
                                         <a class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-primary d-flex align-items-center gap-3" href="<?= $assetPrefix ?>produtos">
                                             <iconify-icon icon="solar:box-outline" class="icon text-xl"></iconify-icon> Produtos
                                         </a>
                                     </li>
                                     <li>
                                         <a class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-primary d-flex align-items-center gap-3" href="<?= $assetPrefix ?>planos">
                                             <iconify-icon icon="solar:card-outline" class="icon text-xl"></iconify-icon> Planos
                                         </a>
                                     </li>
                                     <li>
                                         <a class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-primary d-flex align-items-center gap-3" href="<?= $assetPrefix ?>meus-favoritos">
                                             <iconify-icon icon="ri:heart-line" class="icon text-xl"></iconify-icon> Meus Favoritos
                                         </a>
                                     </li>
                                     <li>
                                         <a class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-danger d-flex align-items-center gap-3" href="<?= $assetPrefix ?>perfil?logout=1" onclick="return confirm('Tem certeza que deseja sair?')">
                                             <iconify-icon icon="lucide:power" class="icon text-xl"></iconify-icon> Sair
                                         </a>
                                     </li>
                                 </ul>
                            </div>
                        </div><!-- Profile dropdown end -->
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Aguardar jQuery carregar
            document.addEventListener('DOMContentLoaded', function() {
                // Aguardar um pouco mais para garantir que jQuery esteja disponível
                setTimeout(function() {
                    if (typeof $ !== 'undefined') {
                        initNotifications();
                        initSearchSystem();
                    } else {
                        console.error('jQuery not loaded');
                    }
                }, 100);
            });
            
            function initNotifications() {
                // Função para marcar notificação como lida
                function markNotificationAsRead(notificationId) {
                    $.post('<?= $assetPrefix ?>ajax/mark_notification_read.php', {
                        notification_id: notificationId
                    }, function(data) {
                        if (data.success) {
                            // Atualizar contador
                            updateNotificationCount();
                        }
                    });
                }
                
                // Função para atualizar contador de notificações
                function updateNotificationCount() {
                    $.get('<?= $assetPrefix ?>ajax/get_notifications_count.php', function(data) {
                        if (data.success) {
                            $('.notification-count, .notification-badge span, .sidebar-notification-count').text(data.unread_count);
                            if (data.unread_count == 0) {
                                $('.notification-badge, .sidebar-notification-count').hide();
                            } else {
                                $('.notification-badge, .sidebar-notification-count').show();
                            }
                        }
                    });
                }
                
                // Marcar notificação como lida ao clicar
                $(document).on('click', '.notification-item', function() {
                    var notificationId = $(this).data('id');
                    if (notificationId) {
                        markNotificationAsRead(notificationId);
                    }
                });
                
                // Atualizar contador a cada 30 segundos
                setInterval(updateNotificationCount, 30000);
            }
            
            function initSearchSystem() {
                console.log('Initializing search system...');
                
                // Sistema de busca em tempo real
                let searchTimeout;
                
                function initSearch() {
                const searchInput = document.getElementById('searchInput');
                const searchResults = document.getElementById('searchResults');
                const searchResultsContent = document.getElementById('searchResultsContent');
                const searchForm = document.getElementById('searchForm');
                
                console.log('Initializing search...', { searchInput, searchResults, searchResultsContent, searchForm });
                
                if (searchInput && searchForm) {
                    // Marcar como inicializado para evitar duplicação
                    searchInput.setAttribute('data-initialized', 'true');
                    console.log('Search initialized successfully!');
                    
                    // Prevenir submit do formulário para permitir busca em tempo real
                    searchForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const query = searchInput.value.trim();
                        if (query.length >= 2) {
                            // Redirecionar para página de produtos com a busca
                            window.location.href = `<?= $assetPrefix ?>produtos.php?search=${encodeURIComponent(query)}`;
                        }
                    });
                    
                    searchInput.addEventListener('input', function() {
                        const query = this.value.trim();
                        console.log('Input event triggered:', query);
                        
                        // Limpar timeout anterior
                        clearTimeout(searchTimeout);
                        
                        if (query.length < 2) {
                            hideSearchResults();
                            return;
                        }
                        
                        // Debounce - aguardar 300ms após parar de digitar
                        searchTimeout = setTimeout(() => {
                            console.log('Searching for:', query);
                            searchProducts(query);
                        }, 300);
                    });
                    
                    // Esconder resultados ao clicar fora
                    document.addEventListener('click', function(e) {
                        if (!e.target.closest('.navbar-search-container')) {
                            hideSearchResults();
                        }
                    });
                    
                    // Esconder resultados ao pressionar Escape
                    searchInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            hideSearchResults();
                        }
                    });
                } else {
                    console.error('Search elements not found!');
                }
            }
            
            function searchProducts(query) {
                console.log('Making request to:', `<?= $assetPrefix ?>ajax/search_products.php?q=${encodeURIComponent(query)}`);
                
                fetch(`<?= $assetPrefix ?>ajax/search_products.php?q=${encodeURIComponent(query)}`)
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Search results:', data);
                        if (data.products && data.products.length > 0) {
                            showSearchResults(data.products);
                        } else {
                            showNoResults();
                        }
                    })
                    .catch(error => {
                        console.error('Erro na busca:', error);
                        hideSearchResults();
                    });
            }
            
            function showSearchResults(products) {
                console.log('Showing search results:', products);
                const searchResults = document.getElementById('searchResults');
                const searchResultsContent = document.getElementById('searchResultsContent');
                
                let html = '';
                
                products.forEach(product => {
                    html += `
                        <a href="${product.url}" class="search-result-item d-flex align-items-center gap-3 p-16 text-decoration-none hover-bg-neutral-50">
                            <img src="${product.image}" alt="${product.name}" class="w-40-px h-40-px object-fit-cover rounded" style="flex-shrink: 0;">
                            <div class="flex-grow-1">
                                <h6 class="text-md fw-semibold mb-2 text-lg">${product.name}</h6>
                                <p class="text-sm text-secondary-light mb-0">${product.description}</p>
                                <span class="text-primary fw-semibold text-sm">${product.price}</span>
                            </div>
                        </a>
                    `;
                });
                
                searchResultsContent.innerHTML = html;
                searchResults.style.display = 'block';
            }
            
            function showNoResults() {
                console.log('Showing no results');
                const searchResults = document.getElementById('searchResults');
                const searchResultsContent = document.getElementById('searchResultsContent');
                
                searchResultsContent.innerHTML = `
                    <div class="p-24 text-center">
                        <i class="ri-search-line text-neutral-400" style="font-size: 2rem;"></i>
                        <p class="text-neutral-400 mt-16 mb-0">Nenhum produto encontrado</p>
                    </div>
                `;
                searchResults.style.display = 'block';
            }
            
            function hideSearchResults() {
                console.log('Hiding search results');
                const searchResults = document.getElementById('searchResults');
                if (searchResults) {
                    searchResults.style.display = 'none';
                }
            }
            
            
                
                // Inicializar busca
                initSearch();
            }
        </script>

        <style>
        /* Estilos específicos para o dropdown de notificações no tema escuro */
        [data-theme="dark"] .dropdown-menu {
            background-color: #1B2431;
            border-color: #323D4E;
        }
        
        [data-theme="dark"] .notification-item {
            color: #D1D5DB;
            text-decoration: none;
        }
        
        [data-theme="dark"] .notification-item:hover {
            background-color: #273142;
            color: #FFFFFF;
        }
        
        [data-theme="dark"] .notification-item.bg-neutral-50 {
            background-color: #273142;
        }
        
        [data-theme="dark"] .notification-item h6 {
            color: #FFFFFF;
        }
        
        [data-theme="dark"] .notification-item p {
            color: #9CA3AF;
        }
        
        [data-theme="dark"] .notification-item span {
            color: #9CA3AF;
        }
        
        [data-theme="dark"] .dropdown-menu .text-center a {
            color: #3B82F6;
        }
        
        [data-theme="dark"] .dropdown-menu .text-center a:hover {
            color: #60A5FA;
        }
        
        /* Estilos para o dropdown de busca */
        .search-results-dropdown {
            max-height: 400px;
            overflow-y: auto;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .search-result-item {
            border-bottom: 1px solid #E5E7EB;
            transition: background-color 0.2s ease;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .search-result-item:hover {
            background-color: #F9FAFB;
        }
        
        /* Estilos para tema escuro */
        [data-theme="dark"] .search-results-dropdown {
            background-color: #1B2431;
            border-color: #323D4E;
        }
        
        [data-theme="dark"] .search-result-item {
            border-bottom-color: #323D4E;
            color: #D1D5DB;
        }
        
        [data-theme="dark"] .search-result-item:hover {
            background-color: #273142;
        }
        
        [data-theme="dark"] .search-result-item h6 {
            color: #FFFFFF;
        }
        
        [data-theme="dark"] .search-result-item p {
            color: #9CA3AF;
        }
        
        [data-theme="dark"] .search-result-item span {
            color: #3B82F6;
        }
        </style>

        <?php
        // Funções auxiliares para notificações
        function getNotificationIcon($type) {
            $icons = [
                'welcome' => 'ri-user-heart-line',
                'system' => 'ri-settings-line',
                'payment' => 'ri-bank-card-line',
                'subscription' => 'ri-vip-crown-line',
                'product' => 'ri-box-line',
                'download' => 'ri-download-line',
                'security' => 'ri-shield-check-line',
                'admin' => 'ri-admin-line',
                'success' => 'ri-check-line',
                'warning' => 'ri-alert-line',
                'error' => 'ri-error-warning-line',
                'info' => 'ri-information-line',
                'news' => 'ri-notification-line'
            ];
            
            return $icons[$type] ?? 'ri-notification-line';
        }
        
        function getNotificationColor($type) {
            $colors = [
                'welcome' => 'success',
                'system' => 'info',
                'payment' => 'warning',
                'subscription' => 'primary',
                'product' => 'info',
                'download' => 'success',
                'security' => 'danger',
                'admin' => 'warning',
                'success' => 'success',
                'warning' => 'warning',
                'error' => 'danger',
                'info' => 'info',
                'news' => 'primary'
            ];
            
            return $colors[$type] ?? 'info';
        }
        
        function formatNotificationTime($date) {
            $timestamp = strtotime($date);
            $now = time();
            $diff = $now - $timestamp;
            
            if ($diff < 60) {
                return 'Agora';
            } elseif ($diff < 3600) {
                $minutes = floor($diff / 60);
                return "Há {$minutes}m";
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return "Há {$hours}h";
            } elseif ($diff < 604800) {
                $days = floor($diff / 86400);
                return "Há {$days}d";
            } else {
                return date('d/m', $timestamp);
            }
        }
        ?>
