-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 15/09/2025 às 07:08
-- Versão do servidor: 5.7.40-log
-- Versão do PHP: 8.3.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `alextutoriais`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT '1',
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `slug` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, 'Cursos', 'Cursos online e treinamentos', 'cursos', NULL, '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(2, 'Ebooks', 'Livros digitais e guias', 'ebooks', NULL, '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(3, 'Templates', 'Modelos e templates', 'templates', NULL, '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(4, 'Ferramentas', 'Ferramentas e utilitários', 'ferramentas', NULL, '2025-08-20 13:56:50', '2025-08-20 13:56:50');

-- --------------------------------------------------------

--
-- Estrutura para tabela `downloads`
--

CREATE TABLE `downloads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `downloaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `lesson_progress`
--

CREATE TABLE `lesson_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `completed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','warning','success','danger') COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `priority` enum('low','medium','high') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'ri-notification-line',
  `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_type` enum('subscription','product','cart') NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('pix','bank_transfer','boleto','card') NOT NULL,
  `payment_status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `payment_proof_path` varchar(500) DEFAULT NULL,
  `payment_proof_uploaded_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_type` enum('subscription_plan','product') NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `payment_settings`
--

CREATE TABLE `payment_settings` (
  `id` int(11) NOT NULL,
  `payment_type` enum('pix','bank_transfer','boleto','card') NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `title` varchar(255) NOT NULL,
  `description` text,
  `pix_key` varchar(255) DEFAULT NULL,
  `pix_key_type` enum('cpf','cnpj','email','phone','random') DEFAULT 'random',
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_agency` varchar(50) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `bank_account_type` enum('corrente','poupanca') DEFAULT 'corrente',
  `account_holder` varchar(255) DEFAULT NULL,
  `account_document` varchar(20) DEFAULT NULL,
  `boleto_instructions` text,
  `card_instructions` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `payment_settings`
--

INSERT INTO `payment_settings` (`id`, `payment_type`, `is_active`, `title`, `description`, `pix_key`, `pix_key_type`, `bank_name`, `bank_agency`, `bank_account`, `bank_account_type`, `account_holder`, `account_document`, `boleto_instructions`, `card_instructions`, `created_at`, `updated_at`) VALUES
(1, 'pix', 1, 'Pagamento via PIX', 'Pagamento instantâneo via PIX', 'plw@cnpj.com', 'cnpj', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-22 17:04:07', '2025-08-23 01:18:40');

-- --------------------------------------------------------

--
-- Estrutura para tabela `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `description` text,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `slug`, `description`, `category`, `created_at`, `updated_at`) VALUES
(1, 'manage_users', 'manage_users', 'Gerenciar usuários (criar, editar, excluir)', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(2, 'manage_roles', 'manage_roles', 'Gerenciar roles e permissões', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(3, 'manage_products', 'manage_products', 'Gerenciar produtos (criar, editar, excluir)', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(4, 'manage_categories', 'manage_categories', 'Gerenciar categorias', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(5, 'manage_plans', 'manage_plans', 'Gerenciar planos de assinatura', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(6, 'manage_subscriptions', 'manage_subscriptions', 'Gerenciar assinaturas', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(7, 'view_reports', 'view_reports', 'Visualizar relatórios e estatísticas', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(8, 'manage_system', 'manage_system', 'Configurações do sistema', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(9, 'download_products', 'download_products', 'Baixar produtos', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(10, 'view_products', 'view_products', 'Visualizar produtos', NULL, '2025-08-20 13:56:50', '2025-08-27 12:32:47'),
(11, 'manage_payments', 'manage_payments', 'Gerenciar pagamentos (aprovar, rejeitar, visualizar)', NULL, '2025-08-23 01:45:07', '2025-08-27 12:32:47'),
(12, 'Gerenciar Avisos', 'manage_news', 'Pode gerenciar avisos e notificações', 'admin', '2025-08-27 12:32:47', '2025-08-27 12:32:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `features` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `plans`
--

INSERT INTO `plans` (`id`, `name`, `description`, `price`, `duration_days`, `features`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Básico', 'Acesso a produtos básicos', 29.90, 30, '[\"Produtos básicos\", \"Suporte por email\"]', 1, '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(2, 'Premium', 'Acesso completo a todos os produtos', 59.90, 30, '[\"Todos os produtos\", \"Suporte prioritário\", \"Downloads ilimitados\"]', 1, '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(3, 'Anual', 'Acesso completo por 1 ano', 599.90, 365, '[\"Todos os produtos\", \"Suporte prioritário\", \"Downloads ilimitados\", \"2 meses grátis\"]', 1, '2025-08-20 13:56:50', '2025-08-20 13:56:50');

-- --------------------------------------------------------

--
-- Estrutura para tabela `plan_permissions`
--

CREATE TABLE `plan_permissions` (
  `plan_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `plan_permissions`
--

INSERT INTO `plan_permissions` (`plan_id`, `permission_id`) VALUES
(2, 1),
(2, 2);

-- --------------------------------------------------------

--
-- Estrutura para tabela `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `short_description` text,
  `full_description` longtext,
  `category_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `product_type` enum('free','premium','exclusive') DEFAULT 'free',
  `file_path` varchar(500) DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `gallery_images` text,
  `video_url` varchar(500) DEFAULT NULL,
  `video_apresentacao` varchar(500) DEFAULT NULL,
  `video_thumbnail` varchar(500) DEFAULT NULL,
  `downloads_count` int(11) DEFAULT '0',
  `max_downloads_per_user` int(11) DEFAULT '-1',
  `featured` tinyint(1) DEFAULT '0',
  `status` enum('active','inactive','draft') DEFAULT 'draft',
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text,
  `tags` text,
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `individual_sale` tinyint(1) DEFAULT '0',
  `individual_price` decimal(10,2) DEFAULT '0.00',
  `version` varchar(20) DEFAULT NULL COMMENT 'Versão do produto',
  `last_updated` timestamp NULL DEFAULT NULL COMMENT 'Data da última atualização',
  `requirements` text COMMENT 'Requisitos para instalação',
  `demo_url` varchar(500) DEFAULT NULL COMMENT 'URL da demonstração online'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `product_categories`
--

INSERT INTO `product_categories` (`id`, `name`, `slug`, `description`, `parent_id`, `created_at`) VALUES
(1, 'E-books', 'e-books', 'Livros digitais e guias', NULL, '2025-08-20 20:27:27'),
(2, 'Templates', 'templates', 'Modelos e templates', NULL, '2025-08-20 20:27:27'),
(3, 'Cursos', 'cursos', 'Cursos online e tutoriais', NULL, '2025-08-20 20:27:27'),
(4, 'Ferramentas', 'ferramentas', 'Ferramentas e utilitários', NULL, '2025-08-20 20:27:27'),
(5, 'Outros', 'outros', 'Outros tipos de produtos', NULL, '2025-08-20 20:27:27'),
(6, 'Softwares', 'softwares', 'Softwares diversos', NULL, '2025-08-20 20:30:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `product_downloads`
--

CREATE TABLE `product_downloads` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `downloaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `product_favorites`
--

CREATE TABLE `product_favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `product_materials`
--

CREATE TABLE `product_materials` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('file','link') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `order_index` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `release_days` int(11) DEFAULT '0' COMMENT 'Dias para liberação gradual (0 = imediato)',
  `is_gradual_release` tinyint(1) DEFAULT '0' COMMENT 'Se o material tem liberação gradual'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `product_plans`
--

CREATE TABLE `product_plans` (
  `product_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `product_purchases`
--

CREATE TABLE `product_purchases` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','cancelled','refunded') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchased_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `product_videos`
--

CREATE TABLE `product_videos` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `youtube_url` varchar(500) NOT NULL,
  `duration` varchar(20) DEFAULT NULL,
  `order_index` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'super_admin', 'Super Administrador - Acesso total ao sistema', '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(2, 'admin', 'Administrador - Gerencia usuários e conteúdo', '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(3, 'user', 'Usuário comum - Acesso básico aos produtos', '2025-08-20 13:56:50', '2025-08-20 13:56:50');

-- --------------------------------------------------------

--
-- Estrutura para tabela `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(2, 1),
(1, 2),
(1, 3),
(2, 3),
(1, 4),
(1, 5),
(1, 6),
(2, 6),
(1, 7),
(1, 8),
(1, 9),
(2, 9),
(3, 9),
(1, 10),
(2, 10),
(3, 10),
(1, 11),
(1, 12);

-- --------------------------------------------------------

--
-- Estrutura para tabela `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text,
  `setting_type` enum('text','image','email','url','textarea','checkbox','password') DEFAULT NULL,
  `setting_group` varchar(100) DEFAULT 'general',
  `setting_label` varchar(255) DEFAULT NULL,
  `setting_description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `setting_group`, `setting_label`, `setting_description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Clubinho PRO', 'text', 'general', 'Nome do Site', 'Nome principal do site que aparece no cabeçalho e títulos', '2025-08-20 13:56:50', '2025-08-26 02:20:53'),
(2, 'site_description', 'Simples de usar, poderoso de gerenciar: o Clubinho foi feito para quem deseja crescer com profissionalismo e manter seus produtos digitais sempre em destaque.', 'textarea', 'general', 'Descrição do Site', 'Descrição que aparece em meta tags e SEO', '2025-08-20 13:56:50', '2025-08-26 02:21:06'),
(3, 'site_logo_light', '../uploads/config/68bf43a2da062.png', 'image', 'branding', 'Logo Claro', 'Logo para fundos claros (formato: PNG, JPG)', '2025-08-20 13:56:50', '2025-09-08 20:59:17'),
(4, 'site_logo_dark', '../uploads/config/testeeee.png', 'image', 'branding', 'Logo Escuro', 'Logo para fundos escuros (formato: PNG, JPG)', '2025-08-20 13:56:50', '2025-08-26 13:43:39'),
(5, 'site_logo_icon', '../uploads/config/68a5f94d99e2e.png', 'image', 'branding', 'Logo Ícone', 'Ícone do site para favicon e sidebar (formato: PNG, 32x32px)', '2025-08-20 13:56:50', '2025-08-20 16:35:36'),
(6, 'site_favicon', '../uploads/config/68a5f8e4d7b55.png', 'image', 'branding', 'Favicon', 'Ícone que aparece na aba do navegador (formato: ICO, PNG)', '2025-08-20 13:56:50', '2025-08-20 16:35:12'),
(7, 'contact_email', 'contato@areademembros.com', 'email', 'contact', 'Email de Contato', 'Email principal para contato', '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(8, 'contact_phone', '5541998608485', 'text', 'contact', 'Telefone de Contato', 'Telefone para contato', '2025-08-20 13:56:50', '2025-09-08 21:42:56'),
(9, 'social_facebook', 'https://facebook.com/', 'url', 'social', 'Facebook', 'Link do Facebook', '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(10, 'social_twitter', 'https://twitter.com/', 'url', 'social', 'Twitter', 'Link do Twitter', '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(11, 'social_instagram', 'https://instagram.com/', 'url', 'social', 'Instagram', 'Link do Instagram', '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(12, 'social_linkedin', 'https://linkedin.com/teste', 'url', 'social', 'LinkedIn', 'Link do LinkedIn', '2025-08-20 13:56:50', '2025-09-08 21:38:03'),
(13, 'footer_text', '© 2025 Clubinho PRO. Todos os direitos reservados.', 'textarea', 'general', 'Texto do Rodapé', 'Texto que aparece no rodapé do site', '2025-08-20 13:56:50', '2025-08-26 02:20:53'),
(14, 'maintenance_mode', '0', 'text', 'system', 'Modo Manutenção', 'Ativar/desativar modo de manutenção (0=desativado, 1=ativado)', '2025-08-20 13:56:50', '2025-08-22 17:22:30'),
(15, 'maintenance_message', 'Site em manutenção. Volte em breve!', 'textarea', 'system', 'Mensagem de Manutenção', 'Mensagem exibida quando o site está em manutenção', '2025-08-20 13:56:50', '2025-08-20 13:56:50'),
(46, 'auth_background_image', '../uploads/config/novofundo.jpg', 'image', 'branding', 'Imagem de Fundo (Login/Registro)', 'Imagem de fundo das páginas de login e registro', '2025-08-20 16:33:16', '2025-08-20 20:46:46'),
(101, 'payment_enabled', '1', 'text', 'payment', 'Pagamentos Habilitados', 'Habilitar sistema de pagamentos offline', '2025-08-23 01:18:06', '2025-08-23 01:18:06'),
(147, 'mercadopago_enabled', '1', 'checkbox', 'payment', 'Habilitar Mercado Pago', 'Ativar processamento automático de pagamentos via Mercado Pago', '2025-08-31 14:26:26', '2025-09-08 20:53:55'),
(148, 'mercadopago_public_key', 'APP_USR-fdfdfdf55c-4afdfdfdfa07-59242f48161e', 'text', 'payment', 'Chave Pública do Mercado Pago', 'Chave pública fornecida pelo Mercado Pago', '2025-08-31 14:26:26', '2025-09-14 23:02:11'),
(149, 'mercadopago_access_token', 'APP_USfdffdf', 'password', 'payment', 'Token de Acesso do Mercado Pago', 'Token de acesso privado do Mercado Pago', '2025-08-31 14:26:26', '2025-09-14 23:02:20'),
(150, 'mercadopago_sandbox', '0', 'checkbox', 'payment', 'Modo Sandbox', 'Ativar modo de testes do Mercado Pago', '2025-08-31 14:26:26', '2025-09-04 17:05:57'),
(151, 'offline_payments_enabled', '0', 'checkbox', 'payment', 'Habilitar Pagamentos Offline', 'Ativar pagamentos via PIX, transferência bancária, etc.', '2025-08-31 14:26:26', '2025-09-08 20:53:55'),
(152, 'pix_enabled', '1', 'checkbox', 'payment', 'Habilitar PIX', 'Ativar pagamento via PIX', '2025-08-31 14:26:26', '2025-09-05 18:07:18'),
(153, 'pix_key', 'plw@cnpj.com', 'text', 'payment', 'Chave PIX', 'Sua chave PIX (CPF, email, telefone ou chave aleatória)', '2025-08-31 14:26:26', '2025-09-05 18:07:18'),
(154, 'pix_key_type', 'email', 'text', 'payment', 'Tipo da Chave PIX', 'Tipo da chave PIX (email, cpf, telefone, aleatoria)', '2025-08-31 14:26:26', '2025-08-31 14:26:26'),
(155, 'bank_transfer_enabled', '0', 'checkbox', 'payment', 'Habilitar Transferência Bancária', 'Ativar pagamento via transferência bancária', '2025-08-31 14:26:26', '2025-08-31 14:26:26'),
(156, 'bank_info', '', 'textarea', 'payment', 'Informações Bancárias', 'Dados bancários para transferência (banco, agência, conta, etc.)', '2025-08-31 14:26:26', '2025-08-31 14:26:26'),
(324, 'recaptcha_enabled', '1', 'checkbox', 'system', 'reCAPTCHA Habilitado', 'Ativar/desativar reCAPTCHA no login', '2025-09-09 14:45:25', '2025-09-09 14:47:17'),
(325, 'recaptcha_site_key', 'chave secreta', 'text', 'system', 'reCAPTCHA Site Key', 'Chave pública do reCAPTCHA (Site Key)', '2025-09-09 14:45:25', '2025-09-14 23:08:25'),
(326, 'recaptcha_secret_key', 'chave do site', 'text', 'system', 'reCAPTCHA Secret Key', 'Chave secreta do reCAPTCHA (Secret Key)', '2025-09-09 14:45:25', '2025-09-14 23:08:25');

-- --------------------------------------------------------

--
-- Estrutura para tabela `shopping_carts`
--

CREATE TABLE `shopping_carts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','cancelled','expired','pending') DEFAULT 'pending',
  `start_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `end_date` timestamp NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `max_downloads` int(11) DEFAULT '-1',
  `max_products` int(11) DEFAULT '-1',
  `features` json DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `slug`, `description`, `price`, `duration_days`, `max_downloads`, `max_products`, `features`, `status`, `created_at`) VALUES
(2, 'Premium', 'premium', 'Acesso completo a todos os produtos', 30.00, 30, -1, -1, '[\"downloads_ilimitados\", \"atualizacoes_recorrentes\", \"acesso_antecipado\"]', 'active', '2025-08-21 20:32:31'),
(9, 'Exclusivo', 'exclusivo', 'exclusivo', 150.00, 30, 63, 74, '[\"downloads_ilimitados\", \"suporte_premium\", \"atualizacoes_recorrentes\", \"acesso_antecipado\", \"download_free\", \"download_premium\"]', 'active', '2025-08-22 01:56:50'),
(12, 'Basico', 'basico', 'testeee', 0.00, 30, -1, -1, '[\"suporte_premium\", \"produtos_exclusivos\"]', 'active', '2025-09-08 15:00:32');

-- --------------------------------------------------------

--
-- Estrutura para tabela `system_configs`
--

CREATE TABLE `system_configs` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `system_configs`
--

INSERT INTO `system_configs` (`id`, `config_key`, `config_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'mercadopago_enabled', '1', 'Habilitar Mercado Pago', '2025-08-30 14:53:27', '2025-08-31 15:00:35'),
(2, 'mercadopago_public_key', 'TEST-12345678-1234-1234-1234-123456789012', 'Chave pública do Mercado Pago', '2025-08-30 14:53:27', '2025-08-30 14:54:01'),
(3, 'mercadopago_access_token', 'TEST-12345678901234567890123456789012-123456-123456', 'Token de acesso do Mercado Pago', '2025-08-30 14:53:27', '2025-08-30 14:54:01'),
(4, 'mercadopago_sandbox', '0', 'Modo sandbox do Mercado Pago (1 = ativo, 0 = produção)', '2025-08-30 14:53:27', '2025-08-31 15:00:35'),
(5, 'offline_payments_enabled', '0', 'Habilitar pagamentos offline', '2025-08-30 14:53:27', '2025-08-30 14:54:01'),
(6, 'pix_enabled', '0', 'Habilitar pagamento PIX', '2025-08-30 14:53:27', '2025-08-30 14:54:01'),
(7, 'pix_key', '', 'Chave PIX', '2025-08-30 14:53:27', '2025-08-30 14:54:01'),
(8, 'pix_key_type', 'email', 'Tipo da chave PIX (email, cpf, telefone, aleatoria)', '2025-08-30 14:53:27', '2025-08-30 14:54:01'),
(9, 'bank_transfer_enabled', '0', 'Habilitar transferência bancária', '2025-08-30 14:53:27', '2025-08-30 14:54:01'),
(10, 'bank_info', '', 'Informações bancárias', '2025-08-30 14:53:27', '2025-08-30 14:54:01'),
(11, 'site_name', 'WowDash', 'Nome do site', '2025-08-30 14:53:27', '2025-08-30 14:53:27'),
(12, 'site_description', 'Plataforma de produtos digitais', 'Descrição do site', '2025-08-30 14:53:27', '2025-08-30 14:53:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `bio` text,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role_id` int(11) DEFAULT '3',
  `current_plan_id` int(11) DEFAULT '1',
  `subscription_expires_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'pending',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `bio`, `password`, `avatar`, `role_id`, `current_plan_id`, `subscription_expires_at`, `status`, `email_verified_at`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'admin@exemplo.com', '', 'sou desenvolvedor web', '$2y$10$tA6uSJ.DuJ1jTSU4kHPD7.Z9u3wMSuBR5O1rWfKxNUSPE.eMD7s8e', 'uploads/avatars/avatar_1_1756214402.jpeg', 1, 1, NULL, 'active', NULL, '2025-08-20 14:07:03', '2025-08-26 13:20:02'),
(2, 'Alex Testeee', 'admin@admin.com', NULL, NULL, '$2y$10$OwRuwxfJxTOy3Ntp5LNdLuGQhwQXwD7PN/obsy9y9GSKbU7SM..se', NULL, 2, 1, NULL, 'active', NULL, '2025-08-20 17:56:14', '2025-08-20 17:56:14');

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL,
  `granted` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_subscriptions`
--

CREATE TABLE `user_subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','expired','cancelled','cancelling') DEFAULT 'active',
  `start_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `end_date` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura para tabela `video_comments`
--

CREATE TABLE `video_comments` (
  `id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `video_likes`
--

CREATE TABLE `video_likes` (
  `id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_id` (`cart_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Índices de tabela `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Índices de tabela `downloads`
--
ALTER TABLE `downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Índices de tabela `lesson_progress`
--
ALTER TABLE `lesson_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_video` (`user_id`,`video_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `video_id` (`video_id`),
  ADD KEY `idx_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_completed_at` (`completed_at`);

--
-- Índices de tabela `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_active_priority` (`is_active`,`priority`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Índices de tabela `payment_settings`
--
ALTER TABLE `payment_settings`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `plan_permissions`
--
ALTER TABLE `plan_permissions`
  ADD PRIMARY KEY (`plan_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Índices de tabela `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Índices de tabela `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `product_downloads`
--
ALTER TABLE `product_downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `product_favorites`
--
ALTER TABLE `product_favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Índices de tabela `product_materials`
--
ALTER TABLE `product_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Índices de tabela `product_plans`
--
ALTER TABLE `product_plans`
  ADD PRIMARY KEY (`product_id`,`plan_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_plan_id` (`plan_id`);

--
-- Índices de tabela `product_purchases`
--
ALTER TABLE `product_purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `product_videos`
--
ALTER TABLE `product_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Índices de tabela `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Índices de tabela `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Índices de tabela `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Índices de tabela `shopping_carts`
--
ALTER TABLE `shopping_carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Índices de tabela `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `system_configs`
--
ALTER TABLE `system_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `idx_config_key` (`config_key`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Índices de tabela `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission`);

--
-- Índices de tabela `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Índices de tabela `video_comments`
--
ALTER TABLE `video_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `video_id` (`video_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `video_likes`
--
ALTER TABLE `video_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_video` (`user_id`,`video_id`),
  ADD KEY `video_id` (`video_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `downloads`
--
ALTER TABLE `downloads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `lesson_progress`
--
ALTER TABLE `lesson_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT de tabela `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `payment_settings`
--
ALTER TABLE `payment_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `product_downloads`
--
ALTER TABLE `product_downloads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `product_favorites`
--
ALTER TABLE `product_favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `product_materials`
--
ALTER TABLE `product_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `product_purchases`
--
ALTER TABLE `product_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de tabela `product_videos`
--
ALTER TABLE `product_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT de tabela `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=350;

--
-- AUTO_INCREMENT de tabela `shopping_carts`
--
ALTER TABLE `shopping_carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `system_configs`
--
ALTER TABLE `system_configs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=494;

--
-- AUTO_INCREMENT de tabela `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `video_comments`
--
ALTER TABLE `video_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `video_likes`
--
ALTER TABLE `video_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `shopping_carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `downloads`
--
ALTER TABLE `downloads`
  ADD CONSTRAINT `downloads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `downloads_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `lesson_progress`
--
ALTER TABLE `lesson_progress`
  ADD CONSTRAINT `lesson_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_progress_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_progress_ibfk_3` FOREIGN KEY (`video_id`) REFERENCES `product_videos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `news_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `plan_permissions`
--
ALTER TABLE `plan_permissions`
  ADD CONSTRAINT `plan_permissions_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plan_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `product_downloads`
--
ALTER TABLE `product_downloads`
  ADD CONSTRAINT `product_downloads_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_downloads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `product_favorites`
--
ALTER TABLE `product_favorites`
  ADD CONSTRAINT `product_favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `product_materials`
--
ALTER TABLE `product_materials`
  ADD CONSTRAINT `product_materials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `product_plans`
--
ALTER TABLE `product_plans`
  ADD CONSTRAINT `product_plans_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_plans_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `product_purchases`
--
ALTER TABLE `product_purchases`
  ADD CONSTRAINT `product_purchases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_purchases_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `product_videos`
--
ALTER TABLE `product_videos`
  ADD CONSTRAINT `product_videos_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `shopping_carts`
--
ALTER TABLE `shopping_carts`
  ADD CONSTRAINT `shopping_carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD CONSTRAINT `user_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `video_comments`
--
ALTER TABLE `video_comments`
  ADD CONSTRAINT `video_comments_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `product_videos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `video_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `video_likes`
--
ALTER TABLE `video_likes`
  ADD CONSTRAINT `video_likes_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `product_videos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `video_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
