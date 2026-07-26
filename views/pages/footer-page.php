<?php
$footerPage = is_array($footerPage ?? null) ? $footerPage : [];
$pageTitle = (string) ($footerPage['title'] ?? 'Footer Sayfası');
$pageContent = (string) ($footerPage['content'] ?? '');

/**
 * CMS içeriğini güvenli HTML'e çevirir.
 * - İçerik HTML ise: izinli etiketler korunur, script/event handler temizlenir.
 * - Düz metin ise: satır sonları paragrafa, "1." "2." gibi numaralı bölümler
 *   ayrı maddelere (<ol><li>) dönüştürülür.
 */
$renderFooterPageBody = static function (string $content): string {
    $content = trim($content);
    if ($content === '') {
        return '';
    }

    $hasHtml = $content !== strip_tags($content);
    if ($hasHtml) {
        // script/style bloklarını içerikleriyle birlikte kaldır
        $content = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $content);
        $allowed = '<p><br><b><strong><i><em><u><s><small><sup><sub>'
            . '<ul><ol><li><h1><h2><h3><h4><h5><h6><a>'
            . '<table><thead><tbody><tr><td><th><blockquote><hr><span><div><img>';
        $html = strip_tags($content, $allowed);
        // Inline event handler'ları ve javascript: URL'lerini temizle
        $html = (string) preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = (string) preg_replace('/\s+(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2/i', '', $html);

        return $html;
    }

    $text = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Cümle sonuna yapışık numaralı bölüm başlangıçlarını ("...eder.5. Veri...") ayır
    $text = (string) preg_replace('/(?<=[.!?;:])\s*(?=\d{1,2}[.)]\s*[A-ZÇĞİÖŞÜ])/u', "\n", $text);

    $blocks = array_values(array_filter(array_map('trim', explode("\n", $text)), static fn (string $line): bool => $line !== ''));

    $html = '';
    $listOpen = false;
    foreach ($blocks as $block) {
        if (preg_match('/^(\d{1,2})[.)]\s*(.+)$/su', $block, $m) === 1) {
            if (!$listOpen) {
                $html .= '<ol class="footerPageList">';
                $listOpen = true;
            }
            $html .= '<li value="' . (int) $m[1] . '">' . $m[2] . '</li>';
            continue;
        }
        if ($listOpen) {
            $html .= '</ol>';
            $listOpen = false;
        }
        $html .= '<p>' . $block . '</p>';
    }
    if ($listOpen) {
        $html .= '</ol>';
    }

    return $html;
};
?>
<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>

<div class="layout-content-holder-bc footerPageContent">
    <main class="footerPageContainer">
        <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="footerPageBody">
            <?= $renderFooterPageBody($pageContent) ?>
        </div>
    </main>
</div>

<?php include VIEW_PATH . '/partials/footer.php'; ?>
