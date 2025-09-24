<?php
// Configuração do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'clubinhopro');
define('DB_USER', 'clubinhopro');
define('DB_PASS', 'Admin@123');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Definir nome da sessão
define('SESSION_NAME', 'membros_session');

// Iniciar sessão
if (session_status() == PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Criar tabelas para interação com vídeos
try {
    // Tabela de curtidas de vídeos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS video_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_video (user_id, video_id),
            FOREIGN KEY (video_id) REFERENCES product_videos(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Tabela de comentários de vídeos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS video_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_id INT NOT NULL,
            user_id INT NOT NULL,
            comment TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (video_id) REFERENCES product_videos(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

} catch (PDOException $e) {
    // Log do erro mas não interromper a execução
    error_log("Erro ao criar tabelas de interação com vídeos: " . $e->getMessage());
}


?>
