<?php
// Detectar se está sendo usado de admin/
$isAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$assetPrefix = $isAdmin ? '../' : '';

// Carregar configurações do site se não estiver disponível
if (!isset($siteConfig)) {
    // Tentar diferentes caminhos possíveis
    $possiblePaths = [
        'includes/SiteConfig.php',
        '../includes/SiteConfig.php',
        '../../includes/SiteConfig.php'
    ];
    
    $loaded = false;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $siteConfig = new SiteConfig($pdo);
            $loaded = true;
            break;
        }
    }
    
    if (!$loaded) {
        // Fallback - usar valores padrão
        $siteConfig = (object) [
            'getSiteName' => function() { return 'Área de Membros'; },
            'getSiteDescription' => function() { return 'Sua área de membros com conteúdo exclusivo'; },
            'getFavicon' => function() use ($assetPrefix) { return $assetPrefix . 'assets/images/favicon.png'; }
        ];
    }
}
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteConfig->getSiteName()) ?></title>
    <meta name="description" content="<?= htmlspecialchars($siteConfig->getSiteDescription()) ?>">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteConfig->getFavicon()) ?>" sizes="16x16">
    <!-- remix icon font css  -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/remixicon.css">
    <!-- BootStrap css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/bootstrap.min.css">
    <!-- Apex Chart css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/apexcharts.css">
    <!-- Data Table css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/dataTables.min.css">
    <!-- Text Editor css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/editor-katex.min.css">
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/editor.atom-one-dark.min.css">
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/editor.quill.snow.css">
    <!-- Date picker css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/flatpickr.min.css">
    <!-- Calendar css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/full-calendar.css">
    <!-- Vector Map css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/jquery-jvectormap-2.0.5.css">
    <!-- Popup css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/magnific-popup.css">
    <!-- Slick Slider css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/slick.css">
    <!-- prism css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/prism.css">
    <!-- file upload css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/file-upload.css">

    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/lib/audioplayer.css">
    <!-- main css -->
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/style.css">
</head>