<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Notification.php';

$auth = new Auth($pdo);
$notification = new Notification($pdo);

// Verificar se usuário está logado
if (!$auth->isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login');
    exit;
}

$user = $auth->getCurrentUser();
$error = '';
$success = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_as_read') {
        $notificationId = (int)$_POST['notification_id'];
        if ($notification->markAsRead($notificationId, $user['id'])) {
            $success = 'Notificação marcada como lida!';
        } else {
            $error = 'Erro ao marcar notificação como lida.';
        }
    } elseif ($action === 'mark_all_read') {
        if ($notification->markAllAsRead($user['id'])) {
            $success = 'Todas as notificações foram marcadas como lidas!';
        } else {
            $error = 'Erro ao marcar notificações como lidas.';
        }
    } elseif ($action === 'delete') {
        $notificationId = (int)$_POST['notification_id'];
        if ($notification->delete($notificationId, $user['id'])) {
            $success = 'Notificação excluída com sucesso!';
        } else {
            $error = 'Erro ao excluir notificação.';
        }
    }
}

// Buscar notificações
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Usar PDO direto que sabemos que funciona
try {
    // Converter para inteiros explicitamente
    $userId = (int)$user['id'];
    $limitInt = (int)$limit;
    $offsetInt = (int)$offset;
    
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limitInt, PDO::PARAM_INT);
    $stmt->bindValue(3, $offsetInt, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmtUnread->bindValue(1, $userId, PDO::PARAM_INT);
    $stmtUnread->execute();
    $unreadCount = $stmtUnread->fetchColumn();
    
} catch (PDOException $e) {
    $notifications = [];
    $unreadCount = 0;
}



// Contar total de notificações para paginação
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->execute();
    $totalNotifications = $stmt->fetchColumn();
    $totalPages = ceil($totalNotifications / $limitInt);
} catch (PDOException $e) {
    $totalNotifications = 0;
    $totalPages = 1;
}
?>

<?php include './partials/layouts/layoutTop.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Notificações</h1>
                        <p class="text-lg text-secondary-light mb-0">
                            Gerencie suas notificações e mantenha-se atualizado
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Conteúdo principal -->
        <section class="py-80">
            <div class="container">
                
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



                <!-- Estatísticas -->
                <div class="row mb-40">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="mb-16">
                                    <i class="ri-notification-line text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <h4 class="mb-8"><?= $totalNotifications ?></h4>
                                <p class="text-neutral-400 mb-0">Total de Notificações</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="mb-16">
                                    <i class="ri-mail-unread-line text-warning" style="font-size: 2rem;"></i>
                                </div>
                                <h4 class="mb-8"><?= $unreadCount ?></h4>
                                <p class="text-neutral-400 mb-0">Não Lidas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="mb-16">
                                    <i class="ri-mail-check-line text-success" style="font-size: 2rem;"></i>
                                </div>
                                <h4 class="mb-8"><?= $totalNotifications - $unreadCount ?></h4>
                                <p class="text-neutral-400 mb-0">Lidas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ações -->
                <div class="row mb-32">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Suas Notificações</h5>
                            <?php if ($unreadCount > 0): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_all_read">
                                    <button type="submit" class="btn btn-outline-primary d-flex align-items-center">
                                        <iconify-icon icon="solar:check-circle-outline" class="me-8"></iconify-icon>
                                        Marcar Todas como Lidas
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Lista de notificações -->
                <?php if (empty($notifications)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center py-80">
                                    <i class="ri-notification-off-line text-neutral-400" style="font-size: 4rem;"></i>
                                    <h4 class="mt-24 mb-16">Nenhuma notificação</h4>
                                    <p class="text-neutral-400 mb-32">
                                        Você não tem notificações no momento. Continue explorando nossos produtos!
                                    </p>
                                    <a href="produtos.php" class="btn btn-primary d-flex align-items-center d-inline-flex">
                                        <iconify-icon icon="solar:box-outline" class="me-8"></iconify-icon>
                                        Ver Produtos
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($notifications as $notif): ?>
                        <div class="col-12 mb-16">
                            <div class="card border-0 shadow-sm <?= !$notif['is_read'] ? 'border-start border-primary border-4' : '' ?>">
                                <div class="card-body p-24">
                                    <div class="d-flex align-items-start">
                                        <div class="me-16">
                                            <div class="w-48 h-48 rounded-circle bg-<?= getTypeColor($notif['type']) ?> d-flex align-items-center justify-content-center">
                                                <i class="<?= htmlspecialchars($notif['icon']) ?> text-white"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-8">
                                                <h6 class="mb-0 <?= !$notif['is_read'] ? 'fw-bold' : '' ?>">
                                                    <?= htmlspecialchars($notif['title']) ?>
                                                </h6>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                        <i class="ri-more-2-fill"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if (!$notif['is_read']): ?>
                                                        <li>
                                                            <form method="POST" action="" style="display: inline;">
                                                                <input type="hidden" name="action" value="mark_as_read">
                                                                <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="ri-check-line me-8"></i> Marcar como lida
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <form method="POST" action="" style="display: inline;">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Tem certeza que deseja excluir esta notificação?')">
                                                                    <i class="ri-delete-bin-line me-8"></i> Excluir
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                                                                         <p class="text-neutral-400 mb-12">
                                                 <?= htmlspecialchars($notif['message']) ?>
                                                 <?php if ($notif['type'] === 'news' && !$notif['link']): ?>
                                                     <br><small class="text-neutral-500">Este é um aviso do administrador</small>
                                                 <?php endif; ?>
                                             </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-neutral-400">
                                                    <?= formatDate($notif['created_at']) ?>
                                                </small>
                                                <?php if ($notif['link']): ?>
                                                    <a href="<?= htmlspecialchars($notif['link']) ?>" class="btn btn-sm btn-outline-primary">
                                                        Ver Mais
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginação -->
                    <?php if ($totalPages > 1): ?>
                    <div class="row">
                        <div class="col-12">
                            <nav aria-label="Paginação de notificações">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>">Anterior</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>">Próxima</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </section>

<?php include './partials/layouts/layoutBottom.php' ?>

    <style>
    /* Estilos específicos para o tema escuro */
    [data-theme="dark"] .btn-outline-secondary {
        color: #D1D5DB;
        border-color: #4B5563;
    }
    
    [data-theme="dark"] .btn-outline-secondary:hover {
        color: #FFFFFF;
        background-color: #4B5563;
        border-color: #4B5563;
    }
    
    [data-theme="dark"] .dropdown-menu {
        background-color: #1B2431;
        border-color: #323D4E;
    }
    
    [data-theme="dark"] .dropdown-item {
        color: #D1D5DB;
    }
    
    [data-theme="dark"] .dropdown-item:hover {
        background-color: #273142;
        color: #FFFFFF;
    }
    
    [data-theme="dark"] .dropdown-item.text-danger {
        color: #EF4444 !important;
    }
    
    [data-theme="dark"] .dropdown-item.text-danger:hover {
        background-color: #991B1B;
        color: #FFFFFF !important;
    }
    
    [data-theme="dark"] .btn-outline-primary {
        color: #3B82F6;
        border-color: #3B82F6;
    }
    
    [data-theme="dark"] .btn-outline-primary:hover {
        color: #FFFFFF;
        background-color: #3B82F6;
        border-color: #3B82F6;
    }
    
    [data-theme="dark"] .pagination .page-link {
        background-color: #273142;
        border-color: #323D4E;
        color: #D1D5DB;
    }
    
    [data-theme="dark"] .pagination .page-link:hover {
        background-color: #323D4E;
        border-color: #4B5563;
        color: #FFFFFF;
    }
    
    [data-theme="dark"] .pagination .page-item.active .page-link {
        background-color: #3B82F6;
        border-color: #3B82F6;
        color: #FFFFFF;
    }
    </style>

    <script>
        $(document).ready(function() {
            // Auto-refresh para notificações não lidas
            setInterval(function() {
                $.get('ajax/get_notifications_count.php', function(data) {
                    if (data.unread_count > 0) {
                        // Atualizar contador no navbar se existir
                        $('.notification-count').text(data.unread_count);
                    }
                });
            }, 30000); // Verificar a cada 30 segundos
        });
    </script>

</body>
</html>

<?php
// Funções auxiliares
function getTypeColor($type) {
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
        'info' => 'info'
    ];
    
    return $colors[$type] ?? 'info';
}

function formatDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'Agora mesmo';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "Há {$minutes} min" . ($minutes > 1 ? 's' : '');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "Há {$hours} hora" . ($hours > 1 ? 's' : '');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "Há {$days} dia" . ($days > 1 ? 's' : '');
    } else {
        return date('d/m/Y H:i', $timestamp);
    }
}
?>