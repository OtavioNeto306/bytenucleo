<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Logger.php';

$auth = new Auth($pdo);
$logger = new Logger('cancelamento.log');

// Verificar se usuário está logado e é admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: login.php');
    exit;
}

// Limpar logs se solicitado
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    $logger->clearLogs();
    header('Location: view_logs.php?cleared=1');
    exit;
}

$logs = $logger->getLogs(100); // Últimas 100 linhas
?>

<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<?php include './partials/head.php' ?>

<body>

    <?php include './partials/sidebar.php' ?>

    <main class="dashboard-main">
        <?php include './partials/navbar.php' ?>

        <!-- Header -->
        <section class="py-80 bg-primary-50">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="display-4 fw-bold mb-24">Logs de Cancelamento</h1>
                        <p class="text-lg text-neutral-600 mb-0">
                            Visualize os logs do sistema de cancelamento
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Conteúdo -->
        <section class="py-80">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        
                        <?php if (isset($_GET['cleared'])): ?>
                        <div class="alert alert-success mb-32">
                            <i class="ri-check-line me-2"></i>
                            Logs limpos com sucesso!
                        </div>
                        <?php endif; ?>
                        
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-base py-16 px-24 d-flex justify-content-between align-items-center">
                                <h3 class="mb-0">
                                    <i class="ri-file-list-line me-2"></i>
                                    Logs de Cancelamento
                                </h3>
                                <div>
                                    <a href="?clear=1" class="btn btn-outline-danger btn-sm" 
                                       onclick="return confirm('Tem certeza que deseja limpar todos os logs?')">
                                        <i class="ri-delete-bin-line me-1"></i>
                                        Limpar Logs
                                    </a>
                                    <a href="view_logs.php" class="btn btn-outline-primary btn-sm ms-2">
                                        <i class="ri-refresh-line me-1"></i>
                                        Atualizar
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-32">
                                
                                <?php if (empty($logs)): ?>
                                <div class="text-center py-40">
                                    <i class="ri-file-list-line text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3">Nenhum log encontrado</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>Nível</th>
                                                <th>Mensagem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <?php
                                                // Parsear o log
                                                if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $log, $matches)) {
                                                    $timestamp = $matches[1];
                                                    $level = $matches[2];
                                                    $message = $matches[3];
                                                    
                                                    // Definir cor baseada no nível
                                                    $levelClass = '';
                                                    switch ($level) {
                                                        case 'ERROR':
                                                            $levelClass = 'text-danger';
                                                            break;
                                                        case 'WARNING':
                                                            $levelClass = 'text-warning';
                                                            break;
                                                        case 'DEBUG':
                                                            $levelClass = 'text-muted';
                                                            break;
                                                        default:
                                                            $levelClass = 'text-success';
                                                    }
                                                } else {
                                                    $timestamp = '';
                                                    $level = '';
                                                    $message = $log;
                                                    $levelClass = '';
                                                }
                                                ?>
                                                <td class="text-muted small"><?= htmlspecialchars($timestamp) ?></td>
                                                <td><span class="badge bg-secondary <?= $levelClass ?>"><?= htmlspecialchars($level) ?></span></td>
                                                <td class="font-monospace small"><?= htmlspecialchars($message) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                                
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </section>

        <?php include './partials/footer.php' ?>
    </main>

    <?php include './partials/scripts.php' ?>

</body>
</html>
