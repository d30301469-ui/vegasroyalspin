<?php
$footerPage = is_array($footerPage ?? null) ? $footerPage : [];
$pageTitle = (string) ($footerPage['title'] ?? 'Footer Sayfası');
$pageContent = (string) ($footerPage['content'] ?? '');

/**
 * CMS içeriğini güvenli HTML'e çevirir.
 * - İçerik HTML ise: izinli etiketler korunur, script/event handler temizlenir.
 * - Düz metin ise: satır sonları paragrafa; "1." "2." gibi numaralı bölümler
 *   numaralı listeye (<ol>), "-", "*", "•" ile başlayan satırlar maddeli
 *   listeye (<ul>) dönüştürülür. Madde başındaki "Başlık:" kısmı vurgulanır.
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

    // Madde metnindeki "Başlık: açıklama" kalıbında başlığı vurgular.
    $emphasizeLabel = static function (string $item): string {
        return (string) preg_replace('/^([^:.]{2,70}):\s+/u', '<strong>$1:</strong> ', $item);
    };

    $text = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Büyük harfli başlığa yapışık metni ayır ("...SÖZLEŞMESİSon Güncelleme...")
    $text = (string) preg_replace('/(?<=[A-ZÇĞİÖŞÜ][A-ZÇĞİÖŞÜ])(?=[A-ZÇĞİÖŞÜ][a-zçğıöşü])/u', "\n", $text);
    // Rakama yapışık yeni cümle başlangıcını ayır ("...2026İşbu...")
    $text = (string) preg_replace('/(?<=\d)(?=[A-ZÇĞİÖŞÜ][a-zçğıöşü])/u', "\n", $text);
    // Cümle sonuna yapışık numaralı bölüm başlangıçlarını ("...eder.5. Veri...") ayır
    $text = (string) preg_replace('/(?<=[.!?;:])\s*(?=\d{1,2}[.)]\s*[A-ZÇĞİÖŞÜ])/u', "\n", $text);
    // Satır içi • işaretlerini ayrı maddelere böl
    $text = (string) preg_replace('/\s*•\s*/u', "\n• ", $text);

    $blocks = array_values(array_filter(array_map('trim', explode("\n", $text)), static fn (string $line): bool => $line !== ''));

    $html = '';
    $openList = ''; // '', 'ol' veya 'ul'
    $closeList = static function () use (&$html, &$openList): void {
        if ($openList !== '') {
            $html .= '</' . $openList . '>';
            $openList = '';
        }
    };

    foreach ($blocks as $block) {
        if (preg_match('/^(\d{1,2})[.)]\s*(.+)$/su', $block, $m) === 1) {
            if ($openList !== 'ol') {
                $closeList();
                $html .= '<ol class="footerPageList">';
                $openList = 'ol';
            }
            $html .= '<li value="' . (int) $m[1] . '">' . $emphasizeLabel($m[2]) . '</li>';
            continue;
        }
        if (preg_match('/^[•*\-–]\s*(.+)$/su', $block, $m) === 1) {
            if ($openList !== 'ul') {
                $closeList();
                $html .= '<ul class="footerPageList">';
                $openList = 'ul';
            }
            $html .= '<li>' . $emphasizeLabel($m[1]) . '</li>';
            continue;
        }
        $closeList();
        // Tamamı büyük harf olan kısa bloklar bölüm başlığıdır
        if (mb_strlen($block) <= 120
            && preg_match('/[A-ZÇĞİÖŞÜ]{2}/u', $block) === 1
            && preg_match('/^[A-ZÇĞİÖŞÜ0-9 ()&\-\'".,\/]+$/u', $block) === 1
        ) {
            $html .= '<h2>' . $block . '</h2>';
            continue;
        }
        $html .= '<p>' . $block . '</p>';
    }
    $closeList();

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
