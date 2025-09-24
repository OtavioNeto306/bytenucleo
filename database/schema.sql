-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS area_membros CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE area_membros;

-- Tabela de usuários
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de planos de assinatura
CREATE TABLE plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de assinaturas
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    status ENUM('active', 'cancelled', 'expired', 'pending') DEFAULT 'pending',
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NOT NULL,
    payment_method VARCHAR(100),
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
);

-- Tabela de produtos/cursos
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail VARCHAR(255),
    price DECIMAL(10,2) DEFAULT 0.00,
    is_free BOOLEAN DEFAULT FALSE,
    requires_subscription BOOLEAN DEFAULT TRUE,
    file_path VARCHAR(255),
    download_count INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de downloads (histórico)
CREATE TABLE downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabela de categorias de produtos
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    slug VARCHAR(255) UNIQUE NOT NULL,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Tabela de relacionamento produtos-categorias
CREATE TABLE product_categories (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Inserir dados iniciais
INSERT INTO plans (name, description, price, duration_days, features) VALUES
('Básico', 'Acesso a produtos básicos', 29.90, 30, '["Produtos básicos", "Suporte por email"]'),
('Premium', 'Acesso completo a todos os produtos', 59.90, 30, '["Todos os produtos", "Suporte prioritário", "Downloads ilimitados"]'),
('Anual', 'Acesso completo por 1 ano', 599.90, 365, '["Todos os produtos", "Suporte prioritário", "Downloads ilimitados", "2 meses grátis"]');

INSERT INTO categories (name, description, slug) VALUES
('Cursos', 'Cursos online e treinamentos', 'cursos'),
('Ebooks', 'Livros digitais e guias', 'ebooks'),
('Templates', 'Modelos e templates', 'templates'),
('Ferramentas', 'Ferramentas e utilitários', 'ferramentas');

-- Inserir alguns produtos de exemplo
INSERT INTO products (title, description, price, is_free, requires_subscription) VALUES
('Curso de PHP Básico', 'Aprenda PHP do zero', 0.00, TRUE, FALSE),
('Ebook Marketing Digital', 'Guia completo de marketing', 29.90, FALSE, TRUE),
('Template Dashboard', 'Template profissional para dashboard', 49.90, FALSE, TRUE),
('Ferramenta de SEO', 'Ferramenta completa de otimização', 79.90, FALSE, TRUE);
