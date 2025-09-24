<?php
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/News.php';

$auth = new Auth($pdo);
$news = new News($pdo);

// Verificar se usuário está logado e é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php?redirect=admin/noticias.php');
    exit;
}

$user = $auth->getCurrentUser();
$error = '';
$success = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $newsData = [
            'title' => $_POST['title'],
            'content' => $_POST['content'],
            'type' => $_POST['type'],
            'priority' => $_POST['priority'],
            'created_by' => $user['id']
        ];
        
                 if ($news->create($newsData)) {
             $success = 'Aviso criado com sucesso! Todos os usuários foram notificados.';
         } else {
             $error = 'Erro ao criar aviso.';
         }
    } elseif ($action === 'update') {
        $newsId = (int)$_POST['news_id'];
        $newsData = [
            'title' => $_POST['title'],
            'content' => $_POST['content'],
            'type' => $_POST['type'],
            'priority' => $_POST['priority'],
            'is_active' => isset($_POST['is_active'])
        ];
        
                 if ($news->update($newsId, $newsData)) {
             $success = 'Aviso atualizado com sucesso!';
         } else {
             $error = 'Erro ao atualizar aviso.';
         }
    } elseif ($action === 'delete') {
        $newsId = (int)$_POST['news_id'];
                 if ($news->delete($newsId)) {
             $success = 'Aviso excluído com sucesso!';
         } else {
             $error = 'Erro ao excluir aviso.';
         }
    }
}

// Filtros
$type = $_GET['type'] ?? '';
$priority = $_GET['priority'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$filters = [];
if ($type) $filters['type'] = $type;
if ($priority) $filters['priority'] = $priority;
if ($search) $filters['search'] = $search;

// Obter notícias
$newsList = $news->getAll($page, 20, $filters);
$totalNews = $news->getCount($filters);
$totalPages = ceil($totalNews / 20);

// Obter estatísticas
$stats = $news->getStats();

// Obter tipos e prioridades
$types = $news->getTypes();
$priorities = $news->getPriorities();
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">

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
                                                 <h1 class="display-4 fw-bold mb-24">Gerenciar Avisos</h1>
                                                 <p class="text-lg text-secondary-light mb-0">
                            Crie e gerencie avisos que aparecem nas notificações dos usuários
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Estatísticas -->
        <section class="py-40">
            <div class="container">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32">
                                <div class="d-flex align-items-center">
                                    <div class="me-16">
                                        <i class="ri-newspaper-line text-primary" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h4 class="fw-semibold mb-4"><?= $stats['total_news'] ?></h4>
                                        <p class="text-neutral-400 mb-0">Total de Notícias</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32">
                                <div class="d-flex align-items-center">
                                    <div class="me-16">
                                        <i class="ri-eye-line text-success" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h4 class="fw-semibold mb-4"><?= $stats['active_news'] ?></h4>
                                        <p class="text-neutral-400 mb-0">Ativas</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32">
                                <div class="d-flex align-items-center">
                                    <div class="me-16">
                                        <i class="ri-alert-line text-warning" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h4 class="fw-semibold mb-4"><?= $stats['high_priority'] ?></h4>
                                        <p class="text-neutral-400 mb-0">Alta Prioridade</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-32">
                                <div class="d-flex align-items-center">
                                    <div class="me-16">
                                        <i class="ri-notification-line text-info" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h4 class="fw-semibold mb-4"><?= $stats['info_count'] ?></h4>
                                        <p class="text-neutral-400 mb-0">Informações</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Conteúdo principal -->
        <section class="py-40">
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

                <!-- Ações -->
                <div class="row mb-32">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                                                         <h5 class="mb-0">Avisos do Sistema</h5>
                             <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#createNewsModal">
                                 <iconify-icon icon="solar:add-circle-outline" class="me-8"></iconify-icon>
                                 Novo Aviso
                             </button>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="row mb-32">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Buscar</label>
                                        <input type="text" name="search" class="form-control" placeholder="Título ou conteúdo..." value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tipo</label>
                                        <select name="type" class="form-select">
                                            <option value="">Todos os tipos</option>
                                            <?php foreach ($types as $key => $label): ?>
                                                <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Prioridade</label>
                                        <select name="priority" class="form-select">
                                            <option value="">Todas as prioridades</option>
                                            <?php foreach ($priorities as $key => $label): ?>
                                                <option value="<?= $key ?>" <?= $priority === $key ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">Filtrar</button>
                                            <a href="?" class="btn btn-outline-secondary">Limpar</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de notícias -->
                <div class="row">
                    <?php if (empty($newsList)): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center py-80">
                                                                        <i class="ri-newspaper-off-line text-neutral-400" style="font-size: 4rem;"></i>
                                    <h4 class="mt-24 mb-16">Nenhum aviso encontrado</h4>
                                    <p class="text-neutral-400 mb-32">
                                        Crie seu primeiro aviso para começar a comunicar com os usuários!
                                    </p>
                                     <button type="button" class="btn btn-primary d-flex align-items-center d-inline-flex" data-bs-toggle="modal" data-bs-target="#createNewsModal">
                                         <iconify-icon icon="solar:add-circle-outline" class="me-8"></iconify-icon>
                                         Criar Aviso
                                     </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($newsList as $newsItem): ?>
                        <div class="col-12 mb-16">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-24">
                                    <div class="d-flex align-items-start">
                                        <div class="me-16">
                                            <div class="w-48 h-48 rounded-circle bg-<?= $news->getTypeColor($newsItem['type']) ?> d-flex align-items-center justify-content-center">
                                                <i class="<?= $news->getTypeIcon($newsItem['type']) ?> text-white"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-8">
                                                <div>
                                                    <h6 class="mb-4">
                                                        <?= htmlspecialchars($newsItem['title']) ?>
                                                        <?php if (!$newsItem['is_active']): ?>
                                                            <span class="badge bg-secondary ms-8">Inativa</span>
                                                        <?php endif; ?>
                                                        <span class="badge bg-<?= $news->getTypeColor($newsItem['type']) ?> ms-8"><?= $types[$newsItem['type']] ?></span>
                                                        <span class="badge bg-<?= $newsItem['priority'] === 'high' ? 'danger' : ($newsItem['priority'] === 'medium' ? 'warning' : 'info') ?> ms-8"><?= $priorities[$newsItem['priority']] ?></span>
                                                    </h6>
                                                    <p class="text-neutral-400 mb-0">
                                                        Por <strong><?= htmlspecialchars($newsItem['author_name']) ?></strong> • 
                                                        <?= $news->formatDate($newsItem['created_at']) ?>
                                                    </p>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                        <i class="ri-more-2-fill"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <button type="button" class="dropdown-item" onclick="editNews(<?= $newsItem['id'] ?>)">
                                                                <i class="ri-edit-line me-8"></i> Editar
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="" style="display: inline;">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="news_id" value="<?= $newsItem['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Tem certeza que deseja excluir esta notícia?')">
                                                                    <i class="ri-delete-bin-line me-8"></i> Excluir
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="mb-12">
                                                <?= nl2br(htmlspecialchars(substr($newsItem['content'], 0, 200))) ?>
                                                <?php if (strlen($newsItem['content']) > 200): ?>
                                                    <span class="text-neutral-400">...</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Paginação -->
                <?php if ($totalPages > 1): ?>
                <div class="row">
                    <div class="col-12">
                        <nav aria-label="Paginação de notícias">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&type=<?= $type ?>&priority=<?= $priority ?>&search=<?= urlencode($search) ?>">Anterior</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&type=<?= $type ?>&priority=<?= $priority ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&type=<?= $type ?>&priority=<?= $priority ?>&search=<?= urlencode($search) ?>">Próxima</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </section>

        <?php include '../partials/footer.php' ?>
    </main>

    <!-- Modal Criar Notícia -->
    <div class="modal fade" id="createNewsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                                 <div class="modal-header">
                     <h5 class="modal-title">Novo Aviso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-16">
                            <label class="form-label">Título *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        
                        <div class="row mb-16">
                            <div class="col-md-6">
                                <label class="form-label">Tipo *</label>
                                <select name="type" class="form-select" required>
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prioridade *</label>
                                <select name="priority" class="form-select" required>
                                    <?php foreach ($priorities as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                                                 <div class="mb-16">
                             <label class="form-label">Conteúdo do Aviso *</label>
                             <textarea name="content" class="form-control" rows="8" required placeholder="Digite o conteúdo do aviso que aparecerá nas notificações dos usuários..."></textarea>
                         </div>
                        
                                                 <div class="alert alert-info">
                             <i class="ri-information-line me-8"></i>
                             <strong>Atenção:</strong> Este aviso será enviado como notificação para todos os usuários ativos do sistema.
                         </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                 <button type="submit" class="btn btn-primary">Criar Aviso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Notícia -->
    <div class="modal fade" id="editNewsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                                 <div class="modal-header">
                     <h5 class="modal-title">Editar Aviso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="news_id" id="edit_news_id">
                        
                        <div class="mb-16">
                            <label class="form-label">Título *</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        
                        <div class="row mb-16">
                            <div class="col-md-4">
                                <label class="form-label">Tipo *</label>
                                <select name="type" id="edit_type" class="form-select" required>
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Prioridade *</label>
                                <select name="priority" id="edit_priority" class="form-select" required>
                                    <?php foreach ($priorities as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-32">
                                    <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input" value="1">
                                    <label class="form-check-label" for="edit_is_active">Ativa</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-16">
                            <label class="form-label">Conteúdo *</label>
                            <textarea name="content" id="edit_content" class="form-control" rows="8" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                 <button type="submit" class="btn btn-primary">Atualizar Aviso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../partials/scripts.php' ?>

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
    
    [data-theme="dark"] .form-control {
        background-color: #273142;
        border-color: #323D4E;
        color: #D1D5DB;
    }
    
    [data-theme="dark"] .form-control:focus {
        background-color: #273142;
        border-color: #3B82F6;
        color: #D1D5DB;
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
    }
    
    [data-theme="dark"] .form-select {
        background-color: #273142;
        border-color: #323D4E;
        color: #D1D5DB;
    }
    
    [data-theme="dark"] .form-select:focus {
        background-color: #273142;
        border-color: #3B82F6;
        color: #D1D5DB;
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
    }
    
    [data-theme="dark"] .form-label {
        color: #D1D5DB;
    }
    
    [data-theme="dark"] .modal-content {
        background-color: #1B2431;
        border-color: #323D4E;
    }
    
    [data-theme="dark"] .modal-header {
        border-bottom-color: #323D4E;
    }
    
    [data-theme="dark"] .modal-footer {
        border-top-color: #323D4E;
    }
    
    [data-theme="dark"] .modal-title {
        color: #FFFFFF;
    }
    
    [data-theme="dark"] .btn-close {
        filter: invert(1);
    }
    </style>

    <script>
        function editNews(newsId) {
            // Carregar dados da notícia via AJAX
            $.get('../ajax/get_news.php', { id: newsId }, function(data) {
                if (data.success) {
                    const news = data.news;
                    
                    // Preencher o formulário
                    $('#edit_news_id').val(news.id);
                    $('#edit_title').val(news.title);
                    $('#edit_content').val(news.content);
                    $('#edit_type').val(news.type);
                    $('#edit_priority').val(news.priority);
                    $('#edit_is_active').prop('checked', news.is_active == 1);
                    
                    // Abrir o modal
                    $('#editNewsModal').modal('show');
                } else {
                    alert('Erro ao carregar dados da notícia: ' + data.error);
                }
            }).fail(function() {
                alert('Erro ao carregar dados da notícia');
            });
        }
    </script>

</body>
</html>
