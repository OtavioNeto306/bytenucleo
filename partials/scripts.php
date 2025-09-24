<?php
// Detectar se está sendo usado de admin/
$isAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$assetPrefix = $isAdmin ? '../' : '';
?>
    <!-- jQuery library js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/bootstrap.bundle.min.js"></script>
    <!-- Apex Chart js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/apexcharts.min.js"></script>
    <!-- Data Table js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/dataTables.min.js"></script>
    <!-- Iconify Font js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/iconify-icon.min.js"></script>
    <!-- jQuery UI js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/jquery-ui.min.js"></script>
    <!-- Vector Map js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/jquery-jvectormap-2.0.5.min.js"></script>
    <script src="<?= $assetPrefix ?>assets/js/lib/jquery-jvectormap-world-mill-en.js"></script>
    <!-- Popup js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/magnifc-popup.min.js"></script>
    <!-- Slick Slider js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/slick.min.js"></script>
    <!-- prism js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/prism.js"></script>
    <!-- file upload js -->
    <script src="<?= $assetPrefix ?>assets/js/lib/file-upload.js"></script>
    <!-- audioplayer -->
    <script src="<?= $assetPrefix ?>assets/js/lib/audioplayer.js"></script>

    <!-- main js -->
    <script src="<?= $assetPrefix ?>assets/js/app.js"></script>

    <?php echo (isset($script) ? $script   : '')?>