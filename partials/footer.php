<?php
// Carregar configurações do site se não estiver disponível
if (!isset($siteConfig)) {
    // Fallback - usar valores padrão
    $siteConfig = (object) [
        'getFooterText' => function() { return '© 2024 Área de Membros. Todos os direitos reservados.'; },
        'getSiteName' => function() { return 'Área de Membros'; }
    ];
}

// Buscar links das redes sociais
$socialLinks = [];
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin')");
        $stmt->execute();
        $socialSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Mapear as chaves para nomes mais limpos
        $socialLinks = [
            'facebook' => $socialSettings['social_facebook'] ?? '',
            'twitter' => $socialSettings['social_twitter'] ?? '',
            'instagram' => $socialSettings['social_instagram'] ?? '',
            'linkedin' => $socialSettings['social_linkedin'] ?? ''
        ];
    }
} catch (Exception $e) {
    // Em caso de erro, usar array vazio
    $socialLinks = [];
}
?>
<footer class="d-footer">
    <div class="row align-items-center justify-content-between">
        <div class="col-auto">
            <p class="mb-0"><?= htmlspecialchars($siteConfig->getFooterText()) ?></p>
        </div>
        <div class="col-auto">
            <div class="d-flex align-items-center gap-3">
                <!-- Redes Sociais -->
                <?php if (!empty($socialLinks['facebook'])): ?>
                <a href="<?= htmlspecialchars($socialLinks['facebook']) ?>" target="_blank" class="text-neutral-400 hover:text-primary-600 transition-colors" title="Facebook">
                    <i class="ri-facebook-fill text-lg"></i>
                </a>
                <?php endif; ?>
                
                <?php if (!empty($socialLinks['twitter'])): ?>
                <a href="<?= htmlspecialchars($socialLinks['twitter']) ?>" target="_blank" class="text-neutral-400 hover:text-primary-600 transition-colors" title="Twitter">
                    <i class="ri-twitter-fill text-lg"></i>
                </a>
                <?php endif; ?>
                
                <?php if (!empty($socialLinks['instagram'])): ?>
                <a href="<?= htmlspecialchars($socialLinks['instagram']) ?>" target="_blank" class="text-neutral-400 hover:text-primary-600 transition-colors" title="Instagram">
                    <i class="ri-instagram-fill text-lg"></i>
                </a>
                <?php endif; ?>
                
                <?php if (!empty($socialLinks['linkedin'])): ?>
                <a href="<?= htmlspecialchars($socialLinks['linkedin']) ?>" target="_blank" class="text-neutral-400 hover:text-primary-600 transition-colors" title="LinkedIn">
                    <i class="ri-linkedin-fill text-lg"></i>
                </a>
                <?php endif; ?>
                
                <!-- Separador -->
                <?php if (array_filter($socialLinks)): ?>
                <span class="text-neutral-400 mx-2">|</span>
                <?php endif; ?>
                
                <!-- Made by -->
                <p class="mb-0">Made by <span class="text-primary-600"><?= htmlspecialchars($siteConfig->getSiteName()) ?></span></p>
            </div>
        </div>
    </div>
</footer>