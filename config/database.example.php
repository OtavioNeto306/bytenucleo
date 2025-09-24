<?php
// Arquivo de exemplo para configuração do banco de dados
// Copie este arquivo para database.php e configure com suas credenciais

// Configuração do banco de dados
define('DB_HOST', 'localhost');           // Host do banco de dados
define('DB_NAME', 'area_membros');        // Nome do banco de dados
define('DB_USER', 'root');                // Usuário do banco de dados
define('DB_PASS', '');                    // Senha do banco de dados

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Configurações gerais
define('SITE_URL', 'http://localhost/wowdash-php');  // URL do seu site
define('SITE_NAME', 'Área de Membros');              // Nome do site
define('UPLOAD_PATH', 'uploads/');                   // Pasta de uploads
define('SESSION_NAME', 'membros_session');           // Nome da sessão

// Iniciar sessão
if (session_status() == PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
?>
